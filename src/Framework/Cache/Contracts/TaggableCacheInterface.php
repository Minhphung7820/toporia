<?php

declare(strict_types=1);

namespace Toporia\Framework\Cache\Contracts;

/**
 * Taggable Cache Interface
 *
 * Extends CacheInterface with tag support for cache organization.
 * Allows grouping related cache entries and clearing by tag.
 *
 * Performance:
 * - Tag operations: O(N) where N = keys in tag
 * - Tag clearing: O(N*M) where N = tags, M = keys per tag
 *
 * Clean Architecture:
 * - Interface Segregation: Separate interface for tag support
 * - Dependency Inversion: Framework depends on abstraction
 */
interface TaggableCacheInterface extends CacheInterface
{
    /**
     * Tag a cache key.
     *
     * @param string|array $tags Tag name(s)
     * @return TaggableCacheInterface Tagged cache instance
     */
    public function tags(string|array $tags): TaggableCacheInterface;

    /**
     * Clear all cache entries with given tags.
     *
     * @param string|array $tags Tag name(s)
     * @return bool True on success
     */
    public function flushTags(string|array $tags): bool;
}

