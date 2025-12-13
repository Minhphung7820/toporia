<?php

declare(strict_types=1);

namespace App\Infrastructure\Transformer;

use App\Domain\Contracts\Transformer\TransformerInterface;
use Toporia\Framework\Container\Contracts\ContainerInterface;

/**
 * Transformer Manager
 *
 * Manages transformer registration and resolution.
 * Provides automatic transformer discovery and caching.
 *
 * Clean Architecture:
 * - Infrastructure layer service
 * - Manages transformer instances
 *
 * SOLID Principles:
 * - Single Responsibility: Manages transformers
 * - Open/Closed: Extensible via registration
 * - Dependency Inversion: Depends on TransformerInterface abstraction
 *
 * Performance:
 * - O(1) transformer resolution
 * - Cached transformer instances
 */
final class TransformerManager
{
    /**
     * @var array<string, class-string<TransformerInterface>> Entity class => Transformer class mapping
     */
    private array $mapping = [];

    /**
     * @var array<string, TransformerInterface> Cached transformer instances
     */
    private array $instances = [];

    /**
     * @param ContainerInterface $container Dependency injection container
     */
    public function __construct(
        private readonly ContainerInterface $container
    ) {
        $this->registerDefaultTransformers();
    }

    /**
     * Register a transformer for an entity class.
     *
     * @param class-string $entityClass Entity class
     * @param class-string<TransformerInterface> $transformerClass Transformer class
     * @return void
     */
    public function register(string $entityClass, string $transformerClass): void
    {
        $this->mapping[$entityClass] = $transformerClass;
    }

    /**
     * Get transformer for entity.
     *
     * @param mixed $entity Entity instance
     * @return TransformerInterface Transformer instance
     * @throws \RuntimeException If transformer not found
     */
    public function getTransformer(mixed $entity): TransformerInterface
    {
        if (!is_object($entity)) {
            throw new \InvalidArgumentException('Entity must be an object');
        }

        $entityClass = get_class($entity);

        // Check cache first
        if (isset($this->instances[$entityClass])) {
            return $this->instances[$entityClass];
        }

        // Get transformer class from mapping
        $transformerClass = $this->mapping[$entityClass] ?? null;
        if ($transformerClass === null) {
            throw new \RuntimeException("No transformer registered for entity: {$entityClass}");
        }

        // Get transformer from container
        $transformer = $this->container->get($transformerClass);
        $this->instances[$entityClass] = $transformer; // Cache instance

        return $transformer;
    }

    /**
     * Check if transformer exists for entity.
     *
     * @param mixed $entity Entity instance
     * @return bool True if transformer exists
     */
    public function hasTransformer(mixed $entity): bool
    {
        if (!is_object($entity)) {
            return false;
        }

        $entityClass = get_class($entity);
        return isset($this->mapping[$entityClass]) || isset($this->instances[$entityClass]);
    }

    /**
     * Register default transformers.
     *
     * Developers should register their transformers here or via register() method.
     *
     * @return void
     */
    private function registerDefaultTransformers(): void
    {
        // Example:
        // $this->mapping = [
        //     \App\Domain\User\User::class => UserTransformer::class,
        //     \App\Domain\Product\Product::class => ProductTransformer::class,
        // ];

        $this->mapping = [];
    }
}
