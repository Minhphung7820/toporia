<?php

declare(strict_types=1);

namespace Toporia\Framework\Cache;

use Toporia\Framework\Cache\Contracts\CacheInterface;
/**
 * Tagged Cache
 *
 * Provides tag-based cache invalidation for grouped cache entries.
 * Laravel-compatible API for cache tagging.
 *
 * Features:
 * - Group related cache entries with tags
 * - Flush all entries with specific tags
 * - Efficient tag-based invalidation
 *
 * Performance:
 * - O(1) tag lookup
 * - O(N) flush where N = entries with tag
 *
 * Example:
 * ```php
 * $cache->tags(['products', 'featured'])->put('featured_products', $data, 3600);
 * $cache->tags(['products'])->flush(); // Removes all product-related cache
 * ```
 *
 * @package Toporia\Framework\Cache
 */
final class TaggedCache implements CacheInterface
{
    /**
     * @var array<string> Cache tags
     */
    private array $tags;

    /**
     * @param CacheInterface $store Underlying cache store
     * @param array<string> $tags Cache tags
     */
    public function __construct(
        private readonly CacheInterface $store,
        array $tags = []
    ) {
        $this->tags = $tags;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store->get($this->taggedKey($key), $default);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $taggedKey = $this->taggedKey($key);

        // Store the value
        $stored = $this->store->set($taggedKey, $value, $ttl);

        // Track this key under tags
        if ($stored) {
            $this->trackKeyUnderTags($key, $ttl);
        }

        return $stored;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->store->has($this->taggedKey($key));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return $this->store->delete($this->taggedKey($key));
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        foreach ($this->tags as $tag) {
            $this->flushTag($tag);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set((string) $key, $value, $ttl)) {
                return false;
            }
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function increment(string $key, int $value = 1): int|false
    {
        return $this->store->increment($this->taggedKey($key), $value);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->store->decrement($this->taggedKey($key), $value);
    }

    /**
     * Laravel-style helper: Store cache entry (alias for set()).
     */
    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->set($key, $value, $ttl);
    }

    /**
     * Laravel-style helper: Delete cache entry (alias for delete()).
     */
    public function forget(string $key): bool
    {
        return $this->delete($key);
    }

    /**
     * Laravel-style helper: Flush all tagged entries (alias for clear()).
     */
    public function flush(): bool
    {
        return $this->clear();
    }

    /**
     * Laravel-style helper: Get and delete cache entry.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->delete($key);
        return $value;
    }

    /**
     * Laravel-style helper: Store cache entry forever (no expiration).
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, null);
    }

    /**
     * Get tagged cache key.
     *
     * @param string $key Original key
     * @return string Tagged key
     */
    private function taggedKey(string $key): string
    {
        if (empty($this->tags)) {
            return $key;
        }

        // Create namespace from tags
        $namespace = $this->getTagNamespace();

        return "{$namespace}:{$key}";
    }

    /**
     * Get tag namespace (versioned).
     *
     * @return string Tag namespace
     */
    private function getTagNamespace(): string
    {
        $parts = [];

        foreach ($this->tags as $tag) {
            $parts[] = $this->getTagVersion($tag);
        }

        return implode('|', $parts);
    }

    /**
     * Get tag version (for cache invalidation).
     *
     * @param string $tag Tag name
     * @return string Tag version
     */
    private function getTagVersion(string $tag): string
    {
        $versionKey = "tag:{$tag}:version";
        $version = $this->store->get($versionKey);

        if ($version === null) {
            $version = $this->resetTagVersion($tag);
        }

        return "{$tag}:{$version}";
    }

    /**
     * Reset tag version (invalidates all entries with this tag).
     *
     * @param string $tag Tag name
     * @return string New version
     */
    private function resetTagVersion(string $tag): string
    {
        $version = (string) time();
        $versionKey = "tag:{$tag}:version";

        // Store version forever (no TTL)
        $this->store->set($versionKey, $version, null);

        return $version;
    }

    /**
     * Flush all entries with specific tag.
     *
     * @param string $tag Tag name
     * @return void
     */
    private function flushTag(string $tag): void
    {
        // Reset tag version (makes all old entries inaccessible)
        $this->resetTagVersion($tag);
    }

    /**
     * Track key under tags (for garbage collection).
     *
     * @param string $key Cache key
     * @param int|null $ttl Time to live
     * @return void
     */
    private function trackKeyUnderTags(string $key, ?int $ttl): void
    {
        foreach ($this->tags as $tag) {
            $trackingKey = "tag:{$tag}:keys";

            // Get existing keys
            $keys = $this->store->get($trackingKey, []);
            if (!is_array($keys)) {
                $keys = [];
            }

            // Add new key
            $keys[] = $key;
            $keys = array_unique($keys);

            // Store back (with same TTL as tag version)
            $this->store->set($trackingKey, $keys, $ttl);
        }
    }
}
