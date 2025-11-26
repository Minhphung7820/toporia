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

        return $this;
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
        $this->pivotOrderBy[] = [
            'column' => $column,
            'direction' => strtolower($direction)
        ];

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
     * For BelongsToMany, we need to query the pivot table to determine
     * which related models belong to which parent models.
     *
     * Performance: O(n + m) where n = parents, m = results
     * - Single query to get pivot mappings
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
        $pivotRows = $qb->table($this->pivotTable)
            ->select($this->foreignPivotKey, $this->relatedPivotKey)
            ->whereIn($this->foreignPivotKey, array_unique($parentIds))
            ->whereIn($this->relatedPivotKey, array_unique($relatedIds))
            ->get();

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
     * @return array
     */
    protected function getCurrentPivotIds(): array
    {
        $qb = new QueryBuilder($this->query->getConnection());
        $results = $qb->table($this->pivotTable)
            ->where($this->foreignPivotKey, $this->parent->getAttribute($this->parentKey))
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

        // Set up the query with proper JOIN but without parent WHERE constraints
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Build SELECT clause with pivot columns
        $selectColumns = ["{$relatedTable}.*"];
        foreach ($this->pivotColumns as $column) {
            $selectColumns[] = "{$this->pivotTable}.{$column} as pivot_{$column}";
        }

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
}
