<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects\Product;

use InvalidArgumentException;

/**
 * Product Status Value Object
 *
 * Represents product status with business rules.
 * Immutable enum-like pattern.
 *
 * Clean Architecture:
 * - Domain layer Value Object
 * - Encapsulates status business rules
 * - No framework dependencies
 *
 * SOLID Principles:
 * - Single Responsibility: Product status representation
 * - Immutability: Cannot change after creation
 */
final class ProductStatus
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const DRAFT = 'draft';
    public const ARCHIVED = 'archived';

    private const VALID_STATUSES = [
        self::ACTIVE,
        self::INACTIVE,
        self::DRAFT,
        self::ARCHIVED,
    ];

    /**
     * @param string $value Status value
     */
    public function __construct(
        public readonly string $value
    ) {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                "Invalid product status: {$value}. Valid statuses: " . implode(', ', self::VALID_STATUSES)
            );
        }
    }

    /**
     * Create Active status.
     *
     * @return self Active status
     */
    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    /**
     * Create Inactive status.
     *
     * @return self Inactive status
     */
    public static function inactive(): self
    {
        return new self(self::INACTIVE);
    }

    /**
     * Create Draft status.
     *
     * @return self Draft status
     */
    public static function draft(): self
    {
        return new self(self::DRAFT);
    }

    /**
     * Create Archived status.
     *
     * @return self Archived status
     */
    public static function archived(): self
    {
        return new self(self::ARCHIVED);
    }

    /**
     * Create from string.
     *
     * @param string $status Status string
     * @return self Status instance
     */
    public static function fromString(string $status): self
    {
        return new self($status);
    }

    /**
     * Check if product is active.
     *
     * @return bool True if active
     */
    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    /**
     * Check if product is inactive.
     *
     * @return bool True if inactive
     */
    public function isInactive(): bool
    {
        return $this->value === self::INACTIVE;
    }

    /**
     * Check if product is draft.
     *
     * @return bool True if draft
     */
    public function isDraft(): bool
    {
        return $this->value === self::DRAFT;
    }

    /**
     * Check if product is archived.
     *
     * @return bool True if archived
     */
    public function isArchived(): bool
    {
        return $this->value === self::ARCHIVED;
    }

    /**
     * Check if product can be ordered (active status only).
     *
     * @return bool True if can be ordered
     */
    public function canBeOrdered(): bool
    {
        return $this->isActive();
    }

    /**
     * Check if product is visible to customers.
     *
     * @return bool True if visible
     */
    public function isVisibleToCustomers(): bool
    {
        return $this->isActive();
    }

    /**
     * Check if product can be edited.
     *
     * @return bool True if can be edited
     */
    public function canBeEdited(): bool
    {
        return !$this->isArchived();
    }

    /**
     * Check if equals another status.
     *
     * @param ProductStatus $other Status to compare
     * @return bool True if equal
     */
    public function equals(ProductStatus $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Get all valid statuses.
     *
     * @return array<string> Valid status values
     */
    public static function all(): array
    {
        return self::VALID_STATUSES;
    }

    /**
     * Convert to string.
     *
     * @return string Status value
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Get human-readable label.
     *
     * @return string Status label
     */
    public function getLabel(): string
    {
        return match($this->value) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::DRAFT => 'Draft',
            self::ARCHIVED => 'Archived',
        };
    }
}
