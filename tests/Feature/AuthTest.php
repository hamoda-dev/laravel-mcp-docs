<?php

declare(strict_types=1);

describe('MCP Authentication', function () {
    beforeEach(function () {
        config(['mcp.auth.driver' => 'token']);
        config(['mcp.auth.tokens' => [
            'frontend-dev' => 'test-frontend-token',
            'qa' => 'test-qa-token',
        ]]);
    });

    it('allows requests with valid bearer token', function () {
        $payload = $this->createJsonRpcRequest('initialize');

        $response = $this->postJson('/mcp', $payload, [
            'Authorization' => 'Bearer test-frontend-token',
        ]);

        $this->assertJsonRpcSuccess($response);
    });

    it('rejects requests without authorization header', function () {
        $payload = $this->createJsonRpcRequest('initialize');

        $response = $this->postJson('/mcp', $payload);

        $this->assertJsonRpcError($response, -32001);
        $response->assertStatus(401);
    });

    it('rejects requests with invalid bearer token', function () {
        $payload = $this->createJsonRpcRequest('initialize');

        $response = $this->postJson('/mcp', $payload, [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $this->assertJsonRpcError($response, -32001);
        $response->assertStatus(401);
    });

    it('rejects requests with malformed authorization header', function () {
        $payload = $this->createJsonRpcRequest('initialize');

        $response = $this->postJson('/mcp', $payload, [
            'Authorization' => 'InvalidFormat token',
        ]);

        $this->assertJsonRpcError($response, -32001);
        $response->assertStatus(401);
    });

    it('allows multiple valid tokens', function () {
        $payload = $this->createJsonRpcRequest('initialize');

        // Test first token
        $response1 = $this->postJson('/mcp', $payload, [
            'Authorization' => 'Bearer test-frontend-token',
        ]);
        $this->assertJsonRpcSuccess($response1);

        // Test second token
        $response2 = $this->postJson('/mcp', $payload, [
            'Authorization' => 'Bearer test-qa-token',
        ]);
        $this->assertJsonRpcSuccess($response2);
    });

    it('skips authentication when driver is none', function () {
        config(['mcp.auth.driver' => 'none']);

        $payload = $this->createJsonRpcRequest('initialize');
        $response = $this->postJson('/mcp', $payload);

        $this->assertJsonRpcSuccess($response);
    });

    it('returns error for invalid auth driver', function () {
        config(['mcp.auth.driver' => 'invalid-driver']);

        $payload = $this->createJsonRpcRequest('initialize');
        $response = $this->postJson('/mcp', $payload);

        $this->assertJsonRpcError($response, -32600);
        $response->assertStatus(500);
    });

    it('returns error when no tokens are configured', function () {
        config(['mcp.auth.driver' => 'token']);
        config(['mcp.auth.tokens' => []]);

        $payload = $this->createJsonRpcRequest('initialize');
        $response = $this->postJson('/mcp', $payload, [
            'Authorization' => 'Bearer any-token',
        ]);

        $this->assertJsonRpcError($response, -32600);
        $response->assertStatus(500);
    });

    it('ignores null tokens in configuration', function () {
        config(['mcp.auth.driver' => 'token']);
        config(['mcp.auth.tokens' => [
            'frontend-dev' => 'test-frontend-token',
            'qa' => null, // This should be ignored
        ]]);

        $payload = $this->createJsonRpcRequest('initialize');

        // Valid token should work
        $response1 = $this->postJson('/mcp', $payload, [
            'Authorization' => 'Bearer test-frontend-token',
        ]);
        $this->assertJsonRpcSuccess($response1);

        // Null token should not work
        $response2 = $this->postJson('/mcp', $payload, [
            'Authorization' => 'Bearer qa-token',
        ]);
        $this->assertJsonRpcError($response2, -32001);
    });
});
