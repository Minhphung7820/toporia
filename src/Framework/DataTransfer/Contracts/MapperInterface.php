<?php

declare(strict_types=1);

namespace Toporia\Framework\DataTransfer\Contracts;

/**
 * Interface MapperInterface
 *
 * Contract for mapping between different object types.
 * Mappers convert objects from one type to another.
 *
 * Use Cases:
 * - Entity to DTO conversion
 * - DTO to Entity conversion
 * - External API response to internal models
 * - Database records to domain entities
 *
 * @template TSource The source type
 * @template TTarget The target type
 *
 * @package Toporia\Framework\DataTransfer\Contracts
 */
interface MapperInterface
{
    /**
     * Map source object to target type.
     *
     * @param TSource $source Source object
     * @param array<string, mixed> $context Mapping context
     * @return TTarget Mapped object
     */
    public function map(mixed $source, array $context = []): mixed;

    /**
     * Map collection of source objects.
     *
     * @param iterable<TSource> $sources Source objects
     * @param array<string, mixed> $context Mapping context
     * @return array<TTarget> Mapped objects
     */
    public function mapCollection(iterable $sources, array $context = []): array;

    /**
     * Get source type this mapper handles.
     *
     * @return class-string<TSource>
     */
    public function getSourceType(): string;

    /**
     * Get target type this mapper produces.
     *
     * @return class-string<TTarget>
     */
    public function getTargetType(): string;
}
