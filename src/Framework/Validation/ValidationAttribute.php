<?php

declare(strict_types=1);

namespace Toporia\Framework\Validation;

/**
 * Validation Attribute
 *
 * Value object representing an attribute being validated.
 * Provides metadata about the attribute (name, display name, etc.).
 *
 * Clean Architecture:
 * - Value Object: Immutable data container
 * - No framework dependencies
 *
 * Performance:
 * - Immutable (can be cached/shared)
 * - O(1) property access
 *
 * @package Toporia\Framework\Validation
 */
final readonly class ValidationAttribute
{
    /**
     * @param string $name Attribute name (e.g., "email", "user.email")
     * @param string $displayName Human-readable display name (e.g., "Email Address")
     */
    public function __construct(
        public string $name,
        public string $displayName
    ) {
    }

    /**
     * Create from attribute name.
     *
     * Automatically generates display name from attribute name.
     *
     * @param string $name Attribute name
     * @return self
     */
    public static function fromName(string $name): self
    {
        $displayName = str_replace(['_', '.'], ' ', $name);
        $displayName = ucwords($displayName);
        return new self($name, $displayName);
    }

    /**
     * Get attribute name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get display name.
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }
}

