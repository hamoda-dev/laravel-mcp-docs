<?php

declare(strict_types=1);

describe('MCP Controller JSON-RPC Methods', function () {
    beforeEach(function () {
        config(['mcp.auth.driver' => 'none']); // Disable auth for these tests
    });

    describe('JSON-RPC validation', function () {
        it('validates JSON-RPC 2.0 format', function () {
            $response = $this->postJson('/mcp', [
                'method' => 'initialize',
                // Missing jsonrpc field
            ]);

            $this->assertJsonRpcError($response, -32600);
        });

        it('requires jsonrpc version 2.0', function () {
            $response = $this->postJson('/mcp', [
                'jsonrpc' => '1.0',
                'method' => 'initialize',
            ]);

            $this->assertJsonRpcError($response, -32600);
        });

        it('requires method field', function () {
            $response = $this->postJson('/mcp', [
                'jsonrpc' => '2.0',
                // Missing method field
            ]);

            $this->assertJsonRpcError($response, -32600);
        });

        it('returns method not found for unknown methods', function () {
            $payload = $this->createJsonRpcRequest('unknown_method');
            $response = $this->postJson('/mcp', $payload);

            $this->assertJsonRpcError($response, -32601);
        });
    });

    describe('initialize method', function () {
        it('returns server information and capabilities', function () {
            $payload = $this->createJsonRpcRequest('initialize');
            $response = $this->postJson('/mcp', $payload);

            $this->assertJsonRpcSuccess($response);
            $response->assertJsonStructure([
                'result' => [
                    'protocolVersion',
                    'capabilities',
                    'serverInfo' => [
                        'name',
                        'version',
                        'description',
                    ],
                ],
            ]);
        });

        it('returns correct protocol version', function () {
            $payload = $this->createJsonRpcRequest('initialize');
            $response = $this->postJson('/mcp', $payload);

            $response->assertJson([
                'result' => [
                    'protocolVersion' => '2024-11-05',
                ],
            ]);
        });
    });

    describe('list_tools method', function () {
        it('returns available tools', function () {
            $payload = $this->createJsonRpcRequest('list_tools');
            $response = $this->postJson('/mcp', $payload);

            $this->assertJsonRpcSuccess($response);
            $response->assertJsonStructure([
                'result' => [
                    'tools' => [
                        '*' => [
                            'name',
                            'description',
                            'inputSchema',
                        ],
                    ],
                ],
            ]);
        });

        it('includes all expected tools', function () {
            $payload = $this->createJsonRpcRequest('list_tools');
            $response = $this->postJson('/mcp', $payload);

            $tools = $response->json('result.tools');
            $toolNames = collect($tools)->pluck('name')->toArray();

            expect($toolNames)->toContain('get_api_schema');
            expect($toolNames)->toContain('get_endpoint_details');
            expect($toolNames)->toContain('list_endpoints');
            expect($toolNames)->toContain('mock_call');
        });
    });

    describe('get_api_schema method', function () {
        it('returns full OpenAPI schema by default', function () {
            $payload = $this->createJsonRpcRequest('get_api_schema');
            $response = $this->postJson('/mcp', $payload);

            $this->assertJsonRpcSuccess($response);
            $response->assertJsonStructure([
                'result' => [
                    'openapi',
                    'info',
                    'paths',
                ],
            ]);
        });

        it('returns specific section when requested', function () {
            $payload = $this->createJsonRpcRequest('get_api_schema', ['section' => 'info']);
            $response = $this->postJson('/mcp', $payload);

            $this->assertJsonRpcSuccess($response);
            $response->assertJsonStructure([
                'result' => [
                    'info',
                ],
            ]);

            // Should not contain other sections
            expect($response->json('result'))->not->toHaveKey('paths');
            expect($response->json('result'))->not->toHaveKey('components');
        });
    });

    describe('get_endpoint_details method', function () {
        it('requires path and method parameters', function () {
            $payload = $this->createJsonRpcRequest('get_endpoint_details');
            $response = $this->postJson('/mcp', $payload);

            $this->assertJsonRpcError($response, -32603);
        });

        it('returns endpoint details for valid path and method', function () {
            $payload = $this->createJsonRpcRequest('get_endpoint_details', [
                'path' => '/users',
                'method' => 'GET',
            ]);
            $response = $this->postJson('/mcp', $payload);

            $this->assertJsonRpcSuccess($response);
            $response->assertJsonStructure([
                'result' => [
                    'path',
                    'method',
                    'summary',
                    'parameters',
                    'responses',
                ],
            ]);
        });

        it('returns error for non-existent endpoint', function () {
            $payload = $this->createJsonRpcRequest('get_endpoint_details', [
                'path' => '/nonexistent',
                'method' => 'GET',
            ]);
            $response = $this->postJson('/mcp', $payload);

            $this->assertJsonRpcError($response, -32603);
        });

        it('handles case-insensitive HTTP methods', function () {
            $payload = $this->createJsonRpcRequest('get_endpoint_details', [
                'path' => '/users',
                'method' => 'get', // lowercase
            ]);
            $response = $this->postJson('/mcp', $payload);

            $this->assertJsonRpcSuccess($response);
            $response->assertJson([
                'result' => [
                    'method' => 'GET', // Should be uppercase in response
                ],
            ]);
        });
    });

    describe('list_endpoints method', function () {
        it('returns all available endpoints', function () {
            $payload = $this->createJsonRpcRequest('list_endpoints');
            $response = $this->postJson('/mcp', $payload);

            $this->assertJsonRpcSuccess($response);
            $response->assertJsonStructure([
                'result' => [
                    'endpoints' => [
                        '*' => [
                            'path',
                            'method',
                            'summary',
                            'tags',
                        ],
                    ],
                    'total',
                ],
            ]);
        });

        it('filters endpoints by tag when provided', function () {
            $payload = $this->createJsonRpcRequest('list_endpoints', ['tag' => 'users']);
            $response = $this->postJson('/mcp', $payload);

            $this->assertJsonRpcSuccess($response);

            $endpoints = $response->json('result.endpoints');

            // All returned endpoints should have the 'users' tag
            foreach ($endpoints as $endpoint) {
                expect($endpoint['tags'])->toContain('users');
            }
        });
    });

    describe('mock_call method', function () {
        it('requires path and method parameters', function () {
            $payload = $this->createJsonRpcRequest('mock_call');
            $response = $this->postJson('/mcp', $payload);

            $this->assertJsonRpcError($response, -32603);
        });

        it('generates mock response for valid endpoint', function () {
            $payload = $this->createJsonRpcRequest('mock_call', [
                'path' => '/users',
                'method' => 'GET',
            ]);
            $response = $this->postJson('/mcp', $payload);

            $this->assertJsonRpcSuccess($response);
            $response->assertJsonStructure([
                'result' => [
                    'path',
                    'method',
                    'status_code',
                    'mock_response',
                ],
            ]);
        });

        it('uses default status code 200', function () {
            $payload = $this->createJsonRpcRequest('mock_call', [
                'path' => '/users',
                'method' => 'GET',
            ]);
            $response = $this->postJson('/mcp', $payload);

            $response->assertJson([
                'result' => [
                    'status_code' => 200,
                ],
            ]);
        });

        it('uses custom status code when provided', function () {
            $payload = $this->createJsonRpcRequest('mock_call', [
                'path' => '/users',
                'method' => 'POST',
                'status_code' => 201,
            ]);
            $response = $this->postJson('/mcp', $payload);

            $response->assertJson([
                'result' => [
                    'status_code' => 201,
                ],
            ]);
        });

        it('returns error for endpoint without schema', function () {
            $payload = $this->createJsonRpcRequest('mock_call', [
                'path' => '/users/{id}',
                'method' => 'DELETE', // This endpoint returns 204 with no content
            ]);
            $response = $this->postJson('/mcp', $payload);

            $this->assertJsonRpcError($response, -32603);
        });
    });

    describe('error handling', function () {
        it('handles OpenAPI file not found gracefully', function () {
            config(['mcp.openapi' => '/path/to/nonexistent/file.yaml']);

            $payload = $this->createJsonRpcRequest('get_api_schema');
            $response = $this->postJson('/mcp', $payload);

            $this->assertJsonRpcError($response, -32603);
        });

        it('preserves request ID in responses', function () {
            $payload = $this->createJsonRpcRequest('initialize', [], 'custom-id');
            $response = $this->postJson('/mcp', $payload);

            $response->assertJson(['id' => 'custom-id']);
        });

        it('handles null ID in requests', function () {
            $payload = $this->createJsonRpcRequest('initialize', [], null);
            $response = $this->postJson('/mcp', $payload);

            $response->assertJson(['id' => null]);
        });
    });
});
