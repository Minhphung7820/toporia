<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Transformer;

/**
 * Resource Collection Interface
 *
 * Contract for collections of resources.
 * Provides pagination, metadata, and collection operations.
 *
 * Clean Architecture:
 * - Domain layer defines the contract
 * - Infrastructure layer provides implementations
 *
 * SOLID Principles:
 * - Single Responsibility: Manages collection of resources
 * - Open/Closed: Extensible via implementations
 */
interface ResourceCollectionInterface extends ResourceInterface
{
    /**
     * Get resources in collection.
     *
     * @return array<ResourceInterface> Array of resources
     */
    public function getResources(): array;

    /**
     * Get collection metadata.
     *
     * @return array<string, mixed> Metadata (count, pagination, etc.)
     */
    public function getMeta(): array;

    /**
     * Check if collection is empty.
     *
     * @return bool True if empty
     */
    public function isEmpty(): bool;

    /**
     * Get collection count.
     *
     * @return int Number of resources
     */
    public function count(): int;
}
