<?php

declare(strict_types=1);

namespace McpDocs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use McpDocs\Support\OpenApiRepository;

class McpController extends Controller
{
    public function __construct(
        protected OpenApiRepository $openApiRepository
    ) {}

    /**
     * Handle JSON-RPC 2.0 requests.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        // Validate JSON-RPC 2.0 format
        if (! $this->isValidJsonRpc($payload)) {
            return $this->errorResponse(-32600, 'Invalid Request', null);
        }

        $method = $payload['method'];
        $params = $payload['params'] ?? [];
        $id = $payload['id'] ?? null;

        try {
            $result = match ($method) {
                'initialize' => $this->initialize($params),
                'list_tools' => $this->listTools($params),
                'get_api_schema' => $this->getApiSchema($params),
                'get_endpoint_details' => $this->getEndpointDetails($params),
                'list_endpoints' => $this->listEndpoints($params),
                'mock_call' => $this->mockCall($params),
                default => throw new \BadMethodCallException("Method not found: {$method}"),
            };

            return $this->successResponse($result, $id);
        } catch (\BadMethodCallException $e) {
            return $this->errorResponse(-32601, 'Method not found', $id);
        } catch (\Exception $e) {
            return $this->errorResponse(-32603, 'Internal error: ' . $e->getMessage(), $id);
        }
    }

    /**
     * Initialize method - returns server info and capabilities.
     */
    protected function initialize(array $params): array
    {
        return [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [
                'tools' => [
                    'listChanged' => false,
                ],
            ],
            'serverInfo' => [
                'name' => config('mcp.server.name', 'Laravel MCP Docs'),
                'version' => config('mcp.server.version', '1.0.0'),
                'description' => config('mcp.server.description', 'Laravel API Documentation via MCP'),
            ],
        ];
    }

    /**
     * List available tools.
     */
    protected function listTools(array $params): array
    {
        return [
            'tools' => [
                [
                    'name' => 'get_api_schema',
                    'description' => 'Get the full OpenAPI schema or specific parts of it',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'section' => [
                                'type' => 'string',
                                'description' => 'Optional section to retrieve (paths, components, info, etc.)',
                                'enum' => ['paths', 'components', 'info', 'servers', 'tags'],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'get_endpoint_details',
                    'description' => 'Get detailed information about a specific API endpoint',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => [
                                'type' => 'string',
                                'description' => 'The API path (e.g., /api/users/{id})',
                            ],
                            'method' => [
                                'type' => 'string',
                                'description' => 'HTTP method',
                                'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'],
                            ],
                        ],
                        'required' => ['path', 'method'],
                    ],
                ],
                [
                    'name' => 'list_endpoints',
                    'description' => 'List all available API endpoints with basic information',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'tag' => [
                                'type' => 'string',
                                'description' => 'Optional tag to filter endpoints',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'mock_call',
                    'description' => 'Generate a mock response for an endpoint based on its schema',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => [
                                'type' => 'string',
                                'description' => 'The API path',
                            ],
                            'method' => [
                                'type' => 'string',
                                'description' => 'HTTP method',
                            ],
                            'status_code' => [
                                'type' => 'integer',
                                'description' => 'HTTP status code for the mock response (default: 200)',
                                'default' => 200,
                            ],
                        ],
                        'required' => ['path', 'method'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get API schema.
     */
    protected function getApiSchema(array $params): array
    {
        $schema = $this->openApiRepository->getSchema();
        $section = $params['section'] ?? null;

        if ($section && isset($schema[$section])) {
            return [$section => $schema[$section]];
        }

        return $schema;
    }

    /**
     * Get endpoint details.
     */
    protected function getEndpointDetails(array $params): array
    {
        if (! isset($params['path']) || ! isset($params['method'])) {
            throw new \InvalidArgumentException('Both path and method parameters are required');
        }

        $details = $this->openApiRepository->getEndpointDetails($params['path'], $params['method']);

        if (! $details) {
            throw new \InvalidArgumentException('Endpoint not found');
        }

        return $details;
    }

    /**
     * Generate mock call response.
     */
    protected function mockCall(array $params): array
    {
        if (! isset($params['path']) || ! isset($params['method'])) {
            throw new \InvalidArgumentException('Both path and method parameters are required');
        }

        $statusCode = $params['status_code'] ?? 200;
        $mock = $this->openApiRepository->getMockResponse($params['path'], $params['method'], $statusCode);

        if ($mock === null) {
            throw new \InvalidArgumentException('Cannot generate mock response for this endpoint');
        }

        return [
            'path' => $params['path'],
            'method' => strtoupper($params['method']),
            'status_code' => $statusCode,
            'mock_response' => $mock,
        ];
    }

    /**
     * List all endpoints.
     */
    protected function listEndpoints(array $params): array
    {
        $endpoints = $this->openApiRepository->getAvailableEndpoints();
        $tag = $params['tag'] ?? null;

        if ($tag) {
            $endpoints = array_filter($endpoints, function ($endpoint) use ($tag) {
                return in_array($tag, $endpoint['tags']);
            });
        }

        return [
            'endpoints' => array_values($endpoints),
            'total' => count($endpoints),
        ];
    }

    /**
     * Validate JSON-RPC 2.0 format.
     */
    protected function isValidJsonRpc(array $payload): bool
    {
        return isset($payload['jsonrpc']) &&
               $payload['jsonrpc'] === '2.0' &&
               isset($payload['method']);
    }

    /**
     * Return a success response.
     */
    protected function successResponse(mixed $result, mixed $id): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id,
        ]);
    }

    /**
     * Return an error response.
     */
    protected function errorResponse(int $code, string $message, mixed $id): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'id' => $id,
        ], $this->getHttpStatusFromJsonRpcError($code));
    }

    /**
     * Map JSON-RPC error codes to HTTP status codes.
     */
    protected function getHttpStatusFromJsonRpcError(int $code): int
    {
        return match ($code) {
            -32600 => 400, // Invalid Request
            -32601 => 404, // Method not found
            -32602 => 400, // Invalid params
            -32603 => 500, // Internal error
            -32001 => 401, // Authentication error
            default => 500,
        };
    }
}
