<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\Schema;

/**
 * Column Definition Helper
 *
 * Provides fluent interface for column modifiers.
 *
 * Performance:
 * - O(1) operations (direct array reference modification)
 * - No overhead (fluent interface pattern)
 *
 * Clean Architecture:
 * - Single Responsibility: Only modifies column definitions
 * - Open/Closed: Extensible via new modifier methods
 *
 * SOLID Principles:
 * - Single Responsibility: Column definition modifiers only
 * - Open/Closed: Add new modifiers without modifying existing code
 */
class ColumnDefinition
{
    /**
     * @param array $column Reference to column definition array.
     */
    public function __construct(
        private array &$column
    ) {}

    /**
     * Make column nullable.
     *
     * @return self
     */
    public function nullable(bool $nullable = true): self
    {
        $this->column['nullable'] = $nullable;
        return $this;
    }

    /**
     * Set default value.
     *
     * @param mixed $value Default value.
     * @return self
     */
    public function default(mixed $value): self
    {
        $this->column['default'] = $value;
        return $this;
    }

    /**
     * Make column unsigned (integers).
     *
     * @return self
     */
    public function unsigned(): self
    {
        $this->column['unsigned'] = true;
        return $this;
    }

    /**
     * Make column unique.
     *
     * @return self
     */
    public function unique(?string $indexName = null): self
    {
        $this->column['unique'] = true;
        if ($indexName !== null) {
            $this->column['uniqueIndexName'] = $indexName;
        }
        return $this;
    }

    /**
     * Add comment to column.
     *
     * @param string $comment Column comment.
     * @return self
     */
    public function comment(string $comment): self
    {
        $this->column['comment'] = $comment;
        return $this;
    }

    /**
     * Set column position (for ALTER TABLE).
     * Place column after another column.
     *
     * @param string $column Column name to place after.
     * @return self
     */
    public function after(string $column): self
    {
        $this->column['after'] = $column;
        return $this;
    }

    /**
     * Set column position (for ALTER TABLE).
     * Place column first.
     *
     * @return self
     */
    public function first(): self
    {
        $this->column['first'] = true;
        return $this;
    }

    /**
     * Mark column for modification (ALTER TABLE MODIFY).
     *
     * @return self
     */
    public function change(): self
    {
        $this->column['change'] = true;
        return $this;
    }

    /**
     * Set column to auto-increment.
     *
     * @return self
     */
    public function autoIncrement(): self
    {
        $this->column['autoIncrement'] = true;
        return $this;
    }

    /**
     * Set column as primary key.
     *
     * @return self
     */
    public function primary(): self
    {
        $this->column['primary'] = true;
        return $this;
    }

    /**
     * Set column length (for string types).
     *
     * @param int $length Column length.
     * @return self
     */
    public function length(int $length): self
    {
        $this->column['length'] = $length;
        return $this;
    }

    /**
     * Set precision and scale (for decimal types).
     *
     * @param int $precision Total digits.
     * @param int $scale Decimal digits.
     * @return self
     */
    public function precision(int $precision, int $scale = 0): self
    {
        $this->column['precision'] = $precision;
        $this->column['scale'] = $scale;
        return $this;
    }

    /**
     * Set column collation.
     *
     * @param string $collation Collation name.
     * @return self
     */
    public function collation(string $collation): self
    {
        $this->column['collation'] = $collation;
        return $this;
    }

    /**
     * Set column charset.
     *
     * @param string $charset Charset name.
     * @return self
     */
    public function charset(string $charset): self
    {
        $this->column['charset'] = $charset;
        return $this;
    }

    /**
     * Set column to use current timestamp as default.
     *
     * @return self
     */
    public function useCurrent(): self
    {
        $this->column['useCurrent'] = true;
        return $this;
    }

    /**
     * Set column to use current timestamp on update (for timestamps).
     *
     * @return self
     */
    public function useCurrentOnUpdate(): self
    {
        $this->column['useCurrentOnUpdate'] = true;
        return $this;
    }

    /**
     * Set column to be stored as (for JSON columns).
     *
     * @param string $as 'json' or 'jsonb' (PostgreSQL).
     * @return self
     */
    public function storedAs(string $as): self
    {
        $this->column['storedAs'] = $as;
        return $this;
    }

    /**
     * Set column to be virtual (computed column).
     *
     * @param string|null $expression Expression for virtual column.
     * @return self
     */
    public function virtualAs(?string $expression = null): self
    {
        $this->column['virtual'] = true;
        if ($expression !== null) {
            $this->column['virtualExpression'] = $expression;
        }
        return $this;
    }
}
