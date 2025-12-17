<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Foundation\Application;
use Toporia\Framework\Foundation\PackageManifest;
use Toporia\Framework\Routing\Router;

/**
 * Route Service Provider
 *
 * This provider is responsible for loading application and package routes.
 *
 * Route Loading Order (important for catch-all routes):
 * 1. API routes (specific paths, loaded first)
 * 2. Package routes (auto-discovered from packages)
 * 3. Web routes (may include catch-all, loaded last)
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        /** @var Application $app */
        $app = $container->get(Application::class);

        /** @var Router $router */
        $router = $container->get(Router::class);

        // Load middleware configuration
        $middlewareConfig = $container->get('config')->get('middleware', []);
        $middlewareGroups = $middlewareConfig['groups'] ?? [];

        // STEP 1: Load API routes FIRST (specific paths before catch-all)
        $this->loadApiRoutes($app, $router, $middlewareGroups['api'] ?? []);

        // STEP 2: Load package routes (auto-discovered from packages)
        $this->loadPackageRoutes($container, $router);

        // STEP 3: Load web routes LAST (may contain SPA catch-all route)
        $this->loadWebRoutes($app, $router, $middlewareGroups['web'] ?? []);
    }

    /**
     * Load web routes.
     *
     * @param Application $app
     * @param Router $router
     * @param array $middleware
     * @return void
     */
    protected function loadWebRoutes(Application $app, Router $router, array $middleware): void
    {
        $router->group([
            'middleware' => $middleware,
            'namespace' => 'App\\Presentation\\Http\\Controllers',
        ], function (Router $router) use ($app) {
            $path = $app->path('routes/web.php');
            if (file_exists($path)) {
                require $path;
            }
        });
    }

    /**
     * Load API routes.
     *
     * @param Application $app
     * @param Router $router
     * @param array $middleware
     * @return void
     */
    protected function loadApiRoutes(Application $app, Router $router, array $middleware): void
    {
        $router->group([
            'prefix' => 'api',
            'middleware' => $middleware,
            'namespace' => 'App\\Presentation\\Http\\Controllers\\Api',
        ], function (Router $router) use ($app) {
            $path = $app->path('routes/api.php');
            if (file_exists($path)) {
                require $path;
            }
        });
    }

    /**
     * Load routes from packages auto-discovered via PackageManifest.
     *
     * This method loads all routes registered by packages in their composer.json
     * under extra.toporia.routes configuration. Routes are loaded with their
     * specified middleware, prefix, and namespace.
     *
     * Performance: O(N) where N = number of package route files
     *
     * @param ContainerInterface $container
     * @param Router $router
     * @return void
     */
    protected function loadPackageRoutes(ContainerInterface $container, Router $router): void
    {
        // Get package manifest singleton (performance: reuse across all providers)
        $manifest = $container->get(PackageManifest::class);

        // Get all package routes
        $packageRoutes = $manifest->routes();

        if (empty($packageRoutes)) {
            return;
        }

        // Load each package's routes
        foreach ($packageRoutes as $packageName => $routes) {
            if (!is_array($routes)) {
                continue;
            }

            foreach ($routes as $routeConfig) {
                $this->loadPackageRouteFile($router, $routeConfig);
            }
        }
    }

    /**
     * Load a single route file from a package.
     *
     * @param Router $router
     * @param array<string, mixed> $routeConfig Route configuration
     * @return void
     */
    protected function loadPackageRouteFile(Router $router, array $routeConfig): void
    {
        $path = $routeConfig['path'] ?? null;

        if (!$path || !file_exists($path)) {
            return;
        }

        // Build route group attributes
        $attributes = [];

        if (!empty($routeConfig['middleware'])) {
            $attributes['middleware'] = (array) $routeConfig['middleware'];
        }

        if (!empty($routeConfig['prefix'])) {
            $attributes['prefix'] = $routeConfig['prefix'];
        }

        if (!empty($routeConfig['namespace'])) {
            $attributes['namespace'] = $routeConfig['namespace'];
        }

        if (!empty($routeConfig['name'])) {
            $attributes['name'] = $routeConfig['name'];
        }

        // Load route file within group (if attributes exist)
        if (!empty($attributes)) {
            $router->group($attributes, function (Router $router) use ($path) {
                require $path;
            });
        } else {
            // Load directly if no group attributes
            require $path;
        }
    }
}
