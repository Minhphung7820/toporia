<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};
use Toporia\Framework\Database\Query\{QueryBuilder, RowCollection};

/**
 * MorphMany Relationship
 *
 * Handles polymorphic one-to-many relationships.
 * Example: Post/Video morphMany Comments
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.1.0
 * @package     toporia/framework
 * @subpackage  Relations
 * @since       2025-01-10
 */
class MorphMany extends Relation
{
    /** @var string Morph type column name */
    protected string $morphType;

    /** @var string|null Cached related table name */
    private ?string $relatedTableCache = null;

    /**
     * @param QueryBuilder $query Query builder for related model
     * @param Model $parent Parent model instance (Post or Video)
     * @param class-string<Model> $relatedClass Related model class (Comment)
     * @param string $morphName Morph name ('commentable')
     * @param string|null $morphType Type column (commentable_type)
     * @param string|null $morphId ID column (commentable_id)
     * @param string|null $localKey Local key on parent (id)
     */
    public function __construct(
        QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        protected string $morphName,
        ?string $morphType = null,
        ?string $morphId = null,
        ?string $localKey = null
    ) {
        $this->morphType = $morphType ?? "{$morphName}_type";
        $this->foreignKey = $morphId ?? "{$morphName}_id";
        $this->localKey = $localKey ?? $parent::getPrimaryKey();

        parent::__construct($query, $parent, $this->foreignKey, $this->localKey);

        $this->addConstraints();
    }

    /**
     * {@inheritdoc}
     */
    protected function getRelatedClass(): string
    {
        return $this->relatedClass;
    }

    /**
     * Add basic WHERE constraint based on parent model.
     *
     * OPTIMIZED: Automatically applies soft delete scope if related model uses soft deletes.
     *
     * @return $this
     */
    public function addConstraints(): static
    {
        if ($this->parent->exists()) {
            $this->query->where($this->morphType, $this->getMorphClass());
            $this->query->where($this->foreignKey, $this->parent->getAttribute($this->localKey));
        }

        // Apply soft delete scope if related model uses soft deletes
        $this->applySoftDeleteScope($this->query, $this->relatedClass);

        return $this;
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

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Get parent key or throw exception.
     *
     * @throws \RuntimeException If parent key is null
     */
    protected function getParentKeyOrFail(): mixed
    {
        $parentKey = $this->parent->getAttribute($this->localKey);

        if ($parentKey === null) {
            throw new \RuntimeException('Cannot perform operation: parent model does not have a key');
        }

        return $parentKey;
    }

    /**
     * Build dictionary for eager loading matching.
     *
     * @return array<string, array<Model>>
     */
    protected function buildDictionary(ModelCollection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $type = $result->getAttribute($this->morphType);
            $id = $result->getAttribute($this->foreignKey);
            $key = "{$type}:{$id}";

            $dictionary[$key][] = $result;
        }

        return $dictionary;
    }

    // =========================================================================
    // CORE RELATION METHODS
    // =========================================================================

    /**
     * Get morph class name for parent.
     *
     * @return string
     */
    protected function getMorphClass(): string
    {
        // Use full class name to match database storage
        // Can be customized via getMorphClass() method on model
        return get_class($this->parent);
    }

    /**
     * {@inheritdoc}
     */
    public function getResults(): ModelCollection
    {
        if ($this->parent->exists()) {
            $freshQuery = $this->query->newQuery();
            $freshQuery->table($this->getRelatedTable());

            $freshQuery->where($this->morphType, $this->getMorphClass());
            $freshQuery->where($this->foreignKey, $this->parent->getAttribute($this->localKey));

            $rowCollection = $freshQuery->get();
        } else {
            $rowCollection = $this->query->get();
        }

        $rows = $rowCollection instanceof RowCollection
            ? $rowCollection->all()
            : $rowCollection;

        if (empty($rows)) {
            return new ModelCollection([]);
        }

        return $this->relatedClass::hydrate($rows);
    }

    /**
     * {@inheritdoc}
     *
     * Eager loading optimization with closures:
     * Groups models by type and loads in minimal queries.
     *
     * Example: Loading comments for 50 Posts and 30 Videos
     * Single query with nested WHERE:
     * WHERE (type='Post' AND id IN (?,?,...)) OR (type='Video' AND id IN (?,?,...))
     *
     * Performance: 1 query instead of 80! O(N) where N = distinct types
     */
    public function addEagerConstraints(array $models): void
    {
        // Group models by type (full class name) for efficient loading
        $types = [];
        foreach ($models as $model) {
            $type = get_class($model);

            if (!isset($types[$type])) {
                $types[$type] = [];
            }
            $types[$type][] = $model->getAttribute($this->localKey);
        }

        // Build nested WHERE with closures
        // WHERE (type='Post' AND id IN (...)) OR (type='Video' AND id IN (...))
        $this->query->where(function ($q) use ($types) {
            $first = true;
            foreach ($types as $type => $ids) {
                if ($first) {
                    $q->where(function ($subQ) use ($type, $ids) {
                        $subQ->where($this->morphType, $type)
                            ->whereIn($this->foreignKey, $ids);
                    });
                    $first = false;
                } else {
                    $q->orWhere(function ($subQ) use ($type, $ids) {
                        $subQ->where($this->morphType, $type)
                            ->whereIn($this->foreignKey, $ids);
                    });
                }
            }
        });

        // Apply soft delete scope if related model uses soft deletes
        $this->applySoftDeleteScope($this->query, $this->relatedClass);
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $type = get_class($model);
            $id = $model->getAttribute($this->localKey);
            $key = "{$type}:{$id}";

            $related = $dictionary[$key] ?? [];
            $model->setRelation($relationName, new ModelCollection($related));
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
     * {@inheritdoc}
     */
    public function newEagerInstance(QueryBuilder $freshQuery): static
    {
        $instance = new static(
            $freshQuery,
            $this->parent,
            $this->relatedClass,
            $this->morphName,
            $this->morphType,
            $this->foreignKey,
            $this->localKey
        );

        $newQuery = $freshQuery->newQuery();
        $instance->setQuery($newQuery);

        // Copy where constraints from original query (excluding parent-specific morph constraints)
        $this->copyWhereConstraints($newQuery, [
            $this->morphType,
            $this->foreignKey,
            fn($col) => $col === $this->morphType || $col === $this->foreignKey
        ]);

        return $instance;
    }

    // =========================================================================
    // CRUD OPERATIONS
    // =========================================================================

    /**
     * Create a new related model.
     */
    public function create(array $attributes = []): Model
    {
        $attributes[$this->foreignKey] = $this->getParentKeyOrFail();
        $attributes[$this->morphType] = $this->getMorphClass();

        return $this->relatedClass::create($attributes);
    }

    /**
     * Save a related model.
     */
    public function save(Model $model): Model
    {
        $model->setAttribute($this->foreignKey, $this->getParentKeyOrFail());
        $model->setAttribute($this->morphType, $this->getMorphClass());
        $model->save();

        return $model;
    }

    /**
     * Save multiple related models.
     */
    public function saveMany(array $models): ModelCollection
    {
        if (empty($models)) {
            return new ModelCollection([]);
        }

        $parentKey = $this->getParentKeyOrFail();
        $morphClass = $this->getMorphClass();

        foreach ($models as &$attributes) {
            $attributes[$this->foreignKey] = $parentKey;
            $attributes[$this->morphType] = $morphClass;
        }

        return $this->bulkInsert($models);
    }

    /**
     * Create multiple related models.
     */
    public function createMany(array $models): ModelCollection
    {
        return $this->saveMany($models);
    }

    /**
     * Bulk insert models for performance.
     */
    protected function bulkInsert(array $models): ModelCollection
    {
        $table = $this->getRelatedTable();
        $now = now()->toDateTimeString();

        $preparedModels = [];
        foreach ($models as $attributes) {
            $attributes['created_at'] ??= $now;
            $attributes['updated_at'] ??= $now;
            $preparedModels[] = $attributes;
        }

        $allColumns = [];
        foreach ($preparedModels as $attributes) {
            $allColumns = [...$allColumns, ...array_keys($attributes)];
        }
        $columns = array_unique($allColumns);
        sort($columns);

        $values = [];
        $placeholders = [];

        foreach ($preparedModels as $attributes) {
            $rowValues = [];
            foreach ($columns as $column) {
                $rowValues[] = $attributes[$column] ?? null;
            }
            $placeholders[] = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            $values = [...$values, ...$rowValues];
        }

        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES " . implode(', ', $placeholders);
        $connection = $this->query->getConnection();
        $connection->execute($sql, $values);

        $lastInsertId = (int) $connection->lastInsertId();
        $insertedIds = range($lastInsertId, $lastInsertId + count($preparedModels) - 1);

        $savedModels = [];
        foreach ($insertedIds as $index => $id) {
            $model = new $this->relatedClass($preparedModels[$index]);
            $model->setAttribute('id', $id);
            $savedModels[] = $model;
        }

        return new ModelCollection($savedModels);
    }

    /**
     * Update all related models.
     */
    public function update(array $attributes): int
    {
        return $this->parent->exists() ? $this->query->update($attributes) : 0;
    }

    /**
     * Delete all related models.
     */
    public function delete(): int
    {
        return $this->parent->exists() ? $this->query->delete() : 0;
    }

    /**
     * Soft delete related models through this relationship.
     *
     * If related model uses soft deletes, this will soft delete the related models.
     * Otherwise, it will perform a hard delete.
     *
     * Performance: O(1) - Single UPDATE query for soft delete
     *
     * @return int Number of models soft deleted
     *
     * @example
     * ```php
     * // Soft delete all comments for a post
     * $post->comments()->softDelete();
     * ```
     */
    public function softDelete(): int
    {
        if (!$this->parent->exists()) {
            return 0;
        }

        // If related model doesn't use soft deletes, perform hard delete
        if (!$this->relatedModelUsesSoftDeletes($this->relatedClass)) {
            return $this->delete();
        }

        // Soft delete related models
        $deletedAtColumn = $this->getDeletedAtColumn($this->relatedClass);

        return $this->query
            ->where($this->morphType, $this->getMorphClass())
            ->whereNull($deletedAtColumn) // Only soft delete non-deleted records
            ->update([$deletedAtColumn => now()->toDateTimeString()]);
    }

    /**
     * Restore soft-deleted related models through this relationship.
     *
     * Restores soft-deleted related models.
     *
     * Performance: O(1) - Single UPDATE query for restore
     *
     * @return int Number of models restored
     *
     * @example
     * ```php
     * // Restore all soft-deleted comments for a post
     * $post->comments()->restore();
     * ```
     */
    public function restore(): int
    {
        if (!$this->parent->exists()) {
            return 0;
        }

        // If related model doesn't use soft deletes, return 0
        if (!$this->relatedModelUsesSoftDeletes($this->relatedClass)) {
            return 0;
        }

        // Restore related models
        $deletedAtColumn = $this->getDeletedAtColumn($this->relatedClass);

        return $this->relatedClass::withTrashed()
            ->where($this->morphType, $this->getMorphClass())
            ->where($this->foreignKey, $this->parent->getAttribute($this->localKey))
            ->whereNotNull($deletedAtColumn) // Only restore soft-deleted records
            ->update([$deletedAtColumn => null]);
    }

    /**
     * Get the first related model or create a new one.
     */
    public function firstOrCreate(array $attributes = []): Model
    {
        return $this->query->first() ?? $this->create($attributes);
    }

    /**
     * Get the first related model or instantiate a new one.
     */
    public function firstOrNew(array $attributes = []): Model
    {
        $instance = $this->query->first();

        if ($instance instanceof Model) {
            return $instance;
        }

        $attributes[$this->foreignKey] = $this->parent->getAttribute($this->localKey);
        $attributes[$this->morphType] = $this->getMorphClass();

        return new $this->relatedClass($attributes);
    }

    /**
     * Update or create a related model.
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        $query = clone $this->query;

        foreach ($attributes as $column => $value) {
            $query->where($column, $value);
        }

        $instance = $query->first();

        if ($instance instanceof Model) {
            $instance->fill([...$attributes, ...$values]);
            $instance->save();
            return $instance;
        }

        return $this->create([...$attributes, ...$values]);
    }

    // =========================================================================
    // QUERY METHODS
    // =========================================================================

    /**
     * Process records in chunks to optimize memory usage.
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
     * Get the count of related models.
     */
    public function count(): int
    {
        return $this->parent->exists() ? $this->query->count() : 0;
    }

    /**
     * Check if any related models exist.
     */
    public function exists(): bool
    {
        return $this->parent->exists() && $this->query->exists();
    }

    // =========================================================================
    // AGGREGATION METHODS
    // =========================================================================

    /**
     * Get the sum of a column.
     */
    public function sum(string $column): float|int
    {
        return $this->query->sum($column) ?? 0;
    }

    /**
     * Get the average of a column.
     */
    public function avg(string $column): float|int
    {
        return $this->query->avg($column) ?? 0;
    }

    /**
     * Get the minimum value of a column.
     */
    public function min(string $column): mixed
    {
        return $this->query->min($column);
    }

    /**
     * Get the maximum value of a column.
     */
    public function max(string $column): mixed
    {
        return $this->query->max($column);
    }

    /**
     * Paginate the results.
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $total = $this->count();
        $offset = ($page - 1) * $perPage;

        $items = $this->query->limit($perPage)->offset($offset)->get();
        $models = $this->relatedClass::hydrate($items->toArray());

        return [
            'data' => $models,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }

    /**
     * Paginate using cursor-based pagination (high-performance for large datasets).
     *
     * Cursor pagination provides O(1) performance regardless of dataset size,
     * making it ideal for large datasets (millions+ records).
     *
     * Performance Benefits:
     * - No COUNT query overhead
     * - O(1) query time with indexed WHERE clause
     * - Consistent results even with concurrent inserts/deletes
     * - Works efficiently with millions of records
     *
     * Usage:
     * ```php
     * // First page
     * $paginator = $post->comments()->cursorPaginate(50);
     *
     * // Next page (using cursor from previous response)
     * $paginator = $post->comments()->cursorPaginate(50, $request->get('cursor'));
     * ```
     *
     * @param int $perPage Number of items per page
     * @param string|null $cursor Cursor value from previous page (null for first page)
     * @param string|null $column Column to use as cursor (default: primary key)
     * @param string|null $path Base URL path for pagination links
     * @param string|null $baseUrl Base URL (scheme + host) for building full URLs
     * @param string $cursorName Query parameter name for cursor (default: 'cursor')
     * @return \Toporia\Framework\Support\Pagination\CursorPaginator
     *
     * @throws \InvalidArgumentException If perPage is invalid
     */
    public function cursorPaginate(
        int $perPage = 15,
        ?string $cursor = null,
        ?string $column = null,
        ?string $path = null,
        ?string $baseUrl = null,
        string $cursorName = 'cursor'
    ): \Toporia\Framework\Support\Pagination\CursorPaginator {
        // Validate parameters
        if ($perPage < 1) {
            throw new \InvalidArgumentException('Per page must be at least 1');
        }

        // Determine cursor column (default to primary key)
        if ($column === null) {
            $column = $this->relatedClass::getPrimaryKey();
        }

        // Get related table name
        $relatedTable = $this->relatedClass::getTableName();
        $fullColumn = "{$relatedTable}.{$column}";

        // Clone query to avoid modifying original
        $query = clone $this->query;

        // Get current order direction for cursor column
        $orderDirection = $this->getOrderDirectionForColumn($query, $fullColumn) ?? 'ASC';

        // Apply cursor constraint if provided
        if ($cursor !== null) {
            $cursorValue = $this->decodeCursor($cursor, $column);
            if ($cursorValue !== null) {
                // Performance: Use indexed WHERE clause (O(1) lookup)
                if ($orderDirection === 'ASC') {
                    $query->where($fullColumn, '>', $cursorValue);
                } else {
                    $query->where($fullColumn, '<', $cursorValue);
                }
            }
        }

        // Ensure ordering by cursor column for consistent pagination
        $query = $this->ensureOrderByCursorColumn($query, $fullColumn, $orderDirection);

        // Performance: Fetch one extra item to determine if there are more pages
        $items = $query->limit($perPage + 1)->get();
        $rows = $items instanceof \Toporia\Framework\Database\Query\RowCollection ? $items->toArray() : $items;
        $models = $this->relatedClass::hydrate($rows);

        // Determine if there are more pages
        $hasMore = $models->count() > $perPage;

        // Remove the extra item if it exists
        if ($hasMore) {
            $models = $models->take($perPage);
        }

        // Get cursors for next and previous pages
        $nextCursor = null;
        $prevCursor = null;

        if ($hasMore && $models->isNotEmpty()) {
            // Get the last item's cursor value
            $lastItem = $models->last();
            $nextCursorValue = $lastItem->getAttribute($column);
            $nextCursor = $this->encodeCursor($nextCursorValue, $column);
        }

        // Previous cursor is the current cursor (for backward navigation)
        $prevCursor = $cursor;

        return new \Toporia\Framework\Support\Pagination\CursorPaginator(
            items: $models,
            perPage: $perPage,
            nextCursor: $nextCursor,
            prevCursor: $prevCursor,
            hasMore: $hasMore,
            path: $path,
            baseUrl: $baseUrl,
            cursorName: $cursorName
        );
    }

    // =========================================================================
    // GETTER METHODS
    // =========================================================================

    /**
     * Get the morph type column name.
     */
    public function getMorphType(): string
    {
        return $this->morphType;
    }

    /**
     * Get the morph class value.
     */
    public function getMorphClassValue(): string
    {
        return $this->getMorphClass();
    }

    /**
     * Check if the parent is of a specific morph type.
     */
    public function isType(string $type): bool
    {
        return $this->getMorphClass() === $type;
    }


    // =========================================================================
    // MAGIC METHODS
    // =========================================================================

    /**
     * Magic method to delegate calls to the underlying query builder.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this->query, $method)) {
            $result = $this->query->{$method}(...$parameters);

            if ($result instanceof QueryBuilder) {
                return $this;
            }

            return $result;
        }

        throw new \BadMethodCallException(
            sprintf('Method %s::%s does not exist.', static::class, $method)
        );
    }

    // =========================================================================
    // FINDER METHODS
    // =========================================================================

    /**
     * Get related models with specific attributes.
     */
    public function getBy(array $attributes, array $columns = ['*']): ModelCollection
    {
        $query = clone $this->query;

        foreach ($attributes as $column => $value) {
            $query->where($column, $value);
        }

        $results = $query->select($columns)->get();
        return $this->relatedClass::hydrate($results->toArray());
    }

    /**
     * Get the latest related models.
     */
    public function latest(int $limit = 10, string $column = 'created_at'): ModelCollection
    {
        $results = $this->query->orderBy($column, 'desc')->limit($limit)->get();
        return $this->relatedClass::hydrate($results->toArray());
    }

    /**
     * Get the oldest related models.
     */
    public function oldest(int $limit = 10, string $column = 'created_at'): ModelCollection
    {
        $results = $this->query->orderBy($column, 'asc')->limit($limit)->get();
        return $this->relatedClass::hydrate($results->toArray());
    }

    /**
     * Sync related models by morph type and unique column.
     */
    public function syncBy(array $models, string $uniqueColumn): array
    {
        $changes = ['created' => [], 'updated' => [], 'deleted' => []];

        if (empty($models)) {
            return $changes;
        }

        $parentKey = $this->getParentKeyOrFail();
        $morphClass = $this->getMorphClass();

        $existing = $this->query->get();
        $existingByUnique = [];
        foreach ($existing as $model) {
            $key = $model->getAttribute($uniqueColumn);
            if ($key !== null) {
                $existingByUnique[$key] = $model;
            }
        }

        $processedKeys = [];
        foreach ($models as $modelData) {
            if (!is_array($modelData)) {
                continue;
            }

            $uniqueValue = $modelData[$uniqueColumn] ?? null;
            if ($uniqueValue === null) {
                continue;
            }

            $processedKeys[] = $uniqueValue;

            if (isset($existingByUnique[$uniqueValue])) {
                $existingModel = $existingByUnique[$uniqueValue];
                foreach ($modelData as $key => $value) {
                    $existingModel->setAttribute($key, $value);
                }
                $existingModel->save();
                $changes['updated'][] = $existingModel;
            } else {
                $modelData[$this->foreignKey] = $parentKey;
                $modelData[$this->morphType] = $morphClass;
                $newModel = $this->relatedClass::create($modelData);
                $changes['created'][] = $newModel;
            }
        }

        foreach ($existingByUnique as $key => $model) {
            if (!in_array($key, $processedKeys, true)) {
                $model->delete();
                $changes['deleted'][] = $model;
            }
        }

        return $changes;
    }

    // =========================================================================
    // TEMPORAL METHODS
    // =========================================================================

    /**
     * Get models created within a date range.
     */
    public function createdBetween(string $startDate, string $endDate, string $column = 'created_at'): ModelCollection
    {
        $results = $this->query->whereBetween($column, [$startDate, $endDate])->get();
        return $this->relatedClass::hydrate($results->toArray());
    }

    /**
     * Get models created today.
     */
    public function createdToday(string $column = 'created_at'): ModelCollection
    {
        $today = now()->toDateString();
        $results = $this->query->whereRaw("DATE({$column}) = ?", [$today])->get();
        return $this->relatedClass::hydrate($results->toArray());
    }

    // =========================================================================
    // MORPH TYPE METHODS
    // =========================================================================

    /**
     * Get models by morph type.
     */
    public function morphedBy(string $morphType): ModelCollection
    {
        $results = $this->query->where($this->morphType, $morphType)->get();
        return $this->relatedClass::hydrate($results->toArray());
    }
}
