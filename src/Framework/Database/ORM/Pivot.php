<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM;

/**
 * Pivot Model
 *
 * Represents a pivot table record in many-to-many relationships.
 * Provides a clean, reusable implementation instead of anonymous classes.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  ORM
 */
class Pivot
{
    /**
     * @param array<string, mixed> $attributes Pivot attributes
     * @param string $table Pivot table name
     * @param bool $exists Whether the pivot exists in database
     */
    public function __construct(
        protected array $attributes = [],
        protected string $table = '',
        protected bool $exists = false
    ) {}

    /**
     * Get an attribute value.
     */
    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Set an attribute value.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Check if an attribute is set.
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Get an attribute by key.
     */
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Set an attribute by key.
     */
    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Get all attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get the pivot table name.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Check if the pivot record exists in database.
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
