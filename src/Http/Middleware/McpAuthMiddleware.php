<?php

declare(strict_types=1);

namespace McpDocs\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class McpAuthMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if (! config('mcp.enabled', true)) {
            return new \Illuminate\Http\JsonResponse(['message' => 'Not Found'], \Illuminate\Http\JsonResponse::HTTP_NOT_FOUND);
        }

        $authDriver = config('mcp.auth.driver', 'token');

        if ($authDriver === 'none') {
            return $next($request);
        }

        if ($authDriver === 'token') {
            return $this->handleTokenAuth($request, $next);
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => 'Invalid authentication driver configured',
            ],
            'id' => null,
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Handle token-based authentication.
     */
    protected function handleTokenAuth(Request $request, Closure $next): SymfonyResponse
    {
        $authHeader = $request->header('Authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorizedResponse('Missing or invalid Authorization header');
        }

        $token = substr($authHeader, 7); // Remove 'Bearer ' prefix
        $validTokens = array_filter(config('mcp.auth.tokens', []));

        if (empty($validTokens)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'No authentication tokens configured',
                ],
                'id' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (! in_array($token, $validTokens, true)) {
            return $this->unauthorizedResponse('Invalid authentication token');
        }

        return $next($request);
    }

    /**
     * Return an unauthorized response.
     */
    protected function unauthorizedResponse(string $message): JsonResponse
    {
        return new JsonResponse([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32001,
                'message' => $message,
            ],
            'id' => null,
        ], JsonResponse::HTTP_UNAUTHORIZED);
    }
}
