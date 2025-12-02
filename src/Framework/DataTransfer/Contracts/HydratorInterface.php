<?php

declare(strict_types=1);

namespace Toporia\Framework\DataTransfer\Contracts;

/**
 * Interface HydratorInterface
 *
 * Contract for hydrating objects from arrays/DTOs.
 * Hydrators populate domain entities from external data sources.
 *
 * Key Features:
 * - Type-safe hydration with generics
 * - Bidirectional conversion (hydrate/extract)
 * - Strategy pattern for different hydration methods
 *
 * Performance:
 * - Reflection caching for repeated hydrations
 * - Lazy property population
 *
 * @template T The target object type
 *
 * @package Toporia\Framework\DataTransfer\Contracts
 */
interface HydratorInterface
{
    /**
     * Hydrate an object with data.
     *
     * @param array<string, mixed> $data Source data
     * @param T|class-string<T> $target Target object or class name
     * @return T Hydrated object
     */
    public function hydrate(array $data, object|string $target): object;

    /**
     * Extract data from an object.
     *
     * @param T $object Source object
     * @return array<string, mixed> Extracted data
     */
    public function extract(object $object): array;

    /**
     * Check if hydrator supports the given class.
     *
     * @param class-string $class Class name
     * @return bool
     */
    public function supports(string $class): bool;
}
