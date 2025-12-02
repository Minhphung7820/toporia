<?php

declare(strict_types=1);

namespace Toporia\Framework\DataTransfer\Contracts;

/**
 * Interface DTOInterface
 *
 * Base contract for all Data Transfer Objects.
 * DTOs are immutable value objects used to transfer data between layers.
 *
 * Key Principles:
 * - Immutability: DTOs should not be modified after creation
 * - Type Safety: All properties should be strongly typed
 * - Validation: DTOs may carry validation rules
 * - Serialization: DTOs should be easily serializable
 *
 * Performance:
 * - O(1) property access
 * - Zero overhead serialization with toArray()
 *
 * @package Toporia\Framework\DataTransfer\Contracts
 */
interface DTOInterface
{
    /**
     * Create DTO from array data.
     *
     * @param array<string, mixed> $data
     * @return static
     */
    public static function fromArray(array $data): static;

    /**
     * Convert DTO to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Check if DTO has a property.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Get a property value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;
}
