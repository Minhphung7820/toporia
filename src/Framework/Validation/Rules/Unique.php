<?php

declare(strict_types=1);

namespace Toporia\Framework\Validation\Rules;

use Toporia\Framework\Validation\Contracts\DataAwareRuleInterface;
use Toporia\Framework\Validation\Contracts\RuleInterface;
use Toporia\Framework\Validation\ValidationAttribute;
use Toporia\Framework\Validation\ValidationData;

/**
 * Unique Rule
 *
 * Validates that a value is unique in a database table.
 * Supports multiple ignore conditions for update scenarios.
 *
 * Clean Architecture:
 * - Single Responsibility: Only validates uniqueness
 * - Dependency Inversion: Works with any database connection
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles unique validation
 * - Open/Closed: Extensible via ignore conditions
 * - Dependency Inversion: Uses database abstraction
 *
 * Performance Optimizations:
 * - Single indexed query
 * - Prepared statements
 * - Batch validation for arrays
 *
 * Usage Examples:
 * ```php
 * // Simple unique
 * new Unique('users', 'email')
 *
 * // With single ignore condition
 * new Unique('users', 'email', ['id' => 1])
 *
 * // With multiple ignore conditions
 * new Unique('users', 'email', ['id' => 1, 'status' => 'deleted'])
 *
 * // In array validation
 * 'emails.*' => [new Unique('users', 'email')]
 * ```
 *
 * @package Toporia\Framework\Validation\Rules
 */
final class Unique implements RuleInterface, DataAwareRuleInterface
{
    /**
     * Database table name.
     *
     * @var string
     */
    private string $table;

    /**
     * Column name to check.
     *
     * @var string
     */
    private string $column;

    /**
     * Ignore conditions: ['column' => 'value', ...]
     *
     * @var array<string, mixed>
     */
    private array $ignoreConditions;

    /**
     * Validation data (for accessing other fields).
     *
     * @var ValidationData|null
     */
    private ?ValidationData $data = null;

    /**
     * Create a new unique rule instance.
     *
     * @param string $table Database table name
     * @param string $column Column name to check
     * @param array<string, mixed> $ignoreConditions Ignore conditions ['column' => 'value', ...]
     */
    public function __construct(string $table, string $column, array $ignoreConditions = [])
    {
        $this->table = $table;
        $this->column = $column;
        $this->ignoreConditions = $ignoreConditions;
    }

    /**
     * Set validation data.
     *
     * @param ValidationData $data Validation data
     * @return void
     */
    public function setData(ValidationData $data): void
    {
        $this->data = $data;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute Attribute name
     * @param mixed $value Attribute value
     * @return bool
     * @throws \RuntimeException If database not available
     * @throws \InvalidArgumentException If parameters invalid
     */
    public function passes(string $attribute, mixed $value): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        // Handle array values (for array validation)
        if (is_array($value)) {
            return $this->validateArray($value);
        }

        return $this->validateValue($value);
    }

    /**
     * Validate a single value.
     *
     * @param mixed $value Value to validate
     * @return bool
     */
    private function validateValue(mixed $value): bool
    {
        $db = $this->getConnection();

        if (method_exists($db, 'table')) {
            // QueryBuilder
            $query = $db->table($this->table)->where($this->column, $value);

            // Apply ignore conditions
            foreach ($this->ignoreConditions as $column => $ignoreValue) {
                // Support dynamic values from validation data
                $actualValue = $this->resolveIgnoreValue($column, $ignoreValue);
                $query->where($column, '!=', $actualValue);
            }

            return !$query->exists();
        }

        if (method_exists($db, 'prepare') && method_exists($db, 'execute')) {
            // PDO
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$this->column} = ?";
            $params = [$value];

            foreach ($this->ignoreConditions as $column => $ignoreValue) {
                $actualValue = $this->resolveIgnoreValue($column, $ignoreValue);
                $sql .= " AND {$column} != ?";
                $params[] = $actualValue;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn() === 0;
        }

        throw new \RuntimeException('Database connection must be PDO or QueryBuilder instance');
    }

    /**
     * Validate array of values.
     *
     * @param array<mixed> $values Array of values
     * @return bool
     */
    private function validateArray(array $values): bool
    {
        // Check if all values are unique in the array itself
        $uniqueValues = array_unique($values);
        if (count($uniqueValues) !== count($values)) {
            return false;
        }

        // Check each value against database
        foreach ($values as $value) {
            if (!$this->validateValue($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve ignore value (supports dynamic values from validation data).
     *
     * @param string $column Column name
     * @param mixed $value Ignore value (can be a field reference like "id" or actual value)
     * @return mixed
     */
    private function resolveIgnoreValue(string $column, mixed $value): mixed
    {
        // If value is a string and exists in validation data, use it
        if (is_string($value) && $this->data !== null && $this->data->has($value)) {
            return $this->data->get($value);
        }

        return $value;
    }

    /**
     * Get the validation error message.
     *
     * @param ValidationAttribute|null $attribute Attribute metadata
     * @return string
     */
    public function message(?ValidationAttribute $attribute = null): string
    {
        $name = $attribute?->getDisplayName() ?? 'field';
        return "The {$name} has already been taken.";
    }

    /**
     * Get database connection.
     *
     * @return object
     * @throws \RuntimeException If database not available
     */
    private function getConnection(): object
    {
        // Try to get from container or global
        if (class_exists(\Toporia\Framework\Container\Container::class)) {
            $container = \Toporia\Framework\Container\Container::getInstance();
            if ($container->has(\Toporia\Framework\Database\Contracts\ConnectionInterface::class)) {
                return $container->get(\Toporia\Framework\Database\Contracts\ConnectionInterface::class);
            }
        }

        // Fallback: use reflection to access Validator's protected static method
        try {
            $reflection = new \ReflectionClass(\Toporia\Framework\Validation\Validator::class);
            $method = $reflection->getMethod('getConnection');
            $method->setAccessible(true);
            return $method->invoke(null);
        } catch (\ReflectionException $e) {
            throw new \RuntimeException('Database connection not available: ' . $e->getMessage());
        }
    }
}
