<?php

declare(strict_types=1);

namespace Toporia\Framework\DataTransfer\Contracts;

/**
 * Interface TransformerInterface
 *
 * Contract for transforming entities to resources/DTOs.
 * Transformers convert domain entities to presentation-layer representations.
 *
 * Key Features:
 * - Type-safe transformation with generics
 * - Context-aware transformation
 * - Batch transformation support
 * - Include/exclude relations support
 *
 * @template TEntity The source entity type
 * @template TResource The target resource type
 *
 * @package Toporia\Framework\DataTransfer\Contracts
 */
interface TransformerInterface
{
    /**
     * Transform a single entity to resource.
     *
     * @param TEntity $entity Source entity
     * @param array<string, mixed> $context Transformation context
     * @return TResource Transformed resource
     */
    public function transform(mixed $entity, array $context = []): mixed;

    /**
     * Transform a collection of entities.
     *
     * @param iterable<TEntity> $entities Source entities
     * @param array<string, mixed> $context Transformation context
     * @return array<TResource> Transformed resources
     */
    public function transformCollection(iterable $entities, array $context = []): array;

    /**
     * Check if transformer can handle the given entity.
     *
     * @param mixed $entity Entity to check
     * @return bool
     */
    public function supports(mixed $entity): bool;

    /**
     * Get available includes for this transformer.
     *
     * @return array<string>
     */
    public function getAvailableIncludes(): array;

    /**
     * Get default includes.
     *
     * @return array<string>
     */
    public function getDefaultIncludes(): array;
}
