<?php

declare(strict_types=1);

namespace Toporia\Framework\Testing\Concerns;

/**
 * Cache Testing Trait
 *
 * Provides utilities for cache testing.
 *
 * Performance:
 * - O(1) cache clearing
 * - Fast cache assertions
 */
trait InteractsWithCache
{
    /**
     * Cache storage (in-memory for testing).
     *
     * @var array<string, mixed>
     */
    protected array $cache = [];

    /**
     * Clear cache.
     *
     * Performance: O(1)
     */
    protected function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Assert that a cache key exists.
     *
     * Performance: O(1)
     */
    protected function assertCacheHas(string $key): void
    {
        $this->assertArrayHasKey($key, $this->cache, "Cache key {$key} does not exist");
    }

    /**
     * Assert that a cache key doesn't exist.
     *
     * Performance: O(1)
     */
    protected function assertCacheMissing(string $key): void
    {
        $this->assertArrayNotHasKey($key, $this->cache, "Cache key {$key} unexpectedly exists");
    }

    /**
     * Assert cache value.
     *
     * Performance: O(1)
     */
    protected function assertCacheEquals(mixed $expected, string $key): void
    {
        $this->assertArrayHasKey($key, $this->cache, "Cache key {$key} does not exist");
        $this->assertEquals($expected, $this->cache[$key], "Cache value mismatch for key {$key}");
    }

    /**
     * Cleanup cache after test.
     */
    protected function tearDownCache(): void
    {
        $this->clearCache();
    }
}
