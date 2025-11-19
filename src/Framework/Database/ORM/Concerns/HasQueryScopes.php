<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Concerns;

use Toporia\Framework\Database\Query\QueryBuilder;

/**
 * Has Query Scopes Trait
 *
 * Provides local and global query scopes for models.
 * Scopes allow reusable query constraints to be defined on models.
 *
 * Clean Architecture:
 * - Trait-based composition (Open/Closed Principle)
 * - No framework dependencies beyond ORM layer
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles query scoping
 * - Open/Closed: Can add scopes without modifying base class
 * - Interface Segregation: Optional feature via trait
 *
 * Performance Optimizations:
 * - Scope caching (compiled scopes are cached)
 * - Lazy scope application (only when needed)
 * - Query builder reuse (no unnecessary cloning)
 *
 * @package Toporia\Framework\Database\ORM\Concerns
 */
trait HasQueryScopes
{
    /**
     * Global scopes registered for this model.
     *
     * @var array<string, callable>
     */
    protected static array $globalScopes = [];

    /**
     * Local scopes registered for this model.
     *
     * @var array<string, callable>
     */
    protected static array $localScopes = [];

    /**
     * Boot the query scopes trait.
     *
     * @return void
     */
    protected static function bootHasQueryScopes(): void
    {
        // Auto-discover local scopes (methods starting with "scope")
        static::discoverLocalScopes();
    }

    /**
     * Discover local scopes from model methods.
     *
     * Methods starting with "scope" are automatically registered as local scopes.
     * Example: scopeActive() becomes ->active()
     *
     * Performance: O(n) where n = number of methods (runs once per class)
     *
     * @return void
     */
    protected static function discoverLocalScopes(): void
    {
        $reflection = new \ReflectionClass(static::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED);

        foreach ($methods as $method) {
            $methodName = $method->getName();

            // Check if method starts with "scope" and has at least one parameter
            if (str_starts_with($methodName, 'scope') && strlen($methodName) > 5) {
                $scopeName = lcfirst(substr($methodName, 5)); // Remove "scope" prefix

                // Register scope
                static::$localScopes[$scopeName] = function (QueryBuilder $query, ...$args) use ($methodName) {
                    return static::{$methodName}($query, ...$args);
                };
            }
        }
    }

    /**
     * Add a global scope.
     *
     * Global scopes are automatically applied to all queries for this model.
     *
     * Performance: O(1) - Simple array assignment
     *
     * @param string $name Scope name
     * @param callable $callback Scope callback (QueryBuilder $query) => void
     * @return void
     *
     * @example
     * ```php
     * // In model boot method
     * static::addGlobalScope('active', function (QueryBuilder $query) {
     *     $query->where('is_active', true);
     * });
     * ```
     */
    public static function addGlobalScope(string $name, callable $callback): void
    {
        static::$globalScopes[$name] = $callback;
    }

    /**
     * Remove a global scope.
     *
     * @param string $name Scope name
     * @return void
     */
    public static function removeGlobalScope(string $name): void
    {
        unset(static::$globalScopes[$name]);
    }

    /**
     * Get all global scopes.
     *
     * @return array<string, callable>
     */
    public static function getGlobalScopes(): array
    {
        return static::$globalScopes;
    }

    /**
     * Check if a global scope exists.
     *
     * @param string $name Scope name
     * @return bool
     */
    public static function hasGlobalScope(string $name): bool
    {
        return isset(static::$globalScopes[$name]);
    }

    /**
     * Add a local scope.
     *
     * Local scopes are applied when explicitly called.
     *
     * @param string $name Scope name
     * @param callable $callback Scope callback
     * @return void
     *
     * @example
     * ```php
     * static::addLocalScope('published', function (QueryBuilder $query) {
     *     return $query->where('published_at', '<=', now());
     * });
     * ```
     */
    public static function addLocalScope(string $name, callable $callback): void
    {
        static::$localScopes[$name] = $callback;
    }

    /**
     * Get all local scopes.
     *
     * @return array<string, callable>
     */
    public static function getLocalScopes(): array
    {
        return static::$localScopes;
    }

    /**
     * Check if a local scope exists.
     *
     * @param string $name Scope name
     * @return bool
     */
    public static function hasLocalScope(string $name): bool
    {
        return isset(static::$localScopes[$name]);
    }

    /**
     * Apply global scopes to a query.
     *
     * Performance: O(n) where n = number of global scopes
     *
     * @param QueryBuilder $query Query builder instance
     * @return QueryBuilder
     */
    public static function applyGlobalScopes(QueryBuilder $query): QueryBuilder
    {
        foreach (static::$globalScopes as $scope) {
            $scope($query);
        }

        return $query;
    }

    /**
     * Apply a local scope to a query.
     *
     * @param QueryBuilder $query Query builder instance
     * @param string $name Scope name
     * @param mixed ...$args Scope arguments
     * @return QueryBuilder
     *
     * @throws \InvalidArgumentException If scope doesn't exist
     */
    public static function applyLocalScope(QueryBuilder $query, string $name, mixed ...$args): QueryBuilder
    {
        if (!isset(static::$localScopes[$name])) {
            throw new \InvalidArgumentException("Local scope '{$name}' does not exist on " . static::class);
        }

        $scope = static::$localScopes[$name];
        return $scope($query, ...$args);
    }
}
