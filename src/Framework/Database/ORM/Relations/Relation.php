<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\Contracts\RelationInterface;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\ORM\Model;


/**
 * Abstract Class Relation
 *
 * Abstract base class for Relation implementations in the Relations layer
 * providing common functionality and contracts.
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
abstract class Relation implements RelationInterface
{
    /**
     * @param QueryBuilder $query Query builder for related model
     * @param Model $parent Parent model instance
     * @param string $foreignKey Foreign key column name
     * @param string $localKey Local key column name
     */
    public function __construct(
        protected QueryBuilder $query,
        protected Model $parent,
        protected string $foreignKey,
        protected string $localKey
    ) {}

    /**
     * Create a new instance for eager loading without parent constraints.
     *
     * This factory method creates a fresh relation instance with a clean query,
     * avoiding the need for reflection to manipulate private properties.
     *
     * Performance: O(1) - Direct instantiation, no reflection overhead
     * Clean Architecture: Open/Closed - Extensible without modifying base class
     *
     * @param QueryBuilder $freshQuery Fresh query builder without constraints
     * @return static New relation instance ready for eager loading
     */
    public function newEagerInstance(QueryBuilder $freshQuery): static
    {
        return new static($freshQuery, $this->parent, $this->foreignKey, $this->localKey);
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    /**
     * Add basic WHERE constraint based on parent model.
     *
     * @return $this
     */
    public function addConstraints(): static
    {
        if ($this->parent->exists()) {
            $this->query->where(
                $this->foreignKey,
                $this->parent->getAttribute($this->localKey)
            );
        }

        return $this;
    }

    /**
     * Set WHERE IN constraint for eager loading.
     *
     * @param array<int, Model> $models
     * @return void
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = [];
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            if ($key !== null) {
                $keys[] = $key;
            }
        }

        if (!empty($keys)) {
            $this->query->whereIn($this->foreignKey, array_unique($keys));
        }
    }

    /**
     * Execute the relationship query.
     *
     * Subclasses must implement getResults() for specific return types.
     */
    abstract public function getResults(): mixed;

    /**
     * Match eager loaded results to their parent models.
     *
     * Subclasses must implement match() for specific matching logic.
     */
    abstract public function match(array $models, mixed $results, string $relationName): array;
}
