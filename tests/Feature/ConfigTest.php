<?php

declare(strict_types=1);

use McpDocs\Providers\McpServiceProvider;
use McpDocs\Support\OpenApiRepository;

describe('MCP Configuration', function () {
    it('registers service provider automatically', function () {
        $providers = $this->app->getLoadedProviders();

        expect($providers)->toHaveKey(McpServiceProvider::class);
    });

    it('binds OpenApiRepository as singleton', function () {
        $instance1 = $this->app->make(OpenApiRepository::class);
        $instance2 = $this->app->make(OpenApiRepository::class);

        expect($instance1)->toBe($instance2);
    });

    it('publishes config file', function () {
        $this->artisan('vendor:publish', [
            '--tag' => 'mcp-config',
            '--force' => true,
        ]);

        $configPath = config_path('mcp.php');
        expect(file_exists($configPath))->toBe(true);

        // Clean up
        if (file_exists($configPath)) {
            unlink($configPath);
        }
    });

    it('merges default configuration', function () {
        expect((bool) config('mcp.enabled'))->toBe(true);
        expect(config('mcp.auth.driver'))->toBe('token');
        expect(config('mcp.route'))->toBe('/mcp');
        expect(config('mcp.server.name'))->toBe('Test MCP Server');
    });

    it('registers middleware alias', function () {
        $router = $this->app['router'];
        $middleware = $router->getMiddleware();

        expect($middleware)->toHaveKey('mcp.auth');
    });

    it('can disable the service via config', function () {
        config(['mcp.enabled' => false]);

        // Create a new service provider instance to test boot logic
        $provider = new McpServiceProvider($this->app);
        $provider->boot();

        // When disabled, the route should not be accessible
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'id' => 1,
        ]);

        $response->assertStatus(404);
    });

    it('loads configuration from environment variables', function () {
        // Set up environment variables
        config(['mcp.enabled' => env('MCP_ENABLED', true)]);
        config(['mcp.auth.driver' => env('MCP_AUTH_DRIVER', 'token')]);
        config(['mcp.route' => env('MCP_ROUTE', '/mcp')]);

        expect((bool) config('mcp.enabled'))->toBe(true);
        expect(config('mcp.auth.driver'))->toBe('token');
        expect(config('mcp.route'))->toBe('/mcp');
    });

    it('configures auth tokens from environment', function () {
        $tokens = config('mcp.auth.tokens');

        expect($tokens)->toBeArray();
        expect($tokens)->toHaveKey('frontend-dev');
        expect($tokens)->toHaveKey('qa');
    });

    it('sets correct OpenAPI file path', function () {
        $openApiPath = config('mcp.openapi');

        expect($openApiPath)->toContain('openapi.yaml');
        expect(file_exists($openApiPath))->toBe(true);
    });

    it('has proper server configuration', function () {
        expect(config('mcp.server.name'))->toBe('Test MCP Server');
        expect(config('mcp.server.version'))->toBe('1.0.0-test');
        expect(config('mcp.server.description'))->toBe('Test MCP Documentation Server');
    });

    it('has rate limiting configuration', function () {
        expect(config('mcp.rate_limit.enabled'))->toBe(true);
        expect(config('mcp.rate_limit.max_attempts'))->toBe(60);
        expect(config('mcp.rate_limit.decay_minutes'))->toBe(1);
    });

    describe('OpenAPI Repository configuration', function () {
        it('creates repository with correct file path', function () {
            $repository = $this->app->make(OpenApiRepository::class);

            // Use reflection to access protected property
            $reflection = new ReflectionClass($repository);
            $pathProperty = $reflection->getProperty('openApiPath');
            $pathProperty->setAccessible(true);

            $actualPath = $pathProperty->getValue($repository);
            $expectedPath = config('mcp.openapi');

            expect($actualPath)->toBe($expectedPath);
        });

        it('can load schema successfully', function () {
            $repository = $this->app->make(OpenApiRepository::class);
            $schema = $repository->getSchema();

            expect($schema)->toBeArray();
            expect($schema)->toHaveKey('openapi');
            expect($schema)->toHaveKey('info');
            expect($schema)->toHaveKey('paths');
        });
    });

    describe('route configuration', function () {
        it('registers route with correct path', function () {
            $routes = collect($this->app['router']->getRoutes());
            $mcpRoute = $routes->first(function ($route) {
                return str_contains($route->uri(), 'mcp');
            });

            expect($mcpRoute)->not->toBeNull();
            expect($mcpRoute->methods())->toContain('POST');
        });

        it('applies middleware to route', function () {
            $routes = collect($this->app['router']->getRoutes());
            $mcpRoute = $routes->first(function ($route) {
                return str_contains($route->uri(), 'mcp');
            });

            expect($mcpRoute)->not->toBeNull();

            $middleware = $mcpRoute->middleware();
            expect($middleware)->toContain('api');
            expect($middleware)->toContain('mcp.auth');
        });

        it('uses correct route name', function () {
            $routes = collect($this->app['router']->getRoutes());
            $mcpRoute = $routes->first(function ($route) {
                return str_contains($route->uri(), 'mcp');
            });

            expect($mcpRoute)->not->toBeNull();
            expect($mcpRoute->getName())->toBe('mcp.handle');
        });
    });
});
