<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};
use Toporia\Framework\Database\Query\{QueryBuilder, RowCollection};
use Toporia\Framework\Support\Str;

/**
 * MorphToMany Relationship
 *
 * Handles polymorphic many-to-many relationships.
 * Example: Post/Video morphToMany Tags
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.1.0
 * @package     toporia/framework
 * @subpackage  Relations
 * @since       2025-01-10
 */
class MorphToMany extends Relation
{
    /** @var string Pivot table name */
    protected string $pivotTable;

    /** @var string Morph type column */
    protected string $morphType;

    /** @var string Related pivot key */
    protected string $relatedPivotKey;

    /** @var string Related key */
    protected string $relatedKey;

    /** @var array<string> Additional pivot columns to select */
    protected array $pivotColumns = [];

    /** @var bool Whether to include timestamps in pivot table */
    protected bool $withTimestamps = false;

    /** @var string Custom pivot accessor name */
    protected string $pivotAccessor = 'pivot';

    /** @var array<array{column: string, operator: string, value: mixed}> Pivot where constraints */
    protected array $pivotWheres = [];

    /** @var array<array{column: string, values: array}> Pivot whereIn constraints */
    protected array $pivotWhereIns = [];

    /** @var array<array{column: string, direction: string}> Pivot order by clauses */
    protected array $pivotOrderBy = [];

    /** @var class-string|null Custom pivot model class */
    protected ?string $pivotClass = null;

    /** @var string|null Cached related table name */
    private ?string $relatedTableCache = null;

    /**
     * @param QueryBuilder $query Query builder
     * @param Model $parent Parent model (Post or Video)
     * @param class-string<Model> $relatedClass Related model class (Tag)
     * @param string $morphName Morph name ('taggable')
     * @param string|null $pivotTable Pivot table name (taggables)
     * @param string|null $morphType Type column (taggable_type)
     * @param string|null $morphId ID column (taggable_id)
     * @param string|null $relatedKey Related key (tag_id)
     * @param string|null $parentKey Parent key (id)
     * @param string|null $relatedPrimaryKey Related primary key (id)
     */
    public function __construct(
        QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        protected string $morphName,
        ?string $pivotTable = null,
        ?string $morphType = null,
        ?string $morphId = null,
        ?string $relatedKey = null,
        ?string $parentKey = null,
        ?string $relatedPrimaryKey = null
    ) {
        $this->pivotTable = $pivotTable ?? $this->guessPivotTable();
        $this->morphType = $morphType ?? "{$morphName}_type";
        $this->foreignKey = $morphId ?? "{$morphName}_id";
        $this->relatedPivotKey = $relatedKey ?? $this->guessRelatedKey();
        $this->localKey = $parentKey ?? $parent::getPrimaryKey();
        $this->relatedKey = $relatedPrimaryKey ?? $this->relatedClass::getPrimaryKey();

        parent::__construct($query, $parent, $this->foreignKey, $this->localKey);

        $this->performJoin();
    }

    /**
     * {@inheritdoc}
     */
    protected function getRelatedClass(): string
    {
        return $this->relatedClass;
    }

    // =========================================================================
    // TABLE NAME HELPERS (with caching)
    // =========================================================================

    /**
     * Get cached related table name.
     */
    protected function getRelatedTable(): string
    {
        return $this->relatedTableCache ??= $this->relatedClass::getTableName();
    }

    /**
     * Guess pivot table name.
     */
    protected function guessPivotTable(): string
    {
        return $this->morphName . 's';
    }

    /**
     * Guess related key name.
     */
    protected function guessRelatedKey(): string
    {
        $parts = explode('\\', $this->relatedClass);
        return strtolower(end($parts)) . '_id';
    }

    /**
     * Get morph class name for parent.
     */
    protected function getMorphClass(): string
    {
        return get_class($this->parent);
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Create a new pivot query builder.
     */
    protected function newPivotQuery(): QueryBuilder
    {
        return (new QueryBuilder($this->query->getConnection()))
            ->table($this->pivotTable)
            ->where($this->foreignKey, $this->parent->getAttribute($this->localKey))
            ->where($this->morphType, $this->getMorphClass());
    }

    /**
     * Build dictionary for eager loading matching.
     *
     * OPTIMIZED: Uses pivot data from main query (with pivot_ prefix) instead of separate query.
     *
     * @return array<string, array<Model>>
     */
    protected function buildDictionary(ModelCollection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            // Get morphType and foreignKey from pivot attributes (with pivot_ prefix)
            // These are selected in newEagerInstance() with alias pivot_*
            $type = $result->getAttribute("pivot_{$this->morphType}");
            $id = $result->getAttribute("pivot_{$this->foreignKey}");

            if ($type !== null && $id !== null) {
                $dictionary["{$type}:{$id}"][] = $result;
            }
        }

        return $dictionary;
    }

    // =========================================================================
    // CORE RELATION METHODS
    // =========================================================================

    /**
     * Perform join with pivot table.
     */
    protected function performJoin(): void
    {
        $relatedTable = $this->getRelatedTable();

        $this->query->join(
            $this->pivotTable,
            "{$relatedTable}.{$this->relatedKey}",
            '=',
            "{$this->pivotTable}.{$this->relatedPivotKey}"
        );

        if ($this->parent->exists()) {
            $this->query->where("{$this->pivotTable}.{$this->morphType}", $this->getMorphClass());
            $this->query->where("{$this->pivotTable}.{$this->foreignKey}", $this->parent->getAttribute($this->localKey));
        }

        // Apply soft delete scope if related model uses soft deletes
        $this->applySoftDeleteScope($this->query, $this->relatedClass, $relatedTable);
    }

    /**
     * {@inheritdoc}
     */
    public function getResults(): ModelCollection
    {
        $relatedTable = $this->getRelatedTable();

        if ($this->parent->exists()) {
            $freshQuery = $this->query->newQuery()->table($relatedTable);

            $freshQuery->join(
                $this->pivotTable,
                "{$relatedTable}.{$this->relatedKey}",
                '=',
                "{$this->pivotTable}.{$this->relatedPivotKey}"
            );

            $freshQuery->where("{$this->pivotTable}.{$this->morphType}", $this->getMorphClass());
            $freshQuery->where("{$this->pivotTable}.{$this->foreignKey}", $this->parent->getAttribute($this->localKey));
            $freshQuery->select("{$relatedTable}.*");

            $rowCollection = $freshQuery->get();
        } else {
            $this->query->select("{$relatedTable}.*");
            $rowCollection = $this->query->get();
        }

        $rows = $rowCollection instanceof RowCollection ? $rowCollection->all() : $rowCollection;

        return empty($rows) ? new ModelCollection([]) : $this->relatedClass::hydrate($rows);
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models): void
    {
        $relatedTable = $this->getRelatedTable();

        $types = [];
        foreach ($models as $model) {
            $type = get_class($model);
            $types[$type][] = $model->getAttribute($this->localKey);
        }

        $this->query = $this->query->newQuery()->table($relatedTable);

        $this->query->join(
            $this->pivotTable,
            "{$relatedTable}.{$this->relatedKey}",
            '=',
            "{$this->pivotTable}.{$this->relatedPivotKey}"
        );

        $pivotTable = $this->pivotTable;
        $morphType = $this->morphType;
        $foreignKey = $this->foreignKey;

        $this->query->where(function ($q) use ($types, $pivotTable, $morphType, $foreignKey) {
            $first = true;
            foreach ($types as $type => $ids) {
                $callback = fn($subQ) => $subQ->where("{$pivotTable}.{$morphType}", $type)
                    ->whereIn("{$pivotTable}.{$foreignKey}", $ids);

                $first ? $q->where($callback) : $q->orWhere($callback);
                $first = false;
            }
        });

        // Select related table columns and pivot columns with alias for consistency
        $this->query->select("{$relatedTable}.*");

        // Always select morphType and foreignKey for matching (required for eager loading)
        $this->query->selectRaw("{$this->pivotTable}.{$this->morphType} as pivot_{$this->morphType}");
        $this->query->selectRaw("{$this->pivotTable}.{$this->foreignKey} as pivot_{$this->foreignKey}");

        // Only select additional pivot columns if we should include pivot object
        if ($this->shouldIncludePivot()) {
            // Add other pivot columns
            foreach ($this->pivotColumns as $column) {
                // Skip if already added (morphType or foreignKey might be in pivotColumns)
                if ($column !== $this->morphType && $column !== $this->foreignKey) {
                    $this->query->selectRaw("{$this->pivotTable}.{$column} as pivot_{$column}");
                }
            }
        }

        // Apply soft delete scope if related model uses soft deletes
        $this->applySoftDeleteScope($this->query, $this->relatedClass, $relatedTable);
    }

    /**
     * {@inheritdoc}
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

        $dictionary = $this->buildDictionary($results);

        // Build index of related models by ID for O(1) lookup
        $relatedIndex = [];
        foreach ($results as $result) {
            $relatedId = $result->getAttribute($this->relatedKey);
            if ($relatedId !== null) {
                $relatedIdKey = (string) $relatedId;
                // Only keep first instance if duplicate (pivot data will be extracted)
                if (!isset($relatedIndex[$relatedIdKey])) {
                    // Remove pivot_* attributes from the first instance to keep it clean
                    /** @var Model $result */
                    $this->removePivotAttributes($result);
                    $relatedIndex[$relatedIdKey] = $result;
                }
            }
        }

        foreach ($models as $model) {
            $key = get_class($model) . ':' . $model->getAttribute($this->localKey);
            $matchedResults = $dictionary[$key] ?? [];

            // Process matched results and attach pivot data if needed
            $matched = [];
            foreach ($matchedResults as $matchedResult) {
                $relatedId = $matchedResult->getAttribute($this->relatedKey);
                if ($relatedId === null) {
                    continue;
                }

                $relatedIdKey = (string) $relatedId;

                // Clone the related model to avoid sharing pivot data
                if (isset($relatedIndex[$relatedIdKey])) {
                    $relatedModel = clone $relatedIndex[$relatedIdKey];
                } else {
                    // Fallback: use the matched result but remove pivot attributes
                    $relatedModel = clone $matchedResult;
                    $this->removePivotAttributes($relatedModel);
                }

                // Only create and attach pivot object if we should include it
                if ($this->shouldIncludePivot()) {
                    // Build pivot data from pivot_* attributes
                    $pivotData = [
                        $this->morphType => $matchedResult->getAttribute("pivot_{$this->morphType}"),
                        $this->foreignKey => $matchedResult->getAttribute("pivot_{$this->foreignKey}"),
                        $this->relatedPivotKey => $relatedId,
                    ];

                    // Add other pivot columns
                    foreach ($this->pivotColumns as $column) {
                        if ($column !== $this->morphType && $column !== $this->foreignKey && $column !== $this->relatedPivotKey) {
                            $pivotValue = $matchedResult->getAttribute("pivot_{$column}");
                            if ($pivotValue !== null) {
                                $pivotData[$column] = $pivotValue;
                            }
                        }
                    }

                    // Add timestamps if enabled
                    if ($this->withTimestamps) {
                        $createdAt = $matchedResult->getAttribute('pivot_created_at');
                        $updatedAt = $matchedResult->getAttribute('pivot_updated_at');
                        if ($createdAt !== null) {
                            $pivotData['created_at'] = $createdAt;
                        }
                        if ($updatedAt !== null) {
                            $pivotData['updated_at'] = $updatedAt;
                        }
                    }

                    // Clear any existing relations on the cloned model
                    $relatedModel->setRelation($this->pivotAccessor, null);

                    // Create and attach pivot object
                    $pivotModel = $this->newPivot($pivotData, true);
                    $relatedModel->setRelation($this->pivotAccessor, $pivotModel);
                }

                $matched[] = $relatedModel;
            }

            $model->setRelation($relationName, new ModelCollection($matched));
        }

        return $models;
    }

    /**
     * Check if pivot object should be included in the relationship.
     *
     * Pivot object is only included when:
     * - withPivot() is called with columns
     * - withTimestamps() is called
     *
     * @return bool
     */
    protected function shouldIncludePivot(): bool
    {
        return !empty($this->pivotColumns) || $this->withTimestamps;
    }

    /**
     * Remove pivot_* attributes from model.
     *
     * These attributes are only needed during matching and should not appear
     * in the final model attributes. Pivot data should only be in the pivot relation.
     *
     * OPTIMIZED: Uses Model's removeAttributesByPattern() method instead of reflection
     * for better performance.
     *
     * @param \Toporia\Framework\Database\ORM\Model $model Model instance to clean
     * @return void
     */
    protected function removePivotAttributes(\Toporia\Framework\Database\ORM\Model $model): void
    {
        // Use Model's protected method to remove attributes efficiently
        // This avoids reflection overhead and is much faster
        $model->removeAttributesByPattern('pivot_');
    }

    /**
     * Create a new pivot model instance.
     *
     * @param array $attributes Pivot attributes
     * @param bool $exists Whether the pivot exists in database
     * @return \Toporia\Framework\Database\ORM\Pivot Pivot model instance
     */
    protected function newPivot(array $attributes = [], bool $exists = false): \Toporia\Framework\Database\ORM\Pivot
    {
        if ($this->pivotClass !== null) {
            return new ($this->pivotClass)($attributes, $this->pivotTable, $exists);
        }

        return new \Toporia\Framework\Database\ORM\Pivot($attributes, $this->pivotTable, $exists);
    }

    /**
     * {@inheritdoc}
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * {@inheritdoc}
     */
    public function newEagerInstance(QueryBuilder $freshQuery): static
    {
        $instance = new static(
            $freshQuery,
            $this->parent,
            $this->relatedClass,
            $this->morphName,
            $this->pivotTable,
            $this->morphType,
            $this->foreignKey,
            $this->relatedPivotKey,
            $this->localKey,
            $this->relatedKey
        );

        $instance->pivotColumns = $this->pivotColumns;
        $instance->withTimestamps = $this->withTimestamps;
        $instance->pivotAccessor = $this->pivotAccessor;

        // Set up the query with proper JOIN but without parent WHERE constraints
        $relatedTable = $this->relatedClass::getTableName();

        $cleanQuery = $this->relatedClass::query()
            ->join(
                $this->pivotTable,
                "{$relatedTable}.{$this->relatedKey}",
                '=',
                "{$this->pivotTable}.{$this->relatedPivotKey}"
            );

        // Build SELECT clause with pivot columns using selectRaw for proper alias handling
        // This ensures pivot columns are selected with correct aliases
        $cleanQuery->select("{$relatedTable}.*");

        // Always select morphType and foreignKey from pivot table (required for matching)
        $cleanQuery->selectRaw("{$this->pivotTable}.{$this->morphType} as pivot_{$this->morphType}");
        $cleanQuery->selectRaw("{$this->pivotTable}.{$this->foreignKey} as pivot_{$this->foreignKey}");

        // Only select additional pivot columns if we should include pivot object
        if ($this->shouldIncludePivot()) {
            // Add other pivot columns
            foreach ($this->pivotColumns as $column) {
                // Skip if already added (morphType or foreignKey might be in pivotColumns)
                if ($column !== $this->morphType && $column !== $this->foreignKey) {
                    $cleanQuery->selectRaw("{$this->pivotTable}.{$column} as pivot_{$column}");
                }
            }
        }

        $instance->setQuery($cleanQuery);

        // Copy where constraints from original query (excluding pivot and parent-specific constraints)
        $pivotTablePrefix = $this->pivotTable . '.';
        $this->copyWhereConstraints($cleanQuery, [
            $this->morphType,
            $this->foreignKey,
            fn($col) => Str::startsWith($col, $pivotTablePrefix) ||
                $col === $this->morphType ||
                $col === $this->foreignKey ||
                Str::endsWith($col, '.' . $this->morphType) ||
                Str::endsWith($col, '.' . $this->foreignKey)
        ]);

        // Apply pivot constraints separately
        $instance->applyPivotConstraintsToQuery($instance->getQuery());

        // Apply soft delete scope if related model uses soft deletes
        $instance->applySoftDeleteScope($cleanQuery, $this->relatedClass, $relatedTable);

        return $instance;
    }

    // =========================================================================
    // PIVOT CONFIGURATION METHODS
    // =========================================================================

    /**
     * Specify additional pivot columns to include.
     */
    public function withPivot(string ...$columns): static
    {
        $this->pivotColumns = [...$this->pivotColumns, ...$columns];
        return $this;
    }

    /**
     * Include timestamps in pivot table.
     */
    public function withTimestamps(): static
    {
        $this->withTimestamps = true;
        return $this->withPivot('created_at', 'updated_at');
    }

    /**
     * Customize the pivot accessor name.
     */
    public function as(string $accessor): static
    {
        $this->pivotAccessor = $accessor;
        return $this;
    }

    /**
     * Specify a custom pivot model class.
     */
    public function using(string $class): static
    {
        $this->pivotClass = $class;
        return $this;
    }

    // =========================================================================
    // PIVOT CONSTRAINT METHODS
    // =========================================================================

    /**
     * Add a where constraint on the pivot table.
     */
    public function wherePivot(string $column, mixed $operator, mixed $value = null): static
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->pivotWheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value];
        $this->applyPivotWhere($column, $operator, $value);

        return $this;
    }

    /**
     * Apply a pivot where constraint to the query.
     */
    protected function applyPivotWhere(string $column, string $operator, mixed $value): void
    {
        $qualifiedColumn = "{$this->pivotTable}.{$column}";

        match (true) {
            $operator === 'BETWEEN' && is_array($value) => $this->query->whereBetween($qualifiedColumn, $value),
            $operator === 'NOT BETWEEN' && is_array($value) => $this->query->whereNotBetween($qualifiedColumn, $value),
            $operator === 'IS' && $value === null => $this->query->whereNull($qualifiedColumn),
            $operator === 'IS NOT' && $value === null => $this->query->whereNotNull($qualifiedColumn),
            default => $this->query->where($qualifiedColumn, $operator, $value)
        };
    }

    /**
     * Add a whereIn constraint on the pivot table.
     */
    public function wherePivotIn(string $column, array $values): static
    {
        $this->pivotWhereIns[] = ['column' => $column, 'values' => $values];
        $this->query->whereIn("{$this->pivotTable}.{$column}", $values);

        return $this;
    }

    /**
     * Add a whereNotIn constraint on the pivot table.
     */
    public function wherePivotNotIn(string $column, array $values): static
    {
        $this->pivotWhereIns[] = ['column' => $column, 'values' => $values, 'not' => true];
        $this->query->whereNotIn("{$this->pivotTable}.{$column}", $values);

        return $this;
    }

    /**
     * Add an order by clause on the pivot table.
     */
    public function orderByPivot(string $column, string $direction = 'asc'): static
    {
        $direction = strtolower($direction);
        $this->pivotOrderBy[] = ['column' => $column, 'direction' => $direction];
        $this->query->orderBy("{$this->pivotTable}.{$column}", $direction);

        return $this;
    }

    /**
     * Add date-based pivot constraint.
     */
    public function wherePivotDate(string $column, string $operator, string $value): static
    {
        $this->query->whereRaw("DATE({$this->pivotTable}.{$column}) {$operator} ?", [$value]);
        $this->pivotWheres[] = ['column' => "DATE({$column})", 'operator' => $operator, 'value' => $value];

        return $this;
    }

    /**
     * Add month-based pivot constraint.
     */
    public function wherePivotMonth(string $column, string $operator, int $value): static
    {
        $this->query->whereRaw("MONTH({$this->pivotTable}.{$column}) {$operator} ?", [$value]);
        $this->pivotWheres[] = ['column' => "MONTH({$column})", 'operator' => $operator, 'value' => $value];

        return $this;
    }

    /**
     * Add year-based pivot constraint.
     */
    public function wherePivotYear(string $column, string $operator, int $value): static
    {
        $this->query->whereRaw("YEAR({$this->pivotTable}.{$column}) {$operator} ?", [$value]);
        $this->pivotWheres[] = ['column' => "YEAR({$column})", 'operator' => $operator, 'value' => $value];

        return $this;
    }

    // =========================================================================
    // CRUD OPERATIONS
    // =========================================================================

    /**
     * Create a new related model and attach it.
     */
    public function create(array $attributes = [], array $pivotData = []): Model
    {
        $instance = $this->relatedClass::create($attributes);
        $this->attach($instance->getAttribute($this->relatedKey), $pivotData);

        return $instance;
    }

    /**
     * Save a related model and attach it.
     */
    public function save(Model $model, array $pivotData = []): Model
    {
        $model->save();
        $this->attach($model->getAttribute($this->relatedKey), $pivotData);

        return $model;
    }

    /**
     * Attach models to the relationship.
     */
    public function attach(int|string|array $id, array $pivotData = []): array|bool
    {
        if (is_array($id)) {
            return $this->attachMany($id);
        }

        $data = [
            $this->relatedPivotKey => $id,
            $this->foreignKey => $this->parent->getAttribute($this->localKey),
            $this->morphType => $this->getMorphClass(),
            ...$pivotData
        ];

        if ($this->withTimestamps) {
            $now = now()->toDateTimeString();
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
        }

        (new QueryBuilder($this->query->getConnection()))
            ->table($this->pivotTable)
            ->insert($data);

        return true;
    }

    /**
     * Attach multiple related models.
     */
    protected function attachMany(array $ids): array
    {
        $attached = [];
        $insertData = [];
        $now = $this->withTimestamps ? now()->toDateTimeString() : null;

        foreach ($ids as $key => $value) {
            [$relatedId, $pivotData] = is_numeric($key)
                ? [$value, []]
                : [$key, is_array($value) ? $value : []];

            $data = [
                $this->relatedPivotKey => $relatedId,
                $this->foreignKey => $this->parent->getAttribute($this->localKey),
                $this->morphType => $this->getMorphClass(),
                ...$pivotData
            ];

            if ($now) {
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
            }

            $insertData[] = $data;
            $attached[] = $relatedId;
        }

        if ($insertData !== []) {
            (new QueryBuilder($this->query->getConnection()))
                ->table($this->pivotTable)
                ->insert($insertData);
        }

        return $attached;
    }

    /**
     * Detach models from the relationship.
     */
    public function detach(mixed $ids = null): int
    {
        $query = $this->newPivotQuery();

        if ($ids !== null) {
            $ids = is_array($ids) ? $ids : [$ids];
            $query->whereIn($this->relatedPivotKey, $ids);
        }

        return $query->delete();
    }

    /**
     * Soft delete related models through this relationship.
     *
     * If related model uses soft deletes, this will soft delete the related models
     * and detach them from the pivot table. Otherwise, it will only detach.
     *
     * Performance: O(n) - Single UPDATE query for soft delete + DELETE for pivot
     *
     * @param int|string|array|null $ids Related model ID(s) (null = soft delete all)
     * @return int Number of models soft deleted
     *
     * @example
     * ```php
     * // Soft delete a specific tag from post
     * $post->tags()->softDelete(1);
     *
     * // Soft delete multiple tags
     * $post->tags()->softDelete([1, 2, 3]);
     *
     * // Soft delete all tags
     * $post->tags()->softDelete();
     * ```
     */
    public function softDelete(int|string|array|null $ids = null): int
    {
        // If related model doesn't use soft deletes, just detach
        if (!$this->relatedModelUsesSoftDeletes($this->relatedClass)) {
            return $this->detach($ids);
        }

        // Get related model IDs to soft delete
        $relatedIds = [];
        if ($ids === null) {
            // Get all related IDs from pivot table
            $qb = new QueryBuilder($this->query->getConnection());
            $pivotRows = $qb->table($this->pivotTable)
                ->where($this->foreignKey, $this->parent->getAttribute($this->localKey))
                ->where($this->morphType, $this->getMorphClass())
                ->pluck($this->relatedPivotKey);
            $relatedIds = $pivotRows->toArray();
        } else {
            $relatedIds = is_array($ids) ? $ids : [$ids];
        }

        if (empty($relatedIds)) {
            return 0;
        }

        // Soft delete related models
        $deletedAtColumn = $this->getDeletedAtColumn($this->relatedClass);
        $relatedKey = $this->relatedKey;

        $softDeleted = $this->relatedClass::query()
            ->whereIn($relatedKey, $relatedIds)
            ->whereNull($deletedAtColumn) // Only soft delete non-deleted records
            ->update([$deletedAtColumn => now()->toDateTimeString()]);

        // Detach from pivot table
        if ($softDeleted > 0) {
            $this->detach($relatedIds);
        }

        return $softDeleted;
    }

    /**
     * Restore soft-deleted related models through this relationship.
     *
     * Restores soft-deleted related models and optionally re-attaches them to pivot table.
     *
     * Performance: O(n) - Single UPDATE query for restore
     *
     * @param int|string|array $ids Related model ID(s) to restore
     * @param bool $reattach Whether to re-attach to pivot table after restore
     * @return int Number of models restored
     *
     * @example
     * ```php
     * // Restore a tag
     * $post->tags()->restore(1);
     *
     * // Restore and re-attach
     * $post->tags()->restore(1, true);
     * ```
     */
    public function restore(int|string|array $ids, bool $reattach = false): int
    {
        // If related model doesn't use soft deletes, return 0
        if (!$this->relatedModelUsesSoftDeletes($this->relatedClass)) {
            return 0;
        }

        $relatedIds = is_array($ids) ? $ids : [$ids];
        if (empty($relatedIds)) {
            return 0;
        }

        // Restore related models
        $deletedAtColumn = $this->getDeletedAtColumn($this->relatedClass);
        $restored = $this->relatedClass::withTrashed()
            ->whereIn($this->relatedKey, $relatedIds)
            ->whereNotNull($deletedAtColumn) // Only restore soft-deleted records
            ->update([$deletedAtColumn => null]);

        // Re-attach to pivot table if requested
        if ($restored > 0 && $reattach) {
            $this->attachMany($relatedIds);
        }

        return $restored;
    }

    /**
     * Sync the relationship with the given IDs.
     */
    public function sync(array $ids, bool $detaching = true): array
    {
        $changes = ['attached' => [], 'detached' => [], 'updated' => []];
        $current = $this->getCurrentPivotIds();
        $records = $this->formatSyncRecords($ids);
        $syncIds = array_keys($records);

        if ($detaching) {
            $detach = array_diff($current, $syncIds);
            if ($detach !== []) {
                $this->detach($detach);
                $changes['detached'] = array_values($detach);
            }
        }

        foreach ($records as $id => $pivotData) {
            if (in_array($id, $current)) {
                if ($pivotData !== []) {
                    $this->updateExistingPivot($id, $pivotData);
                    $changes['updated'][] = $id;
                }
            } else {
                $this->attach($id, $pivotData);
                $changes['attached'][] = $id;
            }
        }

        return $changes;
    }

    /**
     * Toggle the attachment of related models.
     */
    public function toggle(array|int|string $ids): array
    {
        $ids = is_array($ids) ? $ids : [$ids];
        $changes = ['attached' => [], 'detached' => []];
        $current = $this->getCurrentPivotIds();

        foreach ($ids as $id) {
            if (in_array($id, $current)) {
                $this->detach($id);
                $changes['detached'][] = $id;
            } else {
                $this->attach($id);
                $changes['attached'][] = $id;
            }
        }

        return $changes;
    }

    /**
     * Update an existing pivot record.
     */
    public function updateExistingPivot(int|string $id, array $pivotData): bool
    {
        if ($this->withTimestamps && !isset($pivotData['updated_at'])) {
            $pivotData['updated_at'] = now()->toDateTimeString();
        }

        $affected = $this->newPivotQuery()
            ->where($this->relatedPivotKey, $id)
            ->update($pivotData);

        return $affected > 0;
    }

    /**
     * Sync with additional pivot values for all records.
     */
    public function syncWithPivotValues(array $ids, array $pivotValues, bool $detaching = true): array
    {
        $records = array_fill_keys($ids, $pivotValues);

        return $this->sync($records, $detaching);
    }

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * Check if any related models exist.
     */
    public function exists(): bool
    {
        return $this->parent->exists() && $this->query->exists();
    }

    /**
     * Get the count of related models.
     */
    public function count(): int
    {
        return $this->parent->exists() ? $this->query->count() : 0;
    }

    /**
     * Get the first related model or create a new one.
     */
    public function firstOrCreate(array $attributes = [], array $pivotData = []): Model
    {
        return $this->query->first() ?? $this->create($attributes, $pivotData);
    }

    /**
     * Process records in chunks.
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->query->limit($count)->offset(($page - 1) * $count)->get();

            if ($results->isEmpty()) {
                break;
            }

            $models = $this->relatedClass::hydrate($results->toArray());

            if ($callback($models, $page) === false) {
                return false;
            }

            $page++;
        } while ($results->count() === $count);

        return true;
    }

    /**
     * Process records in chunks ordered by ID.
     */
    public function chunkById(int $count, callable $callback, ?string $column = null, ?string $alias = null): bool
    {
        $column ??= $this->relatedKey;
        $alias ??= $column;
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

            $models = $this->relatedClass::hydrate($results->toArray());

            if ($callback($models) === false) {
                return false;
            }

            $lastId = $models->last()->getAttribute($alias);
        } while ($results->count() === $count);

        return true;
    }

    // =========================================================================
    // PIVOT QUERY METHODS
    // =========================================================================

    /**
     * Get current pivot IDs for the parent model.
     */
    protected function getCurrentPivotIds(): array
    {
        return $this->newPivotQuery()->pluck($this->relatedPivotKey)->toArray();
    }

    /**
     * Format sync records from various input formats.
     */
    protected function formatSyncRecords(array $records): array
    {
        $formatted = [];

        foreach ($records as $key => $value) {
            $formatted[is_numeric($key) ? $value : $key] = is_numeric($key) ? [] : (is_array($value) ? $value : []);
        }

        return $formatted;
    }

    /**
     * Get the pivot table query builder.
     */
    public function pivotQuery(): QueryBuilder
    {
        return $this->newPivotQuery();
    }

    /**
     * Check if a specific pivot relationship exists.
     */
    public function pivotExists(int|string $id, array $pivotConstraints = []): bool
    {
        $query = $this->newPivotQuery()->where($this->relatedPivotKey, $id);

        foreach ($pivotConstraints as $column => $value) {
            $query->where($column, $value);
        }

        return $query->exists();
    }

    /**
     * Find a related model by pivot attributes.
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
     */
    public function getByPivot(array $pivotAttributes, array $columns = ['*']): ModelCollection
    {
        foreach ($pivotAttributes as $column => $value) {
            $this->wherePivot($column, $value);
        }

        return $this->select($columns)->get();
    }

    // =========================================================================
    // PIVOT AGGREGATION METHODS
    // =========================================================================

    /**
     * Get sum of a pivot column.
     */
    public function sumPivot(string $column): float|int
    {
        return $this->newPivotQuery()->sum($column) ?? 0;
    }

    /**
     * Get average of a pivot column.
     */
    public function avgPivot(string $column): float|int
    {
        return $this->newPivotQuery()->avg($column) ?? 0;
    }

    /**
     * Get minimum value of a pivot column.
     */
    public function minPivot(string $column): mixed
    {
        return $this->newPivotQuery()->min($column);
    }

    /**
     * Get maximum value of a pivot column.
     */
    public function maxPivot(string $column): mixed
    {
        return $this->newPivotQuery()->max($column);
    }

    /**
     * Get distinct values from a pivot column.
     */
    public function distinctPivot(string $column): array
    {
        return $this->newPivotQuery()->distinct()->pluck($column)->toArray();
    }

    // =========================================================================
    // GETTER METHODS
    // =========================================================================

    /**
     * Get the morph name.
     */
    public function getMorphName(): string
    {
        return $this->morphName;
    }

    /**
     * Get the pivot table name.
     */
    public function getPivotTable(): string
    {
        return $this->pivotTable;
    }

    /**
     * Get the related pivot key.
     */
    public function getRelatedPivotKey(): string
    {
        return $this->relatedPivotKey;
    }

    /**
     * Get the morph type column name.
     */
    public function getMorphType(): string
    {
        return $this->morphType;
    }

    // =========================================================================
    // LEGACY METHODS (for backward compatibility)
    // =========================================================================

    /**
     * @deprecated Use attach() instead
     */
    public function attachOriginal(mixed $ids): bool
    {
        $this->attach(is_array($ids) ? $ids : [$ids]);

        return true;
    }

    /**
     * @deprecated Use sync() instead
     */
    public function syncOriginal(mixed $ids): void
    {
        $this->detach();
        $this->attach(is_array($ids) ? $ids : [$ids]);
    }

    // =========================================================================
    // MAGIC METHODS
    // =========================================================================

    /**
     * Magic method to delegate calls to the query builder.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this->query, $method)) {
            $result = $this->query->{$method}(...$parameters);

            return $result instanceof QueryBuilder ? $this : $result;
        }

        throw new \BadMethodCallException(
            sprintf('Method %s::%s does not exist.', static::class, $method)
        );
    }
}
