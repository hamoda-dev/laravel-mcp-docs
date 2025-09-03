<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Documentation Service Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains settings for the MCP (Model Context
    | Protocol) documentation service that exposes your API documentation
    | to LLM clients via JSON-RPC 2.0 protocol.
    |
    */

    'enabled' => env('MCP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Configure authentication for the MCP endpoint. Supported drivers:
    | - 'token': Requires Bearer token authentication
    | - 'none': No authentication required
    |
    */

    'auth' => [
        'driver' => env('MCP_AUTH_DRIVER', 'token'), // 'token' or 'none'
        'tokens' => [
            'frontend-dev' => env('MCP_TOKEN_FRONTEND'),
            'qa' => env('MCP_TOKEN_QA'),
            'prod' => env('MCP_TOKEN_PROD'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAPI Configuration
    |--------------------------------------------------------------------------
    |
    | Path to your OpenAPI specification file. Can be absolute or relative
    | to the Laravel application's base path.
    |
    */

    'openapi' => env('MCP_OPENAPI_PATH', base_path('openapi.yaml')),

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | The route path where the MCP endpoint will be available.
    |
    */

    'route' => env('MCP_ROUTE', '/mcp'),

    /*
    |--------------------------------------------------------------------------
    | Server Information
    |--------------------------------------------------------------------------
    |
    | Server information returned by the initialize method.
    |
    */

    'server' => [
        'name' => env('MCP_SERVER_NAME', 'Laravel MCP Docs'),
        'version' => env('MCP_SERVER_VERSION', '1.0.0'),
        'description' => env('MCP_SERVER_DESCRIPTION', 'Laravel API Documentation via MCP'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for the MCP endpoint.
    |
    */

    'rate_limit' => [
        'enabled' => env('MCP_RATE_LIMIT_ENABLED', true),
        'max_attempts' => env('MCP_RATE_LIMIT_MAX_ATTEMPTS', 60),
        'decay_minutes' => env('MCP_RATE_LIMIT_DECAY_MINUTES', 1),
    ],
];
