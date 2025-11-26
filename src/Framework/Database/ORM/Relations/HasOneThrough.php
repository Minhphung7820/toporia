<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\Contracts\RelationInterface;
use Toporia\Framework\Database\ORM\{Model, ModelCollection};


/**
 * Class HasOneThrough
 *
 * Core class for the Relations layer providing essential functionality for
 * the Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Relations
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
class HasOneThrough extends Relation
{
    /**
     * @param \Toporia\Framework\Database\Query\QueryBuilder $query Query builder for related model
     * @param Model $parent Parent model instance
     * @param class-string<Model> $relatedClass Related model class (Phone)
     * @param class-string<Model> $throughClass Through model class (User)
     * @param string $firstKey Foreign key on through table (users.country_id)
     * @param string $secondKey Foreign key on related table (phones.user_id)
     * @param string $localKey Local key on parent table (countries.id)
     * @param string $secondLocalKey Local key on through table (users.id)
     */
    public function __construct(
        \Toporia\Framework\Database\Query\QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        protected string $throughClass,
        protected string $firstKey,  // users.country_id
        string $secondKey,           // phones.user_id
        string $localKey,            // countries.id
        protected string $secondLocalKey
    ) {
        // Call parent constructor first (will set $this->foreignKey and $this->localKey)
        parent::__construct($query, $parent, $firstKey, $localKey);

        // Override foreignKey to use secondKey (phones.user_id instead of users.country_id)
        // This is the actual foreign key on the related table
        $this->foreignKey = $secondKey; // phones.user_id
        // localKey stays as countries.id (correct)

        // Set up the join
        $this->performJoin();
    }

    /**
     * Perform join with through table.
     *
     * @return void
     */
    protected function performJoin(): void
    {
        $throughTable = call_user_func([$this->throughClass, 'getTableName']);
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Join through table to related table
        // INNER JOIN users ON users.id = phones.user_id
        $this->query->join(
            $throughTable,
            "{$throughTable}.{$this->secondLocalKey}",
            '=',
            "{$relatedTable}.{$this->foreignKey}"
        );

        // Add constraint for parent
        // WHERE users.country_id = ?
        if ($this->parent->exists()) {
            $this->query->where(
                "{$throughTable}.{$this->firstKey}",
                $this->parent->getAttribute($this->localKey)
            );
        }
    }

    /**
     * Override addConstraints to prevent base class from adding wrong constraints.
     * HasOneThrough uses performJoin() instead.
     *
     * @return $this
     */
    public function addConstraints(): static
    {
        // Constraints are already added in performJoin()
        // Don't call parent::addConstraints() as it would use wrong foreign key
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return Model|null
     */
    public function getResults(): mixed
    {
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Select only related table columns to avoid column ambiguity
        $this->query->select("{$relatedTable}.*");

        // Since $this->query is a ModelQueryBuilder, first() returns Model|null
        return $this->query->first();
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models): void
    {
        $throughTable = call_user_func([$this->throughClass, 'getTableName']);

        // Get parent IDs
        $keys = array_map(fn($m) => $m->getAttribute($this->localKey), $models);

        // Clear existing where (from performJoin)
        $this->query = $this->query->newQuery()->table(
            call_user_func([$this->relatedClass, 'getTableName'])
        );

        // Re-add join
        $this->performJoin();

        // WHERE users.country_id IN (1, 2, 3, ...)
        $this->query->whereIn("{$throughTable}.{$this->firstKey}", $keys);
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        $throughTable = call_user_func([$this->throughClass, 'getTableName']);

        // Build dictionary: parent_id => related_model
        $dictionary = [];
        foreach ($results as $result) {
            // Get the through key from result
            $key = $result->getAttribute($this->firstKey);
            $dictionary[$key] = $result;
        }

        // Match to parents
        foreach ($models as $model) {
            $localValue = $model->getAttribute($this->localKey);
            $related = $dictionary[$localValue] ?? null;
            $model->setRelation($relationName, $related);
        }

        return $models;
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Check if the related model exists.
     *
     * Performance: O(1) - Single EXISTS query with JOIN
     * Clean Architecture: Expressive existence check
     *
     * @return bool True if related model exists
     *
     * @example
     * ```php
     * if ($country->phone()->exists()) {
     *     // Country has a phone through user
     * }
     * ```
     */
    public function exists(): bool
    {
        if (!$this->parent->exists()) {
            return false;
        }

        return $this->query->exists();
    }

    /**
     * Get the count of related models (always 0 or 1 for HasOneThrough).
     *
     * Performance: O(1) - Single COUNT query with JOIN
     * Clean Architecture: Consistent interface with other relations
     *
     * @return int Count of related models
     *
     * @example
     * ```php
     * $count = $country->phone()->count(); // 0 or 1
     * ```
     */
    public function count(): int
    {
        if (!$this->parent->exists()) {
            return 0;
        }

        return $this->query->count();
    }

    /**
     * Update the related model through the intermediate relationship.
     *
     * Performance: O(1) - Single UPDATE with JOIN constraint
     * Clean Architecture: Expressive update method
     *
     * @param array $attributes Attributes to update
     * @return int Number of affected rows
     *
     * @example
     * ```php
     * $country->phone()->update(['number' => '+1-555-0123']);
     * ```
     */
    public function update(array $attributes): int
    {
        if (!$this->parent->exists()) {
            return 0;
        }

        return $this->query->update($attributes);
    }

    /**
     * Delete the related model through the intermediate relationship.
     *
     * Performance: O(1) - Single DELETE with JOIN constraint
     * Clean Architecture: Expressive deletion method
     *
     * @return int Number of deleted rows
     *
     * @example
     * ```php
     * $country->phone()->delete();
     * ```
     */
    public function delete(): int
    {
        if (!$this->parent->exists()) {
            return 0;
        }

        return $this->query->delete();
    }

    /**
     * Get the first related model or fail.
     *
     * Performance: O(1) - Single SELECT with JOIN
     * Clean Architecture: Expressive finder with exception
     *
     * @return Model Found model
     * @throws \RuntimeException If no model found
     *
     * @example
     * ```php
     * $phone = $country->phone()->firstOrFail();
     * ```
     */
    public function firstOrFail(): Model
    {
        $result = $this->getResults();

        if ($result === null) {
            throw new \RuntimeException('No related model found through intermediate relationship');
        }

        return $result;
    }

    /**
     * Get the sum of a column through the relationship.
     *
     * Performance: O(1) - Single aggregation query with JOIN
     * Clean Architecture: Expressive aggregation method
     *
     * @param string $column Column name
     * @return float|int
     *
     * @example
     * ```php
     * $totalMinutes = $country->phone()->sum('minutes_used');
     * ```
     */
    public function sum(string $column): float|int
    {
        return $this->query->sum($column) ?? 0;
    }

    /**
     * Get the average of a column through the relationship.
     *
     * @param string $column Column name
     * @return float|int
     */
    public function avg(string $column): float|int
    {
        return $this->query->avg($column) ?? 0;
    }

    /**
     * Get the minimum value of a column through the relationship.
     *
     * @param string $column Column name
     * @return mixed
     */
    public function min(string $column): mixed
    {
        return $this->query->min($column);
    }

    /**
     * Get the maximum value of a column through the relationship.
     *
     * @param string $column Column name
     * @return mixed
     */
    public function max(string $column): mixed
    {
        return $this->query->max($column);
    }

    /**
     * Get the through model class name.
     *
     * Performance: O(1) - Direct property access
     * Clean Architecture: Expressive getter method
     *
     * @return class-string<Model> Through model class
     *
     * @example
     * ```php
     * $throughClass = $country->phone()->getThroughClass(); // User::class
     * ```
     */
    public function getThroughClass(): string
    {
        return $this->throughClass;
    }

    /**
     * Get the related model class name.
     *
     * Performance: O(1) - Direct property access
     * Clean Architecture: Expressive getter method
     *
     * @return class-string<Model> Related model class
     *
     * @example
     * ```php
     * $relatedClass = $country->phone()->getRelatedClass(); // Phone::class
     * ```
     */
    public function getRelatedClass(): string
    {
        return $this->relatedClass;
    }

    /**
     * Get the first key (foreign key on through table).
     *
     * Performance: O(1) - Direct property access
     * Clean Architecture: Expressive getter method
     *
     * @return string First key name
     *
     * @example
     * ```php
     * $firstKey = $country->phone()->getFirstKey(); // 'country_id'
     * ```
     */
    public function getFirstKey(): string
    {
        return $this->firstKey;
    }

    /**
     * Get the second local key (local key on through table).
     *
     * Performance: O(1) - Direct property access
     * Clean Architecture: Expressive getter method
     *
     * @return string Second local key name
     *
     * @example
     * ```php
     * $secondLocalKey = $country->phone()->getSecondLocalKey(); // 'id'
     * ```
     */
    public function getSecondLocalKey(): string
    {
        return $this->secondLocalKey;
    }

    /**
     * Add constraints on the through table.
     *
     * Performance: O(1) - Direct query modification
     * Clean Architecture: Fluent interface for additional constraints
     *
     * @param string $column Through table column name
     * @param mixed $operator Operator or value
     * @param mixed $value Value (optional)
     * @return $this
     *
     * @example
     * ```php
     * $phone = $country->phone()->whereThrough('status', 'active')->first();
     * ```
     */
    public function whereThrough(string $column, mixed $operator, mixed $value = null): static
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $throughTable = call_user_func([$this->throughClass, 'getTableName']);
        $this->query->where("{$throughTable}.{$column}", $operator, $value);

        return $this;
    }

    /**
     * Add whereIn constraint on the through table.
     *
     * @param string $column Through table column name
     * @param array $values Array of values
     * @return $this
     */
    public function whereThroughIn(string $column, array $values): static
    {
        $throughTable = call_user_func([$this->throughClass, 'getTableName']);
        $this->query->whereIn("{$throughTable}.{$column}", $values);

        return $this;
    }

    /**
     * Add order by clause on the through table.
     *
     * @param string $column Through table column name
     * @param string $direction Sort direction (asc|desc)
     * @return $this
     */
    public function orderByThrough(string $column, string $direction = 'asc'): static
    {
        $throughTable = call_user_func([$this->throughClass, 'getTableName']);
        $this->query->orderBy("{$throughTable}.{$column}", $direction);

        return $this;
    }

    /**
     * Magic method to delegate calls to the underlying query builder.
     *
     * Performance: O(1) - Direct method delegation
     * Clean Architecture: Proxy pattern for query builder methods
     * SOLID: Interface Segregation - Expose only relevant query methods
     *
     * @param string $method Method name
     * @param array $parameters Method parameters
     * @return mixed
     *
     * @throws \BadMethodCallException If method doesn't exist on query builder
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Delegate to query builder for standard query methods
        if (method_exists($this->query, $method)) {
            $result = $this->query->{$method}(...$parameters);

            // Return $this for fluent interface on builder methods that return QueryBuilder
            if ($result instanceof \Toporia\Framework\Database\Query\QueryBuilder) {
                return $this;
            }

            return $result;
        }

        throw new \BadMethodCallException(
            sprintf('Method %s::%s does not exist.', static::class, $method)
        );
    }
}
