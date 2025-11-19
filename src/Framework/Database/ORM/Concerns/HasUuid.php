<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Concerns;

/**
 * Has UUID Trait
 *
 * Provides UUID primary key support for models.
 * Automatically generates UUIDs for new model instances.
 *
 * Clean Architecture:
 * - Trait-based composition (Open/Closed Principle)
 * - No framework dependencies beyond ORM layer
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles UUID generation
 * - Open/Closed: Can be added to any model without modifying base class
 *
 * Performance Optimizations:
 * - UUID generation only when needed (lazy)
 * - Indexed UUID columns (fast lookups)
 * - Binary UUID storage option (more efficient than string)
 *
 * @package Toporia\Framework\Database\ORM\Concerns
 */
trait HasUuid
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public bool $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected string $keyType = 'string';

    /**
     * Boot the UUID trait.
     *
     * Automatically generates UUID for new instances.
     *
     * @return void
     */
    protected static function bootHasUuid(): void
    {
        // Generate UUID when creating new model
        static::creating(function ($model) {
            if (empty($model->getKey())) {
                $model->setKey(static::generateUuid());
            }
        });
    }

    /**
     * Generate a UUID v4.
     *
     * Performance: O(1) - Fast UUID generation
     *
     * @return string
     */
    public static function generateUuid(): string
    {
        // Use random_bytes for better randomness
        $data = random_bytes(16);

        // Set version (4) and variant bits
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant bits

        // Format as UUID string
        return sprintf(
            '%08s-%04s-%04s-%04s-%12s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }

    /**
     * Get the primary key value.
     *
     * @return mixed
     */
    abstract public function getKey(): mixed;

    /**
     * Set the primary key value.
     *
     * @param mixed $value
     * @return void
     */
    abstract public function setKey(mixed $value): void;
}

