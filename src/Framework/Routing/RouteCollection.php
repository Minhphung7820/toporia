<?php

declare(strict_types=1);

namespace Toporia\Framework\Routing;

use Toporia\Framework\Routing\Contracts\{RouteCollectionInterface, RouteInterface};
/**
 * Collection of routes with efficient lookup.
 *
 * Performance Optimizations:
 * - Index routes by HTTP method (O(1) method lookup, O(M) search where M = routes for that method)
 * - Separate exact routes from pattern routes (O(1) exact match vs O(M) pattern match)
 * - Fast rejection if method has no routes
 *
 * Time Complexity:
 * - Before: O(N) where N = total routes
 * - After: O(M) where M = routes for specific method (typically M << N)
 *
 * Example: With 200 routes (50 GET, 50 POST, 50 PUT, 50 DELETE):
 * - Before: Check all 200 routes = 200 iterations
 * - After: Check only 50 routes for GET = 50 iterations (4x faster)
 */
final class RouteCollection implements RouteCollectionInterface
{
    /**
     * @var array<RouteInterface> All routes (for backward compatibility).
     */
    private array $routes = [];

    /**
     * @var array<string, RouteInterface> Named routes.
     */
    private array $namedRoutes = [];

    /**
     * @var array<string, array<RouteInterface>> Routes indexed by HTTP method.
     * Format: ['GET' => [route1, route2], 'POST' => [route3]]
     */
    private array $routesByMethod = [];

    /**
     * @var array<string, array<string, RouteInterface>> Exact routes indexed by method and URI.
     * Format: ['GET' => ['/api/users' => $route], 'POST' => ['/api/users' => $route]]
     * O(1) lookup for exact matches.
     */
    private array $exactRoutes = [];

    /**
     * @var bool Whether indexes need to be rebuilt.
     */
    private bool $needsIndexing = false;

    /**
     * {@inheritdoc}
     */
    public function add(RouteInterface $route): void
    {
        $this->routes[] = $route;

        // Index by name if available
        if ($route->getName() !== null) {
            $this->namedRoutes[$route->getName()] = $route;
        }

        // Mark that indexes need rebuilding
        $this->needsIndexing = true;
    }

    /**
     * {@inheritdoc}
     */
    public function match(string $method, string $uri): ?array
    {
        // Rebuild indexes if needed (lazy indexing)
        if ($this->needsIndexing) {
            $this->buildIndexes();
        }

        // Fast rejection: No routes for this method
        if (!isset($this->routesByMethod[$method])) {
            return null;
        }

        // Fast path: Try exact match first (O(1) lookup)
        if (isset($this->exactRoutes[$method][$uri])) {
            $route = $this->exactRoutes[$method][$uri];
            return [
                'route' => $route,
                'parameters' => []
            ];
        }

        // Pattern matching: Only check routes for this method (O(M) where M = routes for method)
        $routesForMethod = $this->routesByMethod[$method];
        foreach ($routesForMethod as $route) {
            // Skip exact routes (already checked above)
            $routeUri = $route->getUri();
            if (str_contains($routeUri, '{')) {
                // This is a pattern route
                $parameters = $route->matches($method, $uri);

                if ($parameters !== null) {
                    return [
                        'route' => $route,
                        'parameters' => $parameters
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Build indexes for fast route lookup.
     *
     * This method is called lazily when first match() is called after routes are added.
     * This ensures we don't waste time indexing if routes are never matched.
     */
    private function buildIndexes(): void
    {
        // Reset indexes
        $this->routesByMethod = [];
        $this->exactRoutes = [];

        foreach ($this->routes as $route) {
            $methods = $route->getMethods();
            $methods = is_array($methods) ? $methods : [$methods];
            $uri = $route->getUri();

            // Check if this is an exact route (no parameters)
            $isExactRoute = !str_contains($uri, '{');

            foreach ($methods as $method) {
                // Index by method
                if (!isset($this->routesByMethod[$method])) {
                    $this->routesByMethod[$method] = [];
                }
                $this->routesByMethod[$method][] = $route;

                // Index exact routes separately for O(1) lookup
                if ($isExactRoute) {
                    if (!isset($this->exactRoutes[$method])) {
                        $this->exactRoutes[$method] = [];
                    }
                    $this->exactRoutes[$method][$uri] = $route;
                }
            }
        }

        $this->needsIndexing = false;
    }

    /**
     * {@inheritdoc}
     */
    public function getByName(string $name): ?RouteInterface
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->routes;
    }
}
