<?php

declare(strict_types=1);

namespace App\Application\Services\Blog;

use Toporia\Framework\Cache\Contracts\CacheInterface;

/**
 * Blog Cache Service
 *
 * Centralized cache invalidation for blog-related data.
 * Used by PostObserver to clear cache when posts change.
 *
 * Design Principles:
 * - Single Responsibility: Only handles blog cache invalidation
 * - DRY: Centralized cache key configuration
 * - Performance: Uses batch delete (deleteMultiple) when possible
 * - Resilient: Non-blocking errors, debounce mechanism
 *
 * Cache Keys Managed:
 * - blog:featured_posts:{limit} - Featured posts list
 * - blog:latest_posts:{limit} - Latest posts list
 * - blog:popular_posts:{limit} - Popular posts list
 * - blog:stats - Blog statistics
 *
 * Usage:
 *   BlogCacheService::invalidateAll();           // Clear all blog cache
 *   BlogCacheService::invalidateFeaturedPosts(); // Clear only featured
 *   BlogCacheService::invalidateStats();         // Clear only stats
 */
final class BlogCacheService
{
    /**
     * Cache key prefixes with their max limit values.
     * Format: 'prefix' => maxLimit
     */
    private const CACHE_KEY_CONFIG = [
        'blog:featured_posts' => 15,
        'blog:latest_posts' => 15,
        'blog:popular_posts' => 15,
    ];

    /**
     * Static cache keys (without suffix).
     */
    private const STATIC_CACHE_KEYS = [
        'blog:stats',
    ];

    /**
     * Debounce flag to prevent duplicate invalidation in same request.
     */
    private static bool $invalidatedThisRequest = false;

    /**
     * Invalidate all blog-related cache entries.
     *
     * Uses batch deletion for better performance.
     * Safe to call multiple times - debounce prevents duplicate work.
     *
     * @param bool $force Force invalidation even if already done this request
     */
    public static function invalidateAll(bool $force = false): void
    {
        // Debounce: Skip if already invalidated this request
        if (self::$invalidatedThisRequest && !$force) {
            return;
        }

        try {
            $cache = self::getCache();
            $keys = self::buildAllCacheKeys();

            // Use batch delete for better performance
            $cache->deleteMultiple($keys);

            self::$invalidatedThisRequest = true;
        } catch (\Throwable $e) {
            self::logError('Failed to invalidate all cache', $e);
        }
    }

    /**
     * Invalidate featured posts cache only.
     */
    public static function invalidateFeaturedPosts(): void
    {
        self::invalidateByPrefix('blog:featured_posts');
    }

    /**
     * Invalidate latest posts cache only.
     */
    public static function invalidateLatestPosts(): void
    {
        self::invalidateByPrefix('blog:latest_posts');
    }

    /**
     * Invalidate popular posts cache only.
     */
    public static function invalidatePopularPosts(): void
    {
        self::invalidateByPrefix('blog:popular_posts');
    }

    /**
     * Invalidate stats cache only.
     */
    public static function invalidateStats(): void
    {
        try {
            self::getCache()->delete('blog:stats');
        } catch (\Throwable $e) {
            self::logError('Failed to invalidate stats cache', $e);
        }
    }

    /**
     * Reset debounce flag.
     *
     * Useful for:
     * - Testing (reset between tests)
     * - Long-running processes (queue workers)
     * - CLI commands that process multiple batches
     */
    public static function resetDebounce(): void
    {
        self::$invalidatedThisRequest = false;
    }

    /**
     * Check if cache was already invalidated this request.
     */
    public static function wasInvalidated(): bool
    {
        return self::$invalidatedThisRequest;
    }

    /**
     * Get all cache keys that would be invalidated.
     *
     * Useful for debugging and testing.
     *
     * @return array<string>
     */
    public static function getCacheKeys(): array
    {
        return self::buildAllCacheKeys();
    }

    /**
     * Invalidate cache keys by prefix.
     */
    private static function invalidateByPrefix(string $prefix): void
    {
        try {
            $cache = self::getCache();
            $maxLimit = self::CACHE_KEY_CONFIG[$prefix] ?? 15;
            $keys = self::buildKeysForPrefix($prefix, $maxLimit);

            $cache->deleteMultiple($keys);
        } catch (\Throwable $e) {
            self::logError("Failed to invalidate {$prefix} cache", $e);
        }
    }

    /**
     * Build all cache keys for invalidation.
     *
     * @return array<string>
     */
    private static function buildAllCacheKeys(): array
    {
        $keys = [];

        // Dynamic keys with limit suffix
        foreach (self::CACHE_KEY_CONFIG as $prefix => $maxLimit) {
            $keys = array_merge($keys, self::buildKeysForPrefix($prefix, $maxLimit));
        }

        // Static keys
        foreach (self::STATIC_CACHE_KEYS as $key) {
            $keys[] = $key;
        }

        return $keys;
    }

    /**
     * Build cache keys for a specific prefix.
     *
     * @return array<string>
     */
    private static function buildKeysForPrefix(string $prefix, int $maxLimit): array
    {
        $keys = [];
        for ($i = 1; $i <= $maxLimit; $i++) {
            $keys[] = "{$prefix}:{$i}";
        }
        return $keys;
    }

    /**
     * Get cache instance.
     */
    private static function getCache(): CacheInterface
    {
        return cache();
    }

    /**
     * Log error without throwing.
     */
    private static function logError(string $message, \Throwable $e): void
    {
        log_error("BlogCacheService: {$message}", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
