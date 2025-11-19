<?php

declare(strict_types=1);

namespace Toporia\Framework\Validation;

/**
 * Validation Data
 *
 * Immutable container for all data being validated.
 * Provides efficient access to nested data via dot notation.
 *
 * Clean Architecture:
 * - Value Object: Immutable data container
 * - No framework dependencies
 *
 * Performance:
 * - Lazy dot notation parsing (cached after first access)
 * - O(1) direct key access
 * - O(n) dot notation access (n = depth)
 *
 * @package Toporia\Framework\Validation
 */
final class ValidationData
{
    /**
     * @param array<string, mixed> $data Raw validation data
     * @param array<string, mixed> $cached Cache for dot notation lookups
     */
    private function __construct(
        private readonly array $data,
        private array $cached = []
    ) {}

    /**
     * Create ValidationData from array.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Get value by key (supports dot notation).
     *
     * Performance: O(1) for direct keys, O(n) for dot notation (cached)
     *
     * @param string $key Key or dot notation path (e.g., "user.email")
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Direct key access (O(1))
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        // Check cache first
        if (isset($this->cached[$key])) {
            return $this->cached[$key];
        }

        // Dot notation access (O(n) where n = depth)
        if (str_contains($key, '.')) {
            $value = $this->getNested($key, $default);
            // Cache result
            $this->cached[$key] = $value;
            return $value;
        }

        return $default;
    }

    /**
     * Check if key exists (supports dot notation).
     *
     * @param string $key Key or dot notation path
     * @return bool
     */
    public function has(string $key): bool
    {
        if (isset($this->data[$key])) {
            return true;
        }

        if (str_contains($key, '.')) {
            return $this->getNested($key, '__NOT_FOUND__') !== '__NOT_FOUND__';
        }

        return false;
    }

    /**
     * Get all data as array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Get nested value using dot notation.
     *
     * @param string $key Dot notation path (e.g., "user.profile.email")
     * @param mixed $default Default value
     * @return mixed
     */
    private function getNested(string $key, mixed $default): mixed
    {
        $segments = explode('.', $key);
        $value = $this->data;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
