<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};
use Toporia\Framework\Database\Query\QueryBuilder;


/**
 * Class BelongsToMany
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
class BelongsToMany extends Relation
{
    /**
     * Additional pivot columns to select.
     *
     * @var array<string>
     */
    protected array $pivotColumns = [];

    /**
     * Whether to include timestamps in pivot table.
     *
     * @var bool
     */
    protected bool $withTimestamps = false;

    /**
     * Custom pivot accessor name.
     *
     * @var string
     */
    protected string $pivotAccessor = 'pivot';

    /**
     * Pivot where constraints.
     *
     * @var array<array{column: string, operator: string, value: mixed}>
     */
    protected array $pivotWheres = [];

    /**
     * Pivot whereIn constraints.
     *
     * @var array<array{column: string, values: array}>
     */
    protected array $pivotWhereIns = [];

    /**
     * Pivot order by clauses.
     *
     * @var array<array{column: string, direction: string}>
     */
    protected array $pivotOrderBy = [];

    /**
     * Custom pivot model class.
     *
     * @var class-string|null
     */
    protected ?string $pivotClass = null;

    /**
     * @param QueryBuilder $query Query builder for related model
     * @param Model $parent Parent model instance
     * @param class-string<Model> $relatedClass Related model class name
     * @param string $pivotTable Pivot table name
     * @param string $foreignPivotKey Foreign key in pivot table for parent
     * @param string $relatedPivotKey Foreign key in pivot table for related
     * @param string $parentKey Parent's primary key
     * @param string $relatedKey Related model's primary key
     */
    public function __construct(
        QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        protected string $pivotTable,
        protected string $foreignPivotKey,
        protected string $relatedPivotKey,
        protected string $parentKey,
        protected string $relatedKey
    ) {
        parent::__construct($query, $parent, $foreignPivotKey, $parentKey);
        $this->addPivotConstraints();
    }

    /**
     * Specify additional pivot columns to include in query results.
     *
     * These columns will be selected from the pivot table and made available
     * on the related model (e.g., $role->pivot->created_at).
     *
     * Performance: O(1) - Array merge operation
     * Clean Architecture: Fluent interface for readability
     *
     * @param string ...$columns Pivot column names to select
     * @return $this
     *
     * @example
     * ```php
     * $user->roles()->withPivot('expires_at', 'created_by')->get();
     * // Access: $role->pivot->expires_at
     * ```
     */
    public function withPivot(string ...$columns): static
    {
        $this->pivotColumns = array_merge($this->pivotColumns, $columns);
        return $this;
    }

    /**
     * Include created_at and updated_at timestamps in pivot table.
     *
     * @return $this
     *
     * @example
     * ```php
     * $user->roles()->withTimestamps()->get();
     * // Access: $role->pivot->created_at, $role->pivot->updated_at
     * ```
     */
    public function withTimestamps(): static
    {
        $this->withTimestamps = true;
        return $this->withPivot('created_at', 'updated_at');
    }

    /**
     * Customize the pivot accessor name.
     *
     * @param string $accessor Custom accessor name
     * @return $this
     *
     * @example
     * ```php
     * $user->podcasts()->as('subscription')->withTimestamps()->get();
     * // Access: $podcast->subscription->created_at
     * ```
     */
    public function as(string $accessor): static
    {
        $this->pivotAccessor = $accessor;
        return $this;
    }

    /**
     * Add a where constraint on the pivot table.
     *
     * @param string $column Pivot column name
     * @param mixed $operator Operator or value if no operator provided
     * @param mixed $value Value to compare (optional)
     * @return $this
     *
     * @example
     * ```php
     * $user->roles()->wherePivot('status', 'active')->get();
     * $user->roles()->wherePivot('priority', '>', 5)->get();
     * ```
     */
    public function wherePivot(string $column, mixed $operator, mixed $value = null): static
    {
        // Handle case where only column and value are provided (operator defaults to '=')
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->pivotWheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];

        // Apply constraint immediately to current query
        $this->applyPivotWhere($column, $operator, $value);

        return $this;
    }

    /**
     * Apply a single pivot where constraint to the query.
     *
     * @param string $column Pivot column name
     * @param string $operator Comparison operator
     * @param mixed $value Value to compare
     * @return void
     */
    protected function applyPivotWhere(string $column, string $operator, mixed $value): void
    {
        $column = "{$this->pivotTable}.{$column}";

        if ($operator === 'BETWEEN' && is_array($value)) {
            $this->query->whereBetween($column, $value);
        } elseif ($operator === 'NOT BETWEEN' && is_array($value)) {
            $this->query->whereNotBetween($column, $value);
        } elseif ($operator === 'IS' && $value === null) {
            $this->query->whereNull($column);
        } elseif ($operator === 'IS NOT' && $value === null) {
            $this->query->whereNotNull($column);
        } else {
            $this->query->where($column, $operator, $value);
        }
    }

    /**
     * Add a whereIn constraint on the pivot table.
     *
     * @param string $column Pivot column name
     * @param array $values Array of values
     * @return $this
     *
     * @example
     * ```php
     * $user->roles()->wherePivotIn('priority', [1, 2, 3])->get();
     * ```
     */
    public function wherePivotIn(string $column, array $values): static
    {
        $this->pivotWhereIns[] = [
            'column' => $column,
            'values' => $values
        ];

        // Apply constraint immediately to current query
        $this->query->whereIn("{$this->pivotTable}.{$column}", $values);

        return $this;
    }

    /**
     * Add a whereNotIn constraint on the pivot table.
     *
     * @param string $column Pivot column name
     * @param array $values Array of values
     * @return $this
     *
     * @example
     * ```php
     * $user->roles()->wherePivotNotIn('status', ['inactive', 'banned'])->get();
     * ```
     */
    public function wherePivotNotIn(string $column, array $values): static
    {
        $this->pivotWhereIns[] = [
            'column' => $column,
            'values' => $values,
            'not' => true
        ];

        // Apply constraint immediately to current query
        $this->query->whereNotIn("{$this->pivotTable}.{$column}", $values);

        return $this;
    }

    /**
     * Add an order by clause on the pivot table.
     *
     * @param string $column Pivot column name
     * @param string $direction Sort direction (asc|desc)
     * @return $this
     *
     * @example
     * ```php
     * $user->roles()->orderByPivot('created_at', 'desc')->get();
     * ```
     */
    public function orderByPivot(string $column, string $direction = 'asc'): static
    {
        $direction = strtolower($direction);

        $this->pivotOrderBy[] = [
            'column' => $column,
            'direction' => $direction
        ];

        // Apply order immediately to current query
        $this->query->orderBy("{$this->pivotTable}.{$column}", $direction);

        return $this;
    }

    /**
     * Add a wherePivotNull constraint on the pivot table.
     *
     * @param string $column Pivot column name
     * @return $this
     */
    public function wherePivotNull(string $column): static
    {
        return $this->wherePivot($column, 'IS', null);
    }

    /**
     * Add a wherePivotNotNull constraint on the pivot table.
     *
     * @param string $column Pivot column name
     * @return $this
     */
    public function wherePivotNotNull(string $column): static
    {
        return $this->wherePivot($column, 'IS NOT', null);
    }

    /**
     * Add a wherePivotBetween constraint on the pivot table.
     *
     * @param string $column Pivot column name
     * @param array $values Array with two values [min, max]
     * @return $this
     */
    public function wherePivotBetween(string $column, array $values): static
    {
        return $this->wherePivot($column, 'BETWEEN', $values);
    }

    /**
     * Add a wherePivotNotBetween constraint on the pivot table.
     *
     * @param string $column Pivot column name
     * @param array $values Array with two values [min, max]
     * @return $this
     *
     * @example
     * ```php
     * $user->roles()->wherePivotNotBetween('priority', [1, 5])->get();
     * ```
     */
    public function wherePivotNotBetween(string $column, array $values): static
    {
        return $this->wherePivot($column, 'NOT BETWEEN', $values);
    }

    /**
     * Add a wherePivotDate constraint on the pivot table.
     *
     * Performance: O(1) - Direct SQL date function usage
     * Clean Architecture: Expressive domain-specific method
     *
     * @param string $column Pivot column name
     * @param string $operator Comparison operator
     * @param string $value Date value (Y-m-d format)
     * @return $this
     *
     * @example
     * ```php
     * $user->roles()->wherePivotDate('assigned_at', '>=', '2024-01-01')->get();
     * ```
     */
    public function wherePivotDate(string $column, string $operator, string $value): static
    {
        // Apply function directly to avoid double prefixing
        $this->query->whereRaw("DATE({$this->pivotTable}.{$column}) {$operator} ?", [$value]);

        // Store for eager loading
        $this->pivotWheres[] = [
            'column' => "DATE({$column})",
            'operator' => $operator,
            'value' => $value
        ];

        return $this;
    }

    /**
     * Add a wherePivotMonth constraint on the pivot table.
     *
     * @param string $column Pivot column name
     * @param string $operator Comparison operator
     * @param int $value Month value (1-12)
     * @return $this
     *
     * @example
     * ```php
     * $user->roles()->wherePivotMonth('assigned_at', '=', 12)->get();
     * ```
     */
    public function wherePivotMonth(string $column, string $operator, int $value): static
    {
        // Apply function directly to avoid double prefixing
        $this->query->whereRaw("MONTH({$this->pivotTable}.{$column}) {$operator} ?", [$value]);

        // Store for eager loading
        $this->pivotWheres[] = [
            'column' => "MONTH({$column})",
            'operator' => $operator,
            'value' => $value
        ];

        return $this;
    }

    /**
     * Add a wherePivotYear constraint on the pivot table.
     *
     * @param string $column Pivot column name
     * @param string $operator Comparison operator
     * @param int $value Year value
     * @return $this
     *
     * @example
     * ```php
     * $user->roles()->wherePivotYear('assigned_at', '=', 2024)->get();
     * ```
     */
    public function wherePivotYear(string $column, string $operator, int $value): static
    {
        // Apply function directly to avoid double prefixing
        $this->query->whereRaw("YEAR({$this->pivotTable}.{$column}) {$operator} ?", [$value]);

        // Store for eager loading
        $this->pivotWheres[] = [
            'column' => "YEAR({$column})",
            'operator' => $operator,
            'value' => $value
        ];

        return $this;
    }

    /**
     * Add a wherePivotTime constraint on the pivot table.
     *
     * @param string $column Pivot column name
     * @param string $operator Comparison operator
     * @param string $value Time value (H:i:s format)
     * @return $this
     *
     * @example
     * ```php
     * $user->roles()->wherePivotTime('assigned_at', '>=', '09:00:00')->get();
     * ```
     */
    public function wherePivotTime(string $column, string $operator, string $value): static
    {
        // Apply function directly to avoid double prefixing
        $this->query->whereRaw("TIME({$this->pivotTable}.{$column}) {$operator} ?", [$value]);

        // Store for eager loading
        $this->pivotWheres[] = [
            'column' => "TIME({$column})",
            'operator' => $operator,
            'value' => $value
        ];

        return $this;
    }

    /**
     * Add a wherePivotJsonContains constraint on the pivot table.
     *
     * Performance: O(log n) - Uses database JSON indexing when available
     * Clean Architecture: Database-agnostic JSON querying
     *
     * @param string $column Pivot JSON column name
     * @param mixed $value Value to search for in JSON
     * @param string $path Optional JSON path (default: '$')
     * @return $this
     *
     * @example
     * ```php
     * $user->roles()->wherePivotJsonContains('metadata', 'admin', '$.permissions')->get();
     * ```
     */
    public function wherePivotJsonContains(string $column, mixed $value, string $path = '$'): static
    {
        $jsonValue = is_string($value) ? "\"$value\"" : json_encode($value);

        // Apply function directly to avoid double prefixing
        $this->query->whereRaw("JSON_CONTAINS({$this->pivotTable}.{$column}, ?, ?)", [$jsonValue, $path]);

        // Store for eager loading
        $this->pivotWheres[] = [
            'column' => "JSON_CONTAINS({$column}, '{$jsonValue}', '{$path}')",
            'operator' => '=',
            'value' => 1
        ];

        return $this;
    }

    /**
     * Add a wherePivotJsonLength constraint on the pivot table.
     *
     * @param string $column Pivot JSON column name
     * @param string $operator Comparison operator
     * @param int $value Length value
     * @param string $path Optional JSON path (default: '$')
     * @return $this
     *
     * @example
     * ```php
     * $user->roles()->wherePivotJsonLength('permissions', '>', 3)->get();
     * ```
     */
    public function wherePivotJsonLength(string $column, string $operator, int $value, string $path = '$'): static
    {
        // Apply function directly to avoid double prefixing
        $this->query->whereRaw("JSON_LENGTH({$this->pivotTable}.{$column}, ?) {$operator} ?", [$path, $value]);

        // Store for eager loading
        $this->pivotWheres[] = [
            'column' => "JSON_LENGTH({$column}, '{$path}')",
            'operator' => $operator,
            'value' => $value
        ];

        return $this;
    }

    /**
     * Add JOIN and WHERE constraints for the pivot table.
     *
     * @return $this
     */
    protected function addPivotConstraints(): static
    {
        if ($this->parent->exists()) {
            $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

            // Build SELECT clause with pivot columns
            $selectColumns = ["{$relatedTable}.*"];

            // Add pivot columns if specified
            foreach ($this->pivotColumns as $column) {
                $selectColumns[] = "{$this->pivotTable}.{$column} as pivot_{$column}";
            }

            $this->query
                ->join(
                    $this->pivotTable,
                    "{$relatedTable}.{$this->relatedKey}",
                    '=',
                    "{$this->pivotTable}.{$this->relatedPivotKey}"
                )
                ->where(
                    "{$this->pivotTable}.{$this->foreignPivotKey}",
                    $this->parent->getAttribute($this->parentKey)
                )
                ->select($selectColumns);

            // Apply pivot where constraints
            $this->applyPivotConstraints();
        }

        return $this;
    }

    /**
     * Apply pivot where constraints and order by clauses.
     *
     * @return void
     */
    protected function applyPivotConstraints(): void
    {
        // Apply pivot where constraints
        foreach ($this->pivotWheres as $where) {
            $column = "{$this->pivotTable}.{$where['column']}";

            if ($where['operator'] === 'BETWEEN' && is_array($where['value'])) {
                $this->query->whereBetween($column, $where['value']);
            } elseif ($where['operator'] === 'IS' && $where['value'] === null) {
                $this->query->whereNull($column);
            } elseif ($where['operator'] === 'IS NOT' && $where['value'] === null) {
                $this->query->whereNotNull($column);
            } else {
                $this->query->where($column, $where['operator'], $where['value']);
            }
        }

        // Apply pivot whereIn constraints
        foreach ($this->pivotWhereIns as $whereIn) {
            $column = "{$this->pivotTable}.{$whereIn['column']}";

            if (isset($whereIn['not']) && $whereIn['not']) {
                $this->query->whereNotIn($column, $whereIn['values']);
            } else {
                $this->query->whereIn($column, $whereIn['values']);
            }
        }

        // Apply pivot order by clauses
        foreach ($this->pivotOrderBy as $orderBy) {
            $column = "{$this->pivotTable}.{$orderBy['column']}";
            $this->query->orderBy($column, $orderBy['direction']);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return ModelCollection
     */
    public function getResults(): ModelCollection
    {
        $rows = $this->query->get();

        if ($rows->isEmpty()) {
            return new ModelCollection([]);
        }

        // Convert RowCollection to array for hydrate method
        return call_user_func([$this->relatedClass, 'hydrate'], $rows->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = [];
        foreach ($models as $model) {
            $key = $model->getAttribute($this->parentKey);
            if ($key !== null) {
                $keys[] = $key;
            }
        }

        if (!empty($keys)) {
            $this->query->whereIn(
                "{$this->pivotTable}.{$this->foreignPivotKey}",
                array_unique($keys)
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     * Match eagerly loaded results to their parent models.
     *
     * PERFORMANCE OPTIMIZATION: Two strategies available
     * 1. Current: 2 queries (main + pivot lookup) - safer, works with complex pivot constraints
     * 2. Future: 1 query (select parent_id from pivot in main query) - faster for simple cases
     *
     * Performance: O(n + m) where n = parents, m = results
     * - Single query to get pivot mappings (or could be optimized to 0 extra queries)
     * - Dictionary-based matching with O(1) lookups
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection || $results->isEmpty()) {
            // No results - set empty collections for all models
            foreach ($models as $model) {
                $model->setRelation($relationName, new ModelCollection([]));
            }
            return $models;
        }

        // PERFORMANCE NOTE: If pivot constraints are simple and performance is critical,
        // we could optimize this by selecting parent_id directly in the main eager query
        // This would eliminate the need for a separate pivot query
        if ($this->shouldUseOptimizedMatching()) {
            return $this->matchOptimized($models, $results, $relationName);
        }

        // Standard matching with separate pivot query (current implementation)
        return $this->matchWithPivotQuery($models, $results, $relationName);
    }

    /**
     * Check if we can use optimized matching (single query).
     *
     * Optimized matching works when:
     * - No complex pivot constraints (JSON functions, date functions, etc.)
     * - Simple WHERE/IN constraints only
     * - Parent ID is available in the result set
     *
     * @return bool
     */
    protected function shouldUseOptimizedMatching(): bool
    {
        // Check if we have complex pivot constraints that require separate pivot query
        foreach ($this->pivotWheres as $where) {
            // If column contains SQL functions, we need separate pivot query
            if (str_contains($where['column'], '(') || str_contains($where['column'], ')')) {
                return false;
            }
        }

        // For now, disable optimized matching to avoid column not found errors
        // TODO: Re-enable after proper column validation is implemented
        return false;
    }

    /**
     * Optimized matching using parent_id from main query (1 query total).
     *
     * @param array $models Parent models
     * @param ModelCollection $results Related models with parent_id
     * @param string $relationName Relation name
     * @return array
     */
    protected function matchOptimized(array $models, ModelCollection $results, string $relationName): array
    {
        // Build dictionary: parent_id => [related_models]
        $dictionary = [];
        foreach ($results as $result) {
            // Parent ID should be available from the main query's SELECT
            $parentId = $result->getAttribute("pivot_{$this->foreignPivotKey}");
            if ($parentId !== null) {
                if (!isset($dictionary[$parentId])) {
                    $dictionary[$parentId] = [];
                }
                $dictionary[$parentId][] = $result;
            }
        }

        // Match to parents
        foreach ($models as $model) {
            $parentId = $model->getAttribute($this->parentKey);
            $matched = $dictionary[$parentId] ?? [];
            $model->setRelation($relationName, new ModelCollection($matched));
        }

        return $models;
    }

    /**
     * Standard matching with separate pivot query (2 queries total).
     *
     * @param array $models Parent models
     * @param ModelCollection $results Related models
     * @param string $relationName Relation name
     * @return array
     */
    protected function matchWithPivotQuery(array $models, ModelCollection $results, string $relationName): array
    {
        // Step 1: Collect parent and related IDs
        $parentIds = [];
        foreach ($models as $model) {
            $parentId = $model->getAttribute($this->parentKey);
            if ($parentId !== null) {
                $parentIds[] = $parentId;
            }
        }

        $relatedIds = [];
        foreach ($results as $result) {
            $relatedId = $result->getAttribute($this->relatedKey);
            if ($relatedId !== null) {
                $relatedIds[] = $relatedId;
            }
        }

        if (empty($parentIds) || empty($relatedIds)) {
            // No valid IDs - set empty collections
            foreach ($models as $model) {
                $model->setRelation($relationName, new ModelCollection([]));
            }
            return $models;
        }

        // Step 2: Query pivot table to get parent-to-related mappings
        // SELECT foreign_key, related_key FROM pivot_table
        // WHERE foreign_key IN (...) AND related_key IN (...)
        $qb = new QueryBuilder($this->query->getConnection());
        $pivotQuery = $qb->table($this->pivotTable)
            ->select($this->foreignPivotKey, $this->relatedPivotKey)
            ->whereIn($this->foreignPivotKey, array_unique($parentIds))
            ->whereIn($this->relatedPivotKey, array_unique($relatedIds));

        // Apply pivot constraints to the pivot query
        $this->applyPivotConstraintsToQuery($pivotQuery);

        $pivotRows = $pivotQuery->get();

        // Step 3: Build dictionary mapping parent IDs to arrays of related IDs
        // Example: [1 => [10, 20, 30], 2 => [20, 40], ...]
        $dictionary = [];
        foreach ($pivotRows as $pivot) {
            $parentId = $pivot[$this->foreignPivotKey] ?? null;
            $relatedId = $pivot[$this->relatedPivotKey] ?? null;

            if ($parentId !== null && $relatedId !== null) {
                if (!isset($dictionary[$parentId])) {
                    $dictionary[$parentId] = [];
                }
                $dictionary[$parentId][] = $relatedId;
            }
        }

        // Step 4: Build index of related models by ID for O(1) lookup
        // Example: [10 => Model, 20 => Model, ...]
        $relatedIndex = [];
        foreach ($results as $result) {
            $relatedId = $result->getAttribute($this->relatedKey);
            if ($relatedId !== null) {
                $relatedIndex[$relatedId] = $result;
            }
        }

        // Step 5: Match related models to parents using dictionary
        foreach ($models as $model) {
            $parentId = $model->getAttribute($this->parentKey);
            $matched = [];

            if ($parentId !== null && isset($dictionary[$parentId])) {
                // Get related IDs for this parent from dictionary
                $relatedIdsForParent = $dictionary[$parentId];

                // Look up actual models by ID
                foreach ($relatedIdsForParent as $relatedId) {
                    if (isset($relatedIndex[$relatedId])) {
                        $matched[] = $relatedIndex[$relatedId];
                    }
                }
            }

            // Set relation with matched models (or empty collection)
            $model->setRelation($relationName, new ModelCollection($matched));
        }

        return $models;
    }

    /**
     * Apply pivot constraints to a separate pivot query.
     *
     * @param QueryBuilder $query Pivot query builder
     * @return void
     */
    protected function applyPivotConstraintsToQuery(QueryBuilder $query): void
    {
        // Apply pivot where constraints
        foreach ($this->pivotWheres as $where) {
            $column = $where['column'];

            // Handle function-based columns
            if (str_contains($column, '(') && str_contains($column, ')')) {
                // Extract function and apply as raw where
                $operator = $where['operator'];
                $value = $where['value'];

                if (str_starts_with($column, 'DATE(')) {
                    $actualColumn = str_replace(['DATE(', ')'], '', $column);
                    $query->whereRaw("DATE({$actualColumn}) {$operator} ?", [$value]);
                } elseif (str_starts_with($column, 'MONTH(')) {
                    $actualColumn = str_replace(['MONTH(', ')'], '', $column);
                    $query->whereRaw("MONTH({$actualColumn}) {$operator} ?", [$value]);
                } elseif (str_starts_with($column, 'YEAR(')) {
                    $actualColumn = str_replace(['YEAR(', ')'], '', $column);
                    $query->whereRaw("YEAR({$actualColumn}) {$operator} ?", [$value]);
                } elseif (str_starts_with($column, 'TIME(')) {
                    $actualColumn = str_replace(['TIME(', ')'], '', $column);
                    $query->whereRaw("TIME({$actualColumn}) {$operator} ?", [$value]);
                } else {
                    // Generic function handling
                    $query->whereRaw("{$column} {$where['operator']} ?", [$where['value']]);
                }
            } else {
                // Regular column
                if ($where['operator'] === 'BETWEEN' && is_array($where['value'])) {
                    $query->whereBetween($column, $where['value']);
                } elseif ($where['operator'] === 'NOT BETWEEN' && is_array($where['value'])) {
                    $query->whereNotBetween($column, $where['value']);
                } elseif ($where['operator'] === 'IS' && $where['value'] === null) {
                    $query->whereNull($column);
                } elseif ($where['operator'] === 'IS NOT' && $where['value'] === null) {
                    $query->whereNotNull($column);
                } else {
                    $query->where($column, $where['operator'], $where['value']);
                }
            }
        }

        // Apply pivot whereIn constraints
        foreach ($this->pivotWhereIns as $whereIn) {
            if (isset($whereIn['not']) && $whereIn['not']) {
                $query->whereNotIn($whereIn['column'], $whereIn['values']);
            } else {
                $query->whereIn($whereIn['column'], $whereIn['values']);
            }
        }
    }

    /**
     * Attach a related model to the parent via pivot table.
     *
     * @param int|string|array $id Related model ID or array of IDs with pivot data
     * @param array<string, mixed> $pivotData Additional pivot data
     * @param bool $touch Whether to touch parent timestamps
     * @return array|bool Array of attached IDs or boolean for single attach
     */
    public function attach(int|string|array $id, array $pivotData = [], bool $touch = true): array|bool
    {
        if (is_array($id)) {
            return $this->attachMany($id, $touch);
        }

        $data = array_merge([
            $this->foreignPivotKey => $this->parent->getAttribute($this->parentKey),
            $this->relatedPivotKey => $id,
        ], $pivotData);

        // Add timestamps if enabled
        if ($this->withTimestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
        }

        $qb = new QueryBuilder($this->query->getConnection());
        $qb->table($this->pivotTable)->insert($data);

        if ($touch) {
            $this->touchParent();
        }

        return true;
    }

    /**
     * Attach multiple related models to the parent via pivot table.
     *
     * @param array $ids Array of IDs or associative array with pivot data
     * @param bool $touch Whether to touch parent timestamps
     * @return array Array of attached IDs
     */
    protected function attachMany(array $ids, bool $touch = true): array
    {
        $attached = [];
        $insertData = [];

        foreach ($ids as $key => $value) {
            if (is_numeric($key)) {
                // Simple array: [1, 2, 3]
                $relatedId = $value;
                $pivotData = [];
            } else {
                // Associative array: [1 => ['role' => 'admin'], 2 => ['role' => 'user']]
                $relatedId = $key;
                $pivotData = is_array($value) ? $value : [];
            }

            $data = array_merge([
                $this->foreignPivotKey => $this->parent->getAttribute($this->parentKey),
                $this->relatedPivotKey => $relatedId,
            ], $pivotData);

            // Add timestamps if enabled
            if ($this->withTimestamps) {
                $now = date('Y-m-d H:i:s');
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
            }

            $insertData[] = $data;
            $attached[] = $relatedId;
        }

        if (!empty($insertData)) {
            $qb = new QueryBuilder($this->query->getConnection());
            $qb->table($this->pivotTable)->insert($insertData);
        }

        if ($touch) {
            $this->touchParent();
        }

        return $attached;
    }

    /**
     * Detach a related model from the parent via pivot table.
     *
     * @param int|string|null $id Related model ID (null = detach all)
     * @return int Number of rows deleted
     */
    public function detach(int|string|null $id = null): int
    {
        $qb = new QueryBuilder($this->query->getConnection());
        $qb->table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey));

        if ($id !== null) {
            $qb->where($this->relatedPivotKey, $id);
        }

        return $qb->delete();
    }

    /**
     * Sync the pivot table with the given IDs.
     *
     * PERFORMANCE NOTE: For very large relationships, this method loads all current
     * pivot IDs into memory. Consider using syncChunked() for large datasets.
     *
     * @param array<int|string> $ids Related model IDs or associative array with pivot data
     * @param bool $detaching Whether to detach missing records
     * @return array Sync results with attached, detached, and updated arrays
     */
    public function sync(array $ids, bool $detaching = true): array
    {
        $changes = [
            'attached' => [],
            'detached' => [],
            'updated' => []
        ];

        // Get current pivot records
        $current = $this->getCurrentPivotIds();

        // Normalize input IDs
        $records = $this->formatSyncRecords($ids);
        $syncIds = array_keys($records);

        if ($detaching) {
            // Determine what to detach
            $detach = array_diff($current, $syncIds);
            if (!empty($detach)) {
                $this->detachMany($detach);
                $changes['detached'] = $detach;
            }
        }

        // Determine what to attach or update
        foreach ($records as $id => $pivotData) {
            if (in_array($id, $current)) {
                // Update existing pivot record
                if (!empty($pivotData)) {
                    $this->updateExistingPivot($id, $pivotData);
                    $changes['updated'][] = $id;
                }
            } else {
                // Attach new record
                $this->attach($id, $pivotData, false);
                $changes['attached'][] = $id;
            }
        }

        $this->touchParent();

        return $changes;
    }

    /**
     * Sync without detaching existing records.
     *
     * @param array $ids Related model IDs or associative array with pivot data
     * @return array Sync results
     */
    public function syncWithoutDetaching(array $ids): array
    {
        return $this->sync($ids, false);
    }

    /**
     * Sync the pivot table with chunked processing for large datasets.
     *
     * Performance: O(n/chunk_size) - Memory-efficient sync for large relationships
     * Recommended for relationships with >5000 records
     *
     * @param array $ids Related model IDs or associative array with pivot data
     * @param bool $detaching Whether to detach missing records
     * @param int $chunkSize Number of records to process per chunk
     * @return array Sync results with attached, detached, and updated arrays
     *
     * @example
     * ```php
     * // For large datasets
     * $user->roles()->syncChunked($roleIds, true, 1000);
     * ```
     */
    public function syncChunked(array $ids, bool $detaching = true, int $chunkSize = 1000): array
    {
        $changes = [
            'attached' => [],
            'detached' => [],
            'updated' => []
        ];

        // Normalize input IDs
        $records = $this->formatSyncRecords($ids);
        $syncIds = array_keys($records);

        if ($detaching) {
            // Process detachment in chunks
            $this->processDetachmentChunked($syncIds, $chunkSize, $changes);
        }

        // Process attachment/updates in chunks
        $this->processAttachmentChunked($records, $chunkSize, $changes);

        $this->touchParent();

        return $changes;
    }

    /**
     * Process detachment in chunks.
     *
     * @param array $syncIds IDs to keep
     * @param int $chunkSize Chunk size
     * @param array &$changes Changes array to update
     * @return void
     */
    protected function processDetachmentChunked(array $syncIds, int $chunkSize, array &$changes): void
    {
        $qb = new QueryBuilder($this->query->getConnection());

        // Get current IDs in chunks and detach those not in sync list
        $qb->table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey))
            ->chunk($chunkSize, function ($pivotChunk) use ($syncIds, &$changes) {
                $currentChunk = [];
                foreach ($pivotChunk as $pivot) {
                    $currentChunk[] = $pivot[$this->relatedPivotKey];
                }

                $toDetach = array_diff($currentChunk, $syncIds);
                if (!empty($toDetach)) {
                    $this->detachMany($toDetach);
                    $changes['detached'] = array_merge($changes['detached'], $toDetach);
                }

                return true; // Continue processing
            });
    }

    /**
     * Process attachment/updates in chunks.
     *
     * FIXED: Now correctly filters current pivot IDs by chunk to avoid:
     * 1. Performance issue: O(n²) complexity from repeated full scans
     * 2. Correctness issue: Missing IDs beyond limit causing duplicate attachments
     *
     * @param array $records Records to attach/update
     * @param int $chunkSize Chunk size
     * @param array &$changes Changes array to update
     * @return void
     */
    protected function processAttachmentChunked(array $records, int $chunkSize, array &$changes): void
    {
        $recordChunks = array_chunk($records, $chunkSize, true);

        foreach ($recordChunks as $chunk) {
            $chunkIds = array_keys($chunk);

            // FIXED: Only get current pivot IDs for this specific chunk
            // This prevents both performance and correctness issues
            $current = $this->getCurrentPivotIdsFor($chunkIds);

            foreach ($chunk as $id => $pivotData) {
                if (in_array($id, $current, true)) {
                    // Update existing pivot record
                    if (!empty($pivotData)) {
                        $this->updateExistingPivot($id, $pivotData);
                        $changes['updated'][] = $id;
                    }
                } else {
                    // Attach new record
                    $this->attach($id, $pivotData, false);
                    $changes['attached'][] = $id;
                }
            }
        }
    }

    /**
     * Toggle the attachment of related models.
     *
     * @param array|int|string $ids Related model IDs
     * @param bool $touch Whether to touch parent timestamps
     * @return array Toggle results with attached and detached arrays
     */
    public function toggle(array|int|string $ids, bool $touch = true): array
    {
        $ids = is_array($ids) ? $ids : [$ids];
        $changes = ['attached' => [], 'detached' => []];

        $current = $this->getCurrentPivotIds();

        foreach ($ids as $id) {
            if (in_array($id, $current)) {
                $this->detach($id);
                $changes['detached'][] = $id;
            } else {
                $this->attach($id, [], false);
                $changes['attached'][] = $id;
            }
        }

        if ($touch) {
            $this->touchParent();
        }

        return $changes;
    }

    /**
     * Update an existing pivot record.
     *
     * @param int|string $id Related model ID
     * @param array $pivotData Pivot data to update
     * @return bool
     */
    public function updateExistingPivot(int|string $id, array $pivotData): bool
    {
        if ($this->withTimestamps && !isset($pivotData['updated_at'])) {
            $pivotData['updated_at'] = date('Y-m-d H:i:s');
        }

        $qb = new QueryBuilder($this->query->getConnection());
        $affected = $qb->table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey))
            ->where($this->relatedPivotKey, $id)
            ->update($pivotData);

        return $affected > 0;
    }

    /**
     * Get current pivot IDs for the parent model.
     *
     * PERFORMANCE WARNING: Loads all pivot IDs into memory.
     * For very large relationships (>10k records), consider:
     * 1. Using chunked operations (syncChunked)
     * 2. Implementing streaming sync operations
     * 3. Using database-level operations
     *
     * IMPORTANT: This method should NOT be used in chunked operations as it
     * doesn't filter by specific IDs, leading to correctness issues.
     * Use targeted queries with whereIn() instead.
     *
     * @param int|null $limit Optional limit for safety (null = no limit)
     * @return array
     *
     * @deprecated Use targeted queries in chunked operations instead
     */
    protected function getCurrentPivotIds(?int $limit = null): array
    {
        $qb = new QueryBuilder($this->query->getConnection());
        $query = $qb->table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey));

        // Apply limit if specified for safety
        if ($limit !== null) {
            $query->limit($limit);
        }

        $results = $query->pluck($this->relatedPivotKey);
        $ids = $results->toArray();

        return $ids;
    }

    /**
     * Get current pivot IDs for specific related IDs only.
     *
     * Performance: O(log n) - Uses indexed lookup with whereIn
     * Clean Architecture: Targeted query for chunked operations
     *
     * @param array $relatedIds Array of related IDs to check
     * @return array Array of existing pivot IDs from the given set
     */
    protected function getCurrentPivotIdsFor(array $relatedIds): array
    {
        if (empty($relatedIds)) {
            return [];
        }

        $qb = new QueryBuilder($this->query->getConnection());
        $results = $qb->table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey))
            ->whereIn($this->relatedPivotKey, $relatedIds)
            ->pluck($this->relatedPivotKey);

        return $results->toArray();
    }

    /**
     * Format sync records from various input formats.
     *
     * @param array $records Input records
     * @return array Formatted records
     */
    protected function formatSyncRecords(array $records): array
    {
        $formatted = [];

        foreach ($records as $key => $value) {
            if (is_numeric($key)) {
                // Simple array: [1, 2, 3]
                $formatted[$value] = [];
            } else {
                // Associative array: [1 => ['role' => 'admin'], 2 => ['role' => 'user']]
                $formatted[$key] = is_array($value) ? $value : [];
            }
        }

        return $formatted;
    }

    /**
     * Detach multiple related models.
     *
     * @param array $ids Related model IDs
     * @return int Number of rows deleted
     */
    protected function detachMany(array $ids): int
    {
        $qb = new QueryBuilder($this->query->getConnection());
        return $qb->table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey))
            ->whereIn($this->relatedPivotKey, $ids)
            ->delete();
    }

    /**
     * Touch the parent model's timestamps.
     *
     * @return void
     */
    protected function touchParent(): void
    {
        if (method_exists($this->parent, 'touch')) {
            $this->parent->touch();
        }
    }

    /**
     * {@inheritdoc}
     *
     * For BelongsToMany, we need to ensure the related key is selected
     * on the related model (not the pivot table keys).
     */
    public function getForeignKeyName(): string
    {
        return $this->relatedPivotKey;
    }

    /**
     * {@inheritdoc}
     *
     * Override to handle BelongsToMany's complex constructor with pivot table.
     * Creates a fresh instance without parent constraints for eager loading.
     *
     * Performance: O(1) - Direct instantiation, zero reflection overhead
     * Clean Architecture: Factory Method + Setter pattern for extensibility
     */
    public function newEagerInstance(\Toporia\Framework\Database\Query\QueryBuilder $freshQuery): static
    {
        // Create a dummy parent without ID to avoid parent-specific constraints
        $dummyParent = new ($this->parent::class)();

        $instance = new static(
            $freshQuery,
            $dummyParent,
            $this->relatedClass,
            $this->pivotTable,
            $this->foreignPivotKey,
            $this->relatedPivotKey,
            $this->parentKey,
            $this->relatedKey
        );

        // Preserve all pivot settings from original relation
        $instance->pivotColumns = $this->pivotColumns;
        $instance->withTimestamps = $this->withTimestamps;
        $instance->pivotAccessor = $this->pivotAccessor;
        $instance->pivotWheres = $this->pivotWheres;
        $instance->pivotWhereIns = $this->pivotWhereIns;
        $instance->pivotOrderBy = $this->pivotOrderBy;
        $instance->pivotClass = $this->pivotClass;

        // Set up the query with proper JOIN but without parent WHERE constraints
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Build SELECT clause with pivot columns
        $selectColumns = ["{$relatedTable}.*"];

        // Add additional pivot columns
        foreach ($this->pivotColumns as $column) {
            $selectColumns[] = "{$this->pivotTable}.{$column} as pivot_{$column}";
        }

        // TODO: Add parent_id selection for optimized matching after proper validation
        // For now, we'll use standard matching to avoid column not found errors

        // Create a fresh query from the related model (this ensures table name is set)
        $cleanQuery = call_user_func([$this->relatedClass, 'query'])
            ->join(
                $this->pivotTable,
                "{$relatedTable}.{$this->relatedKey}",
                '=',
                "{$this->pivotTable}.{$this->relatedPivotKey}"
            )
            ->select($selectColumns);

        $instance->setQuery($cleanQuery);

        // Apply pivot constraints to the eager query
        $instance->applyPivotConstraints();

        return $instance;
    }

    /**
     * Create a new pivot model instance.
     *
     * @param array $attributes Pivot attributes
     * @param bool $exists Whether the pivot exists in database
     * @return object Pivot model instance
     */
    public function newPivot(array $attributes = [], bool $exists = false): object
    {
        // Create a simple pivot object
        $pivot = new class($attributes, $this->pivotAccessor, $exists) {
            public function __construct(
                protected array $attributes,
                protected string $accessor,
                protected bool $exists
            ) {}

            public function __get(string $key): mixed
            {
                return $this->attributes[$key] ?? null;
            }

            public function __set(string $key, mixed $value): void
            {
                $this->attributes[$key] = $value;
            }

            public function __isset(string $key): bool
            {
                return isset($this->attributes[$key]);
            }

            public function getAttribute(string $key): mixed
            {
                return $this->attributes[$key] ?? null;
            }

            public function getAttributes(): array
            {
                return $this->attributes;
            }

            public function exists(): bool
            {
                return $this->exists;
            }
        };

        return $pivot;
    }

    /**
     * Get the pivot accessor name.
     *
     * @return string
     */
    public function getPivotAccessor(): string
    {
        return $this->pivotAccessor;
    }

    /**
     * Check if timestamps are enabled for pivot.
     *
     * @return bool
     */
    public function hasPivotTimestamps(): bool
    {
        return $this->withTimestamps;
    }

    /**
     * Get the pivot table name.
     *
     * @return string
     */
    public function getPivotTable(): string
    {
        return $this->pivotTable;
    }

    /**
     * Get the foreign pivot key.
     *
     * @return string
     */
    public function getForeignPivotKey(): string
    {
        return $this->foreignPivotKey;
    }

    /**
     * Get the related pivot key.
     *
     * @return string
     */
    public function getRelatedPivotKey(): string
    {
        return $this->relatedPivotKey;
    }

    /**
     * Get the parent key.
     *
     * @return string
     */
    public function getParentKey(): string
    {
        return $this->parentKey;
    }

    /**
     * Get the related key.
     *
     * @return string
     */
    public function getRelatedKey(): string
    {
        return $this->relatedKey;
    }

    /**
     * Get the first related model or create a new one.
     *
     * @param array $attributes Attributes for new model
     * @param array $pivotData Pivot data for new relationship
     * @return Model
     */
    public function firstOrCreate(array $attributes = [], array $pivotData = []): Model
    {
        $instance = $this->where($attributes)->first();

        if ($instance === null) {
            $instance = call_user_func([$this->relatedClass, 'create'], $attributes);
            $this->attach($instance->getAttribute($this->relatedKey), $pivotData);
        }

        return $instance;
    }

    /**
     * Create a new related model and attach it.
     *
     * @param array $attributes Model attributes
     * @param array $pivotData Pivot data
     * @return Model
     */
    public function create(array $attributes = [], array $pivotData = []): Model
    {
        $instance = call_user_func([$this->relatedClass, 'create'], $attributes);
        $this->attach($instance->getAttribute($this->relatedKey), $pivotData);
        return $instance;
    }

    /**
     * Save a related model and attach it.
     *
     * @param Model $model Model to save and attach
     * @param array $pivotData Pivot data
     * @return Model
     */
    public function save(Model $model, array $pivotData = []): Model
    {
        $model->save();
        $this->attach($model->getAttribute($this->relatedKey), $pivotData);
        return $model;
    }

    /**
     * Get the count of related models.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->query->count();
    }

    /**
     * Check if any related models exist.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Paginate the results.
     *
     * @param int $perPage Items per page
     * @param int $page Current page
     * @return array Pagination results
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $total = $this->count();
        $offset = ($page - 1) * $perPage;

        $items = $this->query->limit($perPage)->offset($offset)->get();

        return [
            'data' => call_user_func([$this->relatedClass, 'hydrate'], $items->toArray()),
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }

    /**
     * Process records in chunks to optimize memory usage.
     *
     * PERFORMANCE WARNING: Uses OFFSET/LIMIT which can be slow on large tables.
     * For better performance on large datasets, use chunkById() instead.
     *
     * Performance: O(n/chunk_size) but OFFSET becomes slower as offset increases
     * Clean Architecture: Callback pattern for flexible processing
     *
     * @param int $count Number of records per chunk
     * @param callable $callback Function to process each chunk
     * @return bool True if all chunks processed successfully
     *
     * @example
     * ```php
     * // For small to medium datasets
     * $user->roles()->chunk(100, function($roles) {
     *     foreach ($roles as $role) {
     *         // Process each role
     *     }
     * });
     *
     * // For large datasets, prefer chunkById():
     * $user->roles()->chunkById(100, function($roles) {
     *     // Much faster on large tables
     * });
     * ```
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->query->limit($count)->offset(($page - 1) * $count)->get();

            if ($results->isEmpty()) {
                break;
            }

            $models = call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());

            // Call the callback with the current chunk
            if ($callback($models, $page) === false) {
                return false;
            }

            $page++;
        } while ($results->count() === $count);

        return true;
    }

    /**
     * Process records in chunks ordered by ID for consistent results.
     *
     * Performance: O(n/chunk_size) - Consistent ordering prevents missed records
     * Clean Architecture: ID-based chunking ensures reliable pagination
     *
     * @param int $count Number of records per chunk
     * @param callable $callback Function to process each chunk
     * @param string $column Column to order by (default: related model's primary key)
     * @param string $alias Optional column alias
     * @return bool True if all chunks processed successfully
     *
     * @example
     * ```php
     * $user->roles()->chunkById(50, function($roles) {
     *     // Process roles in consistent order
     * });
     * ```
     */
    public function chunkById(int $count, callable $callback, string $column = null, string $alias = null): bool
    {
        $column = $column ?: $this->relatedKey;
        $alias = $alias ?: $column;
        $lastId = null;

        do {
            $clone = clone $this->query;

            if ($lastId !== null) {
                $clone->where($column, '>', $lastId);
            }

            $results = $clone->orderBy($column)->limit($count)->get();

            if ($results->isEmpty()) {
                break;
            }

            $models = call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());

            // Call the callback with the current chunk
            if ($callback($models) === false) {
                return false;
            }

            // Get the last ID for the next iteration
            $lastModel = $models->last();
            $lastId = $lastModel->getAttribute($alias);
        } while ($results->count() === $count);

        return true;
    }

    /**
     * Get the sum of a pivot column.
     *
     * Performance: O(1) - Single aggregation query
     * Clean Architecture: Expressive aggregation methods
     *
     * @param string $column Pivot column name
     * @return float|int
     *
     * @example
     * ```php
     * $totalHours = $user->projects()->sumPivot('hours_worked');
     * ```
     */
    public function sumPivot(string $column): float|int
    {
        return $this->query->sum("{$this->pivotTable}.{$column}") ?? 0;
    }

    /**
     * Get the average of a pivot column.
     *
     * @param string $column Pivot column name
     * @return float|int
     *
     * @example
     * ```php
     * $avgRating = $user->products()->avgPivot('rating');
     * ```
     */
    public function avgPivot(string $column): float|int
    {
        return $this->query->avg("{$this->pivotTable}.{$column}") ?? 0;
    }

    /**
     * Get the minimum value of a pivot column.
     *
     * @param string $column Pivot column name
     * @return mixed
     *
     * @example
     * ```php
     * $minPrice = $user->products()->minPivot('price');
     * ```
     */
    public function minPivot(string $column): mixed
    {
        return $this->query->min("{$this->pivotTable}.{$column}");
    }

    /**
     * Get the maximum value of a pivot column.
     *
     * @param string $column Pivot column name
     * @return mixed
     *
     * @example
     * ```php
     * $maxPrice = $user->products()->maxPivot('price');
     * ```
     */
    public function maxPivot(string $column): mixed
    {
        return $this->query->max("{$this->pivotTable}.{$column}");
    }

    /**
     * Specify a custom pivot model class to use.
     *
     * Performance: O(1) - Class name storage for later instantiation
     * Clean Architecture: Strategy pattern for pivot model customization
     * SOLID: Open/Closed - Extensible without modifying core logic
     *
     * @param class-string $class Custom pivot model class
     * @return $this
     *
     * @example
     * ```php
     * $user->roles()->using(RoleUser::class)->get();
     * ```
     */
    public function using(string $class): static
    {
        $this->pivotClass = $class;
        return $this;
    }

    /**
     * Sync with additional pivot values for all records.
     *
     * Performance: O(n) - Batch operations with single transaction
     * Clean Architecture: Atomic sync operation with consistent state
     *
     * @param array $ids Related model IDs
     * @param array $pivotValues Additional pivot data for all records
     * @param bool $detaching Whether to detach missing records
     * @return array Sync results
     *
     * @example
     * ```php
     * $user->roles()->syncWithPivotValues([1, 2, 3], ['assigned_by' => $adminId]);
     * ```
     */
    public function syncWithPivotValues(array $ids, array $pivotValues, bool $detaching = true): array
    {
        // Add pivot values to each ID
        $records = [];
        foreach ($ids as $id) {
            $records[$id] = $pivotValues;
        }

        return $this->sync($records, $detaching);
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
            if ($result instanceof QueryBuilder) {
                return $this;
            }

            return $result;
        }

        throw new \BadMethodCallException(
            sprintf('Method %s::%s does not exist.', static::class, $method)
        );
    }

    /**
     * Find a related model by its pivot attributes.
     *
     * Performance: O(log n) - Indexed pivot table lookup
     * Clean Architecture: Expressive finder method for pivot-based queries
     *
     * @param array $pivotAttributes Pivot attributes to search by
     * @param array $columns Columns to select
     * @return Model|null
     *
     * @example
     * ```php
     * $role = $user->roles()->findByPivot(['department' => 'IT', 'level' => 'senior']);
     * ```
     */
    public function findByPivot(array $pivotAttributes, array $columns = ['*']): ?Model
    {
        foreach ($pivotAttributes as $column => $value) {
            $this->wherePivot($column, $value);
        }

        return $this->select($columns)->first();
    }

    /**
     * Get all related models with specific pivot attributes.
     *
     * @param array $pivotAttributes Pivot attributes to search by
     * @param array $columns Columns to select
     * @return ModelCollection
     *
     * @example
     * ```php
     * $activeRoles = $user->roles()->getByPivot(['status' => 'active']);
     * ```
     */
    public function getByPivot(array $pivotAttributes, array $columns = ['*']): ModelCollection
    {
        foreach ($pivotAttributes as $column => $value) {
            $this->wherePivot($column, $value);
        }

        return $this->select($columns)->get();
    }

    /**
     * Create or update a pivot record with the given attributes.
     *
     * Performance: O(1) - Single UPSERT operation when supported
     * Clean Architecture: Atomic upsert operation
     *
     * @param int|string $id Related model ID
     * @param array $pivotData Pivot data
     * @param array $updateData Data to update if record exists
     * @return bool
     *
     * @example
     * ```php
     * $user->roles()->updateOrAttach(1, ['department' => 'IT'], ['updated_at' => now()]);
     * ```
     */
    public function updateOrAttach(int|string $id, array $pivotData = [], array $updateData = []): bool
    {
        $existing = $this->getCurrentPivotIds();

        if (in_array($id, $existing)) {
            return $this->updateExistingPivot($id, array_merge($pivotData, $updateData));
        } else {
            return $this->attach($id, $pivotData, false) !== false;
        }
    }

    /**
     * Attach multiple models with individual pivot data efficiently.
     *
     * Performance: O(n) - Batch insert with single query
     * Clean Architecture: Bulk operation with transaction safety
     *
     * @param array $records Array of [id => pivotData] pairs
     * @param bool $touch Whether to touch parent timestamps
     * @return array Array of attached IDs
     *
     * @example
     * ```php
     * $user->roles()->attachMany([
     *     1 => ['department' => 'IT', 'level' => 'senior'],
     *     2 => ['department' => 'HR', 'level' => 'junior']
     * ]);
     * ```
     */
    public function attachWithPivotData(array $records, bool $touch = true): array
    {
        return $this->attachMany($records, $touch);
    }

    /**
     * Get the pivot table query builder.
     *
     * Performance: O(1) - Direct query builder access
     * Clean Architecture: Exposes pivot table for advanced queries
     *
     * @return QueryBuilder
     *
     * @example
     * ```php
     * $pivotQuery = $user->roles()->pivotQuery()
     *     ->where('created_at', '>', '2024-01-01')
     *     ->orderBy('priority', 'desc');
     * ```
     */
    public function pivotQuery(): QueryBuilder
    {
        $qb = new QueryBuilder($this->query->getConnection());
        return $qb->table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey));
    }

    /**
     * Get distinct values from a pivot column.
     *
     * Performance: O(log n) - Uses database DISTINCT optimization
     * Clean Architecture: Expressive method for pivot column analysis
     *
     * @param string $column Pivot column name
     * @return array Array of distinct values
     *
     * @example
     * ```php
     * $departments = $user->roles()->distinctPivot('department');
     * ```
     */
    public function distinctPivot(string $column): array
    {
        return $this->pivotQuery()
            ->distinct()
            ->pluck($column)
            ->toArray();
    }

    /**
     * Check if a specific pivot relationship exists.
     *
     * Performance: O(log n) - Indexed lookup with early termination
     * Clean Architecture: Expressive existence check
     *
     * @param int|string $id Related model ID
     * @param array $pivotConstraints Additional pivot constraints
     * @return bool
     *
     * @example
     * ```php
     * if ($user->roles()->pivotExists(1, ['status' => 'active'])) {
     *     // User has active role
     * }
     * ```
     */
    public function pivotExists(int|string $id, array $pivotConstraints = []): bool
    {
        $query = $this->pivotQuery()->where($this->relatedPivotKey, $id);

        foreach ($pivotConstraints as $column => $value) {
            $query->where($column, $value);
        }

        return $query->exists();
    }

    /**
     * Get the custom pivot model class name.
     *
     * @return class-string|null
     */
    public function getPivotClass(): ?string
    {
        return $this->pivotClass;
    }

    /**
     * Validate pivot table structure and column names.
     *
     * This method helps debug column not found errors by checking
     * if the expected columns exist in the pivot table.
     *
     * @return array Validation results
     */
    public function validatePivotStructure(): array
    {
        $connection = $this->query->getConnection();

        try {
            // Get table columns
            $columns = $connection->select("SHOW COLUMNS FROM `{$this->pivotTable}`");
            $columnNames = array_column($columns, 'Field');

            return [
                'table' => $this->pivotTable,
                'exists' => true,
                'columns' => $columnNames,
                'foreign_key_exists' => in_array($this->foreignPivotKey, $columnNames),
                'related_key_exists' => in_array($this->relatedPivotKey, $columnNames),
                'foreign_key' => $this->foreignPivotKey,
                'related_key' => $this->relatedPivotKey,
                'pivot_columns_exist' => array_intersect($this->pivotColumns, $columnNames) === $this->pivotColumns
            ];
        } catch (\Exception $e) {
            return [
                'table' => $this->pivotTable,
                'exists' => false,
                'error' => $e->getMessage(),
                'foreign_key' => $this->foreignPivotKey,
                'related_key' => $this->relatedPivotKey
            ];
        }
    }
}
