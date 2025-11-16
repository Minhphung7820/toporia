<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Transformer;

/**
 * Resource Interface
 *
 * Contract for API resource representations.
 * Resources are the presentation layer representation of domain entities.
 *
 * Clean Architecture:
 * - Domain layer defines the contract
 * - Infrastructure layer provides implementations
 *
 * SOLID Principles:
 * - Single Responsibility: Represents entity in presentation format
 * - Open/Closed: Extensible via implementations
 *
 * Performance:
 * - Resources can be cached
 * - Lazy evaluation support
 */
interface ResourceInterface
{
    /**
     * Convert resource to array.
     *
     * @param array<string, mixed> $context Optional context
     * @return array<string, mixed> Resource as array
     */
    public function toArray(array $context = []): array;

    /**
     * Convert resource to JSON.
     *
     * @param array<string, mixed> $context Optional context
     * @return string JSON representation
     */
    public function toJson(array $context = []): string;

    /**
     * Get resource data.
     *
     * @return array<string, mixed> Resource data
     */
    public function getData(): array;
}

