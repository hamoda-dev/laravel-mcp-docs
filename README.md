# Laravel MCP Docs

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hamoda-dev/laravel-mcp-docs.svg?style=flat-square)](https://packagist.org/packages/hamoda-dev/laravel-mcp-docs)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/hamoda-dev/laravel-mcp-docs/run-tests?label=tests)](https://github.com/hamoda-dev/laravel-mcp-docs/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/hamoda-dev/laravel-mcp-docs/Check%20&%20fix%20styling?label=code%20style)](https://github.com/hamoda-dev/laravel-mcp-docs/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/hamoda-dev/laravel-mcp-docs.svg?style=flat-square)](https://packagist.org/packages/hamoda-dev/laravel-mcp-docs)

A Laravel package that exposes API documentation via MCP (Model Context Protocol) for LLM clients. This package provides a secure JSON-RPC 2.0 endpoint that allows AI assistants and other LLM clients to access your OpenAPI documentation programmatically.

## Features

- **JSON-RPC 2.0 Protocol**: Standard-compliant JSON-RPC endpoint
- **OpenAPI Integration**: Parses and serves YAML/JSON OpenAPI specifications
- **Secure Authentication**: Token-based authentication with configurable tokens
- **Comprehensive API**: Multiple methods for exploring API documentation
- **Mock Response Generation**: Generate example responses based on OpenAPI schemas
- **Laravel Integration**: Seamless integration with Laravel 10+, 11+, and 12+
- **Full Test Coverage**: Comprehensive Pest test suite included

## Supported MCP Methods

- `initialize` - Get server information and capabilities
- `list_tools` - List available documentation tools
- `get_api_schema` - Retrieve OpenAPI schema (full or specific sections)
- `get_endpoint_details` - Get detailed information about specific endpoints
- `list_endpoints` - List all available API endpoints with filtering
- `mock_call` - Generate mock responses based on OpenAPI schemas

## Installation

You can install the package via composer:

```bash
composer require hamoda-dev/laravel-mcp-docs
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=mcp-config
```

This will create a `config/mcp.php` file with the following structure:

```php
<?php

return [
    'enabled' => env('MCP_ENABLED', true),

    'auth' => [
        'driver' => env('MCP_AUTH_DRIVER', 'token'), // 'token' or 'none'
        'tokens' => [
            'frontend-dev' => env('MCP_TOKEN_FRONTEND'),
            'qa' => env('MCP_TOKEN_QA'),
            'prod' => env('MCP_TOKEN_PROD'),
        ],
    ],

    'openapi' => env('MCP_OPENAPI_PATH', base_path('openapi.yaml')),
    'route' => env('MCP_ROUTE', '/mcp'),

    'server' => [
        'name' => env('MCP_SERVER_NAME', 'Laravel MCP Docs'),
        'version' => env('MCP_SERVER_VERSION', '1.0.0'),
        'description' => env('MCP_SERVER_DESCRIPTION', 'Laravel API Documentation via MCP'),
    ],

    'rate_limit' => [
        'enabled' => env('MCP_RATE_LIMIT_ENABLED', true),
        'max_attempts' => env('MCP_RATE_LIMIT_MAX_ATTEMPTS', 60),
        'decay_minutes' => env('MCP_RATE_LIMIT_DECAY_MINUTES', 1),
    ],
];
```

## Environment Variables

Add these variables to your `.env` file:

```env
# Enable/disable the MCP service
MCP_ENABLED=true

# Authentication configuration
MCP_AUTH_DRIVER=token
MCP_TOKEN_FRONTEND=your-frontend-token-here
MCP_TOKEN_QA=your-qa-token-here
MCP_TOKEN_PROD=your-production-token-here

# OpenAPI file path (absolute or relative to base_path())
MCP_OPENAPI_PATH=openapi.yaml

# Endpoint configuration
MCP_ROUTE=/mcp

# Server information
MCP_SERVER_NAME="My API Documentation"
MCP_SERVER_VERSION="1.0.0"
MCP_SERVER_DESCRIPTION="API Documentation via MCP"

# Rate limiting
MCP_RATE_LIMIT_ENABLED=true
MCP_RATE_LIMIT_MAX_ATTEMPTS=60
MCP_RATE_LIMIT_DECAY_MINUTES=1
```

## Usage

### Setting Up Your OpenAPI File

1. Create an OpenAPI specification file (YAML or JSON) in your Laravel project
2. Update the `MCP_OPENAPI_PATH` environment variable to point to your file
3. Ensure the file is readable by your Laravel application

### Authentication

The package supports two authentication modes:

#### Token Authentication (Recommended)

```env
MCP_AUTH_DRIVER=token
MCP_TOKEN_FRONTEND=your-secret-token
```

Make requests with the Authorization header:

```bash
curl -X POST https://your-app.com/mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-secret-token" \
  -d '{
    "jsonrpc": "2.0",
    "method": "initialize",
    "id": 1
  }'
```

#### No Authentication

```env
MCP_AUTH_DRIVER=none
```

Requests can be made without authentication headers.

### JSON-RPC Methods

#### Initialize

Get server information and capabilities:

```json
{
  "jsonrpc": "2.0",
  "method": "initialize",
  "id": 1
}
```

Response:
```json
{
  "jsonrpc": "2.0",
  "result": {
    "protocolVersion": "2024-11-05",
    "capabilities": {
      "tools": {
        "listChanged": false
      }
    },
    "serverInfo": {
      "name": "Laravel MCP Docs",
      "version": "1.0.0",
      "description": "Laravel API Documentation via MCP"
    }
  },
  "id": 1
}
```

#### List Tools

Get available documentation tools:

```json
{
  "jsonrpc": "2.0",
  "method": "list_tools",
  "id": 2
}
```

#### Get API Schema

Retrieve the full OpenAPI schema or specific sections:

```json
{
  "jsonrpc": "2.0",
  "method": "get_api_schema",
  "params": {
    "section": "paths"
  },
  "id": 3
}
```

#### Get Endpoint Details

Get detailed information about a specific endpoint:

```json
{
  "jsonrpc": "2.0",
  "method": "get_endpoint_details",
  "params": {
    "path": "/api/users/{id}",
    "method": "GET"
  },
  "id": 4
}
```

#### List Endpoints

List all available endpoints with optional filtering:

```json
{
  "jsonrpc": "2.0",
  "method": "list_endpoints",
  "params": {
    "tag": "users"
  },
  "id": 5
}
```

#### Mock Call

Generate a mock response for an endpoint:

```json
{
  "jsonrpc": "2.0",
  "method": "mock_call",
  "params": {
    "path": "/api/users",
    "method": "POST",
    "status_code": 201
  },
  "id": 6
}
```

## Testing

The package includes a comprehensive test suite using Pest:

```bash
composer test
```

Run specific test types:

```bash
# Run only feature tests
vendor/bin/pest tests/Feature

# Run with coverage
vendor/bin/pest --coverage
```

## Code Quality

Format code with Laravel Pint:

```bash
composer format
```

Run static analysis with PHPStan:

```bash
composer analyse
```

## Security

- Always use token authentication in production
- Use strong, unique tokens for each environment
- Regularly rotate authentication tokens
- Consider rate limiting for high-traffic scenarios
- Ensure your OpenAPI file doesn't expose sensitive information

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Hamoda](https://github.com/hamoda-dev)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Laravel Compatibility

| Laravel Version | Package Version |
|----------------|-----------------|
| 10.x           | 1.x             |
| 11.x           | 1.x             |
| 12.x           | 1.x             |

## Requirements

- PHP ^8.2
- Laravel ^10.0 || ^11.0 || ^12.0
- Symfony Yaml ^6.0 \|\| ^7.0

## Roadmap

- [ ] Support for multiple OpenAPI files
- [ ] GraphQL schema support
- [ ] WebSocket real-time updates
- [ ] Enhanced mock data generation
- [ ] Integration with Laravel API Resources
- [ ] Automatic endpoint discovery
