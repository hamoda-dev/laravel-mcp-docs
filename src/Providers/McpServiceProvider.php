<?php

declare(strict_types=1);

namespace McpDocs\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use McpDocs\Http\Middleware\McpAuthMiddleware;
use McpDocs\Support\OpenApiRepository;

class McpServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/mcp.php',
            'mcp'
        );

        $this->app->singleton(OpenApiRepository::class, function ($app) {
            return new OpenApiRepository(config('mcp.openapi'));
        });

        $this->app->singleton('mcp.middleware.auth', McpAuthMiddleware::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->bootPublishing();
        $this->bootRoutes();
        $this->bootMiddleware();
    }

    /**
     * Boot publishing configuration.
     */
    protected function bootPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/mcp.php' => config_path('mcp.php'),
            ], 'mcp-config');
        }
    }

    /**
     * Boot routes.
     */
    protected function bootRoutes(): void
    {
        if (! config('mcp.enabled', true)) {
            return;
        }

        Route::group([
            'prefix' => ltrim(config('mcp.route', '/mcp'), '/'),
            'middleware' => ['api'],
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/mcp.php');
        });
    }

    /**
     * Boot middleware.
     */
    protected function bootMiddleware(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('mcp.auth', McpAuthMiddleware::class);
    }
}
