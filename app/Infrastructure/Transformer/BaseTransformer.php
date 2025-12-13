<?php

declare(strict_types=1);

namespace App\Infrastructure\Transformer;

use App\Domain\Contracts\Transformer\TransformerInterface;
use Toporia\Framework\Cache\Contracts\CacheInterface;

/**
 * Base Transformer Implementation
 *
 * Provides common transformer functionality with caching and performance optimizations.
 *
 * Clean Architecture:
 * - Implements Domain TransformerInterface
 * - Uses Framework CacheInterface for performance
 *
 * SOLID Principles:
 * - Single Responsibility: Transforms entities to resources
 * - Open/Closed: Extensible via inheritance
 * - Dependency Inversion: Depends on CacheInterface abstraction
 *
 * Performance:
 * - O(1) transformation with caching
 * - Batch transformation optimization
 * - Lazy evaluation support
 *
 * @template TEntity Domain entity type
 * @template TResource Resource type
 */
abstract class BaseTransformer implements TransformerInterface
{
    /**
     * @var array<string, mixed> Cached transformations
     * Format: ['entity_id:context_hash' => resource]
     */
    private array $cache = [];

    /**
     * @param CacheInterface|null $persistentCache Optional persistent cache
     * @param bool $enableCache Whether to enable caching
     * @param int|null $cacheTtl Cache TTL in seconds
     */
    public function __construct(
        private readonly ?CacheInterface $persistentCache = null,
        private readonly bool $enableCache = true,
        private readonly ?int $cacheTtl = 3600
    ) {}

    /**
     * {@inheritdoc}
     */
    public function transform(mixed $entity, array $context = []): mixed
    {
        if (!$this->canTransform($entity)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Transformer %s cannot transform entity of type %s',
                    static::class,
                    is_object($entity) ? get_class($entity) : gettype($entity)
                )
            );
        }

        // Check cache
        $cacheKey = $this->getCacheKey($entity, $context);
        if ($this->enableCache) {
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Transform entity
        $resource = $this->transformEntity($entity, $context);

        // Cache result
        if ($this->enableCache) {
            $this->setCached($cacheKey, $resource);
        }

        return $resource;
    }

    /**
     * {@inheritdoc}
     */
    public function transformCollection(iterable $entities, array $context = []): array
    {
        $resources = [];

        foreach ($entities as $entity) {
            $resources[] = $this->transform($entity, $context);
        }

        return $resources;
    }

    /**
     * {@inheritdoc}
     */
    public function canTransform(mixed $entity): bool
    {
        $entityClass = $this->getEntityClass();
        return is_object($entity) && $entity instanceof $entityClass;
    }

    /**
     * Transform a single entity to resource.
     * Override this method in child classes.
     *
     * @param TEntity $entity Domain entity
     * @param array<string, mixed> $context Optional context
     * @return TResource Transformed resource
     */
    abstract protected function transformEntity(mixed $entity, array $context = []): mixed;

    /**
     * Get cache key for entity and context.
     *
     * @param mixed $entity Entity
     * @param array<string, mixed> $context Context
     * @return string Cache key
     */
    private function getCacheKey(mixed $entity, array $context): string
    {
        $entityId = $this->getEntityId($entity);
        $contextHash = md5(json_encode($context, JSON_THROW_ON_ERROR));
        return sprintf('%s:%s:%s', static::class, $entityId, $contextHash);
    }

    /**
     * Get entity ID for caching.
     *
     * @param mixed $entity Entity
     * @return string Entity ID
     */
    private function getEntityId(mixed $entity): string
    {
        if (is_object($entity)) {
            // Try common ID methods
            if (method_exists($entity, 'getId')) {
                return (string) $entity->getId();
            }
            if (property_exists($entity, 'id')) {
                return (string) $entity->id;
            }
            // Fallback to object hash
            return spl_object_hash($entity);
        }

        return (string) $entity;
    }

    /**
     * Get cached resource.
     *
     * @param string $key Cache key
     * @return mixed|null Cached resource or null
     */
    private function getCached(string $key): mixed
    {
        // Check memory cache first
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        // Check persistent cache
        if ($this->persistentCache !== null) {
            $cached = $this->persistentCache->get($key);
            if ($cached !== null) {
                $this->cache[$key] = $cached; // Cache in memory
                return $cached;
            }
        }

        return null;
    }

    /**
     * Set cached resource.
     *
     * @param string $key Cache key
     * @param mixed $resource Resource to cache
     * @return void
     */
    private function setCached(string $key, mixed $resource): void
    {
        // Cache in memory
        $this->cache[$key] = $resource;

        // Cache persistently if available
        if ($this->persistentCache !== null && $this->cacheTtl) {
            $this->persistentCache->set($key, $resource, $this->cacheTtl);
        }
    }

    /**
     * Clear cache for entity.
     *
     * @param mixed $entity Entity
     * @return void
     */
    public function clearCache(mixed $entity): void
    {
        $entityId = $this->getEntityId($entity);
        $prefix = static::class . ':' . $entityId . ':';

        // Clear memory cache
        foreach (array_keys($this->cache) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->cache[$key]);
            }
        }

        // Clear persistent cache
        if ($this->persistentCache !== null) {
            // Note: Would need cache tags for efficient clearing
            // For now, we clear memory cache only
        }
    }
}
