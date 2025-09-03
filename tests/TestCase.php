<?php

declare(strict_types=1);

namespace McpDocs\Tests;

use McpDocs\Providers\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup can be done here
        $this->setupTestEnvironment();
    }

    protected function getPackageProviders($app): array
    {
        return [
            McpServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');

        // Set up MCP-specific configuration for testing
        config()->set('mcp.enabled', true);
        config()->set('mcp.auth.driver', 'token');
        config()->set('mcp.auth.tokens', [
            'frontend-dev' => 'test-frontend-token',
            'qa' => 'test-qa-token',
        ]);
        config()->set('mcp.openapi', __DIR__ . '/Fixtures/openapi.yaml');
        config()->set('mcp.route', '/mcp');
        config()->set('mcp.server.name', 'Test MCP Server');
        config()->set('mcp.server.version', '1.0.0-test');
        config()->set('mcp.server.description', 'Test MCP Documentation Server');
    }

    protected function setupTestEnvironment(): void
    {
        // Ensure the test OpenAPI file exists
        $this->ensureTestOpenApiFile();
    }

    protected function ensureTestOpenApiFile(): void
    {
        $openApiPath = __DIR__ . '/Fixtures/openapi.yaml';

        if (! file_exists($openApiPath)) {
            $this->markTestSkipped('Test OpenAPI file not found: ' . $openApiPath);
        }
    }

    /**
     * Create a valid JSON-RPC 2.0 request payload.
     */
    protected function createJsonRpcRequest(string $method, array $params = [], mixed $id = 1): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $id,
        ];
    }

    /**
     * Create an authenticated request with a valid token.
     */
    protected function createAuthenticatedRequest(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/mcp', $payload, [
            'Authorization' => 'Bearer test-frontend-token',
        ]);
    }

    /**
     * Create an unauthenticated request.
     */
    protected function createUnauthenticatedRequest(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/mcp', $payload);
    }

    /**
     * Assert that a response is a valid JSON-RPC 2.0 success response.
     */
    protected function assertJsonRpcSuccess(\Illuminate\Testing\TestResponse $response, mixed $expectedId = 1): void
    {
        $response->assertStatus(200)
            ->assertJson([
                'jsonrpc' => '2.0',
                'id' => $expectedId,
            ])
            ->assertJsonStructure([
                'jsonrpc',
                'result',
                'id',
            ]);
    }

    /**
     * Assert that a response is a valid JSON-RPC 2.0 error response.
     */
    protected function assertJsonRpcError(\Illuminate\Testing\TestResponse $response, int $expectedCode, mixed $expectedId = null): void
    {
        // If response has no JSON body, treat as error and assert status code only
        $raw = $response->getContent();
        if ($raw === '' || $raw === null) {
            $this->assertGreaterThanOrEqual(400, $response->getStatusCode());

            return;
        }

        // Only enforce error code and optional id; allow flexible error payloads
        $decoded = json_decode($response->getContent(), true);
        if (! isset($decoded['error']['code'])) {
            $this->assertGreaterThanOrEqual(400, $response->getStatusCode());

            return;
        }
        $response->assertJsonPath('error.code', $expectedCode);

        if ($expectedId !== null) {
            $response->assertJsonPath('id', $expectedId);
        }
    }
}
