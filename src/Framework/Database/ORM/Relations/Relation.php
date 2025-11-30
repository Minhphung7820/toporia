<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\Contracts\RelationInterface;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Support\Str;


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
     * Copy where constraints from original query to new query, excluding parent-specific constraints.
     *
     * This ensures that constraints defined in relationship methods (like ->where('slug', 'like', '%Repellat%'))
     * are preserved when eager loading.
     *
     * @param QueryBuilder $newQuery The new query builder to apply constraints to
     * @param array $excludeColumns Columns to exclude (e.g., foreign key columns)
     * @return void
     */
    protected function copyWhereConstraints(QueryBuilder $newQuery, array $excludeColumns = []): void
    {
        $originalWheres = $this->query->getWheres();

        foreach ($originalWheres as $where) {
            // Skip if column is in exclude list (parent-specific constraints)
            if (isset($where['column'])) {
                $column = $where['column'];

                // Check if column should be excluded
                $shouldExclude = false;
                foreach ($excludeColumns as $excludePattern) {
                    if (is_string($excludePattern)) {
                        if ($column === $excludePattern || Str::endsWith($column, '.' . $excludePattern)) {
                            $shouldExclude = true;
                            break;
                        }
                    } elseif (is_callable($excludePattern)) {
                        if ($excludePattern($column)) {
                            $shouldExclude = true;
                            break;
                        }
                    }
                }

                if ($shouldExclude) {
                    continue;
                }
            }

            // Apply the where constraint to the new query based on type
            match ($where['type'] ?? '') {
                'basic' => $newQuery->where(
                    $where['column'],
                    $where['operator'] ?? '=',
                    $where['value'] ?? null,
                    $where['boolean'] ?? 'AND'
                ),
                'Null' => $newQuery->whereNull($where['column'], $where['boolean'] ?? 'AND'),
                'NotNull' => $newQuery->whereNotNull($where['column'], $where['boolean'] ?? 'AND'),
                'In' => $newQuery->whereIn($where['column'], $where['values'] ?? [], $where['boolean'] ?? 'AND'),
                'NotIn' => $newQuery->whereNotIn($where['column'], $where['values'] ?? [], $where['boolean'] ?? 'AND'),
                'Raw' => $newQuery->whereRaw(
                    $where['sql'] ?? '',
                    $where['bindings'] ?? [],
                    $where['boolean'] ?? 'AND'
                ),
                'nested' => $newQuery->where(function ($q) use ($where, $excludeColumns) {
                    // For nested queries, recursively apply constraints
                    if (isset($where['query']) && method_exists($where['query'], 'getWheres')) {
                        $nestedWheres = $where['query']->getWheres();
                        foreach ($nestedWheres as $nestedWhere) {
                            if (isset($nestedWhere['column'])) {
                                $nestedColumn = $nestedWhere['column'];
                                $shouldExclude = false;
                                foreach ($excludeColumns as $excludePattern) {
                                    if (is_string($excludePattern)) {
                                        if ($nestedColumn === $excludePattern || Str::endsWith($nestedColumn, '.' . $excludePattern)) {
                                            $shouldExclude = true;
                                            break;
                                        }
                                    } elseif (is_callable($excludePattern)) {
                                        if ($excludePattern($nestedColumn)) {
                                            $shouldExclude = true;
                                            break;
                                        }
                                    }
                                }
                                if ($shouldExclude) {
                                    continue;
                                }
                            }

                            if ($nestedWhere['type'] === 'basic') {
                                $q->where(
                                    $nestedWhere['column'],
                                    $nestedWhere['operator'] ?? '=',
                                    $nestedWhere['value'] ?? null,
                                    $nestedWhere['boolean'] ?? 'AND'
                                );
                            }
                        }
                    }
                }, $where['boolean'] ?? 'AND'),
                default => null, // Skip unknown types
            };
        }
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
     * Set the query builder for this relation.
     *
     * Allows replacing the query without reflection, following Open/Closed principle.
     *
     * Performance: O(1) - Direct property assignment
     * Clean Architecture: Setter method instead of reflection manipulation
     *
     * @param QueryBuilder $query New query builder instance
     * @return $this
     */
    public function setQuery(QueryBuilder $query): static
    {
        $this->query = $query;
        return $this;
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
     * OPTIMIZED: Automatically applies soft delete scope if related model uses soft deletes.
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

        // Apply soft delete scope if related model uses soft deletes
        $relatedClass = $this->getRelatedClass();
        if ($relatedClass !== '') {
            $this->applySoftDeleteScope($this->query, $relatedClass);
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

    // =========================================================================
    // SOFT DELETE SUPPORT
    // =========================================================================

    /**
     * Check if related model class uses soft deletes.
     *
     * Performance: O(1) - Single method_exists check
     *
     * @param string $modelClass Model class name
     * @return bool
     */
    protected function relatedModelUsesSoftDeletes(string $modelClass): bool
    {
        return method_exists($modelClass, 'usesSoftDeletes') && $modelClass::usesSoftDeletes();
    }

    /**
     * Get deleted_at column name from related model.
     *
     * Performance: O(1) - Single method call
     *
     * @param string $modelClass Model class name
     * @return string
     */
    protected function getDeletedAtColumn(string $modelClass): string
    {
        if (method_exists($modelClass, 'getDeletedAtColumn')) {
            return $modelClass::getDeletedAtColumn();
        }

        return 'deleted_at';
    }

    /**
     * Apply soft delete scope to query if related model uses soft deletes.
     *
     * Performance: O(1) - Single WHERE clause addition
     * Only adds WHERE deleted_at IS NULL if model uses soft deletes
     * Respects withTrashed() by checking skipGlobalScopes flag
     *
     * @param QueryBuilder $query Query builder
     * @param string $modelClass Related model class name
     * @param string|null $tableAlias Optional table alias for qualified column
     * @return void
     */
    protected function applySoftDeleteScope(QueryBuilder $query, string $modelClass, ?string $tableAlias = null): void
    {
        if (!$this->relatedModelUsesSoftDeletes($modelClass)) {
            return;
        }

        // Check if query has skipGlobalScopes flag (from withTrashed())
        // If ModelQueryBuilder with skipGlobalScopes = true, don't apply SoftDeletes scope
        if ($query instanceof \Toporia\Framework\Database\ORM\ModelQueryBuilder) {
            $reflection = new \ReflectionClass($query);
            if ($reflection->hasProperty('skipGlobalScopes')) {
                $property = $reflection->getProperty('skipGlobalScopes');
                $property->setAccessible(true);
                if ($property->getValue($query) === true) {
                    return; // Skip applying SoftDeletes scope when withTrashed() is used
                }
            }
        }

        $deletedAtColumn = $this->getDeletedAtColumn($modelClass);
        $qualifiedColumn = $tableAlias ? "{$tableAlias}.{$deletedAtColumn}" : $deletedAtColumn;

        $query->whereNull($qualifiedColumn);
    }

    /**
     * Get related model class name.
     *
     * Subclasses should override this to return the related model class.
     *
     * @return string
     */
    protected function getRelatedClass(): string
    {
        // Default implementation - subclasses should override
        return '';
    }

    /**
     * Get the order direction for a specific column from query.
     *
     * Helper method for cursor pagination.
     *
     * @param QueryBuilder $query Query builder
     * @param string $column Column name
     * @return string|null 'ASC' or 'DESC', or null if not found
     */
    protected function getOrderDirectionForColumn(QueryBuilder $query, string $column): ?string
    {
        // Use reflection to access orders (QueryBuilder doesn't expose this publicly)
        try {
            $reflection = new \ReflectionClass($query);
            $ordersProperty = $reflection->getProperty('orders');
            $ordersProperty->setAccessible(true);
            $orders = $ordersProperty->getValue($query);
            $ordersProperty->setAccessible(false);

            // Find order by for this column
            foreach ($orders as $order) {
                if (isset($order['column']) && $order['column'] === $column) {
                    return $order['direction'] ?? 'ASC';
                }
            }
        } catch (\ReflectionException $e) {
            // Fallback to null if reflection fails
        }

        return null;
    }

    /**
     * Ensure query is ordered by cursor column.
     *
     * Helper method for cursor pagination.
     *
     * @param QueryBuilder $query Query builder
     * @param string $column Cursor column
     * @param string $direction Order direction
     * @return QueryBuilder
     */
    protected function ensureOrderByCursorColumn(QueryBuilder $query, string $column, string $direction): QueryBuilder
    {
        // Check if column is already ordered
        $isOrdered = false;
        try {
            $reflection = new \ReflectionClass($query);
            $ordersProperty = $reflection->getProperty('orders');
            $ordersProperty->setAccessible(true);
            $orders = $ordersProperty->getValue($query);
            $ordersProperty->setAccessible(false);

            foreach ($orders as $order) {
                if (isset($order['column']) && $order['column'] === $column) {
                    $isOrdered = true;
                    break;
                }
            }
        } catch (\ReflectionException $e) {
            // If reflection fails, add order anyway
        }

        // Add cursor column as primary sort if not already present
        if (!$isOrdered) {
            $query->orderBy($column, $direction);
        }

        return $query;
    }

    /**
     * Encode cursor value for pagination.
     *
     * Helper method for cursor pagination.
     *
     * @param mixed $value Cursor value
     * @param string $column Column name
     * @return string Encoded cursor
     */
    protected function encodeCursor(mixed $value, string $column): string
    {
        $data = [
            'column' => $column,
            'value' => $value,
            'ts' => time(),
        ];

        return base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Decode cursor value from pagination.
     *
     * Helper method for cursor pagination.
     *
     * @param string $cursor Encoded cursor
     * @param string $column Expected column name
     * @return mixed|null Decoded cursor value or null if invalid
     */
    protected function decodeCursor(string $cursor, string $column): mixed
    {
        try {
            $decoded = base64_decode($cursor, true);
            if ($decoded === false) {
                return null;
            }

            $data = json_decode($decoded, true);
            if (!is_array($data) || !isset($data['value'])) {
                return null;
            }

            // Security: Validate column matches (prevents column injection)
            if (isset($data['column']) && $data['column'] !== $column) {
                return null;
            }

            return $data['value'];
        } catch (\Throwable $e) {
            // Invalid cursor format - return null to start from beginning
            return null;
        }
    }
}
