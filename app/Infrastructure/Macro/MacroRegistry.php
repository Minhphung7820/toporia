<?php

declare(strict_types=1);

namespace App\Infrastructure\Macro;

use Toporia\Framework\Macro\Contracts\MacroRegistryInterface;
use Toporia\Framework\Cache\Contracts\CacheInterface;

/**
 * Macro Registry Implementation
 *
 * Provides centralized macro registration and retrieval with caching.
 *
 * Clean Architecture:
 * - Implements Domain MacroRegistryInterface
 * - Uses Framework CacheInterface for performance
 *
 * SOLID Principles:
 * - Single Responsibility: Manages macro storage and retrieval
 * - Open/Closed: Extensible via interface
 * - Dependency Inversion: Depends on CacheInterface abstraction
 *
 * Performance:
 * - O(1) registration and lookup using hash maps
 * - Cached macros for fast access
 * - Memory-efficient storage
 */
final class MacroRegistry implements MacroRegistryInterface
{
    /**
     * @var array<string, array<string, callable>> Macros by target class
     * Format: ['ClassName' => ['macroName' => callable]]
     */
    private array $macros = [];

    /**
     * @var array<string, callable> Cached resolved macros
     * Format: ['ClassName::macroName' => callable]
     */
    private array $resolvedCache = [];

    /**
     * @param CacheInterface|null $persistentCache Optional cache for persistence
     */
    public function __construct(
        private readonly ?CacheInterface $persistentCache = null
    ) {}

    /**
     * {@inheritdoc}
     */
    public function register(string $target, string $name, callable $callback): void
    {
        // Normalize target class name
        $target = $this->normalizeTarget($target);

        // Initialize target array if not exists
        if (!isset($this->macros[$target])) {
            $this->macros[$target] = [];
        }

        // Register macro
        $this->macros[$target][$name] = $callback;

        // Clear cache for this target
        $this->clearCache($target, $name);

        // Persist to cache if available
        if ($this->persistentCache !== null) {
            $cacheKey = $this->getCacheKey($target, $name);
            $this->persistentCache->set($cacheKey, $callback, 3600); // 1 hour
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $target, string $name): bool
    {
        $target = $this->normalizeTarget($target);

        // Check memory first
        if (isset($this->macros[$target][$name])) {
            return true;
        }

        // Check persistent cache if available
        if ($this->persistentCache !== null) {
            $cacheKey = $this->getCacheKey($target, $name);
            return $this->persistentCache->has($cacheKey);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $target, string $name): ?callable
    {
        $target = $this->normalizeTarget($target);

        // Check resolved cache first (O(1) lookup)
        $resolvedKey = "{$target}::{$name}";
        if (isset($this->resolvedCache[$resolvedKey])) {
            return $this->resolvedCache[$resolvedKey];
        }

        // Check memory
        if (isset($this->macros[$target][$name])) {
            $callback = $this->macros[$target][$name];
            $this->resolvedCache[$resolvedKey] = $callback; // Cache for next time
            return $callback;
        }

        // Check persistent cache if available
        if ($this->persistentCache !== null) {
            $cacheKey = $this->getCacheKey($target, $name);
            $callback = $this->persistentCache->get($cacheKey);
            if ($callback !== null && is_callable($callback)) {
                $this->resolvedCache[$resolvedKey] = $callback; // Cache in memory
                return $callback;
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(string $target): array
    {
        $target = $this->normalizeTarget($target);

        $macros = $this->macros[$target] ?? [];

        // Load from persistent cache if available
        if ($this->persistentCache !== null && empty($macros)) {
            $cacheKey = "macros:{$target}";
            $cached = $this->persistentCache->get($cacheKey);
            if ($cached !== null && is_array($cached)) {
                $macros = $cached;
            }
        }

        return $macros;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $target, string $name): void
    {
        $target = $this->normalizeTarget($target);

        unset($this->macros[$target][$name]);
        $this->clearCache($target, $name);

        // Remove from persistent cache
        if ($this->persistentCache !== null) {
            $cacheKey = $this->getCacheKey($target, $name);
            $this->persistentCache->delete($cacheKey);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear(string $target): void
    {
        $target = $this->normalizeTarget($target);

        unset($this->macros[$target]);

        // Clear resolved cache for all macros of this target
        foreach (array_keys($this->resolvedCache) as $key) {
            if (str_starts_with($key, "{$target}::")) {
                unset($this->resolvedCache[$key]);
            }
        }

        // Clear persistent cache
        if ($this->persistentCache !== null) {
            $cacheKey = "macros:{$target}";
            $this->persistentCache->delete($cacheKey);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearAll(): void
    {
        $this->macros = [];
        $this->resolvedCache = [];

        // Clear persistent cache
        if ($this->persistentCache !== null) {
            // Note: This would require cache tag support or manual tracking
            // For now, we clear memory cache only
        }
    }

    /**
     * Normalize target class name.
     *
     * @param string $target Target class name
     * @return string Normalized class name
     */
    private function normalizeTarget(string $target): string
    {
        // Remove leading backslash
        return ltrim($target, '\\');
    }

    /**
     * Get cache key for macro.
     *
     * @param string $target Target class name
     * @param string $name Macro name
     * @return string Cache key
     */
    private function getCacheKey(string $target, string $name): string
    {
        return "macro:{$target}:{$name}";
    }

    /**
     * Clear cache for specific macro.
     *
     * @param string $target Target class name
     * @param string $name Macro name
     * @return void
     */
    private function clearCache(string $target, string $name): void
    {
        $resolvedKey = "{$target}::{$name}";
        unset($this->resolvedCache[$resolvedKey]);
    }
}
