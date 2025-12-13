<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Transformer;

/**
 * Transformer Interface
 *
 * Contract for transforming domain entities to presentation layer formats.
 * Separates domain logic from presentation concerns.
 *
 * Clean Architecture:
 * - Domain layer defines the contract
 * - Infrastructure layer provides implementations
 *
 * SOLID Principles:
 * - Single Responsibility: Transforms entities to resources
 * - Open/Closed: Extensible via implementations
 * - Dependency Inversion: Depends on abstraction
 *
 * Performance:
 * - Transformers can be cached
 * - Lazy evaluation support
 * - Batch transformation support
 *
 * @template TEntity Domain entity type
 * @template TResource Resource type (array, DTO, etc.)
 */
interface TransformerInterface
{
    /**
     * Transform a single entity to resource.
     *
     * @param TEntity $entity Domain entity
     * @param array<string, mixed> $context Optional context (user, permissions, etc.)
     * @return TResource Transformed resource
     */
    public function transform(mixed $entity, array $context = []): mixed;

    /**
     * Transform a collection of entities to resources.
     *
     * @param iterable<TEntity> $entities Collection of entities
     * @param array<string, mixed> $context Optional context
     * @return array<TResource> Array of transformed resources
     */
    public function transformCollection(iterable $entities, array $context = []): array;

    /**
     * Get the entity class this transformer handles.
     *
     * @return class-string<TEntity> Entity class name
     */
    public function getEntityClass(): string;

    /**
     * Check if transformer can handle the given entity.
     *
     * @param mixed $entity Entity to check
     * @return bool True if transformer can handle
     */
    public function canTransform(mixed $entity): bool;
}

