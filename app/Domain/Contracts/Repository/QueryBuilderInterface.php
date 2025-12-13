<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Repository;

/**
 * Query Builder Interface (Domain Layer)
 *
 * Abstraction for query building operations.
 * This allows Domain layer to define query criteria without depending on Framework.
 *
 * Clean Architecture:
 * - Domain layer defines the contract
 * - Infrastructure layer provides implementation
 * - No Framework dependencies in Domain layer
 */
interface QueryBuilderInterface
{
    /**
     * Add WHERE clause.
     *
     * @param string $column Column name
     * @param string $operator Operator (=, !=, >, <, etc.)
     * @param mixed $value Value
     * @return self
     */
    public function where(string $column, string $operator, mixed $value): self;

    /**
     * Add WHERE IN clause.
     *
     * @param string $column Column name
     * @param array<mixed> $values Values
     * @return self
     */
    public function whereIn(string $column, array $values): self;

    /**
     * Add WHERE NOT IN clause.
     *
     * @param string $column Column name
     * @param array<mixed> $values Values
     * @return self
     */
    public function whereNotIn(string $column, array $values): self;

    /**
     * Add WHERE NULL clause.
     *
     * @param string $column Column name
     * @return self
     */
    public function whereNull(string $column): self;

    /**
     * Add WHERE NOT NULL clause.
     *
     * @param string $column Column name
     * @return self
     */
    public function whereNotNull(string $column): self;

    /**
     * Add WHERE BETWEEN clause.
     *
     * @param string $column Column name
     * @param mixed $min Minimum value
     * @param mixed $max Maximum value
     * @return self
     */
    public function whereBetween(string $column, mixed $min, mixed $max): self;

    /**
     * Add OR WHERE clause.
     *
     * @param string $column Column name
     * @param string $operator Operator
     * @param mixed $value Value
     * @return self
     */
    public function orWhere(string $column, string $operator, mixed $value): self;

    /**
     * Add WHERE clause with callback (for grouping).
     *
     * @param callable $callback Callback function
     * @return self
     */
    public function whereGroup(callable $callback): self;

    /**
     * Add ORDER BY clause.
     *
     * @param string $column Column name
     * @param string $direction ASC or DESC
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self;

    /**
     * Add LIMIT clause.
     *
     * @param int $limit Limit value
     * @return self
     */
    public function limit(int $limit): self;

    /**
     * Add OFFSET clause.
     *
     * @param int $offset Offset value
     * @return self
     */
    public function offset(int $offset): self;
}

