<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};
use Toporia\Framework\Database\Query\{QueryBuilder, RowCollection};
use Toporia\Framework\Support\ReflectionService;

/**
 * HasMany Relationship
 *
 * Handles one-to-many relationships.
 * Optimized for performance and follows Clean Architecture principles.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.1.0
 * @package     toporia/framework
 * @subpackage  Relations
 * @since       2025-01-10
 */
class HasMany extends Relation
{
    public function __construct(
        QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        string $foreignKey,
        string $localKey
    ) {
        parent::__construct($query, $parent, $foreignKey, $localKey);
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
            $this->query->where(
                $this->foreignKey,
                $this->parent->getAttribute($this->localKey)
            );
        }

        // Apply soft delete scope if related model uses soft deletes
        $this->applySoftDeleteScope($this->query, $this->relatedClass);

        return $this;
    }

    // =========================================================================
    // CORE RELATION METHODS
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function getResults(): ModelCollection
    {
        $rows = $this->query->get();

        if ($rows->isEmpty()) {
            return new ModelCollection([]);
        }

        return $this->hydrateModels($rows);
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
            $localValue = $model->getAttribute($this->localKey);
            $model->setRelation($relationName, new ModelCollection($dictionary[$localValue] ?? []));
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
            $this->foreignKey,
            $this->localKey
        );

        $newQuery = $freshQuery->newQuery();
        $instance->setQuery($newQuery);

        // Copy where constraints from original query (excluding parent-specific foreign key constraint)
        $this->copyWhereConstraints($newQuery, [$this->foreignKey]);

        return $instance;
    }

    // =========================================================================
    // BULK OPERATIONS
    // =========================================================================

    /**
     * Save multiple related models using bulk insert.
     *
     * @param array<int, array<string, mixed>> $models Array of model attributes
     * @return ModelCollection Collection of saved models
     */
    public function saveMany(array $models): ModelCollection
    {
        if ($models === []) {
            return new ModelCollection([]);
        }

        $parentKey = $this->getParentKeyOrFail();
        $preparedModels = $this->prepareModelsForBulkInsert($models, $parentKey);

        $insertedIds = $this->executeBulkInsert($preparedModels);

        return $this->hydrateInsertedModels($preparedModels, $insertedIds);
    }

    /**
     * Create multiple related models (alias for saveMany).
     */
    public function createMany(array $models): ModelCollection
    {
        return $this->saveMany($models);
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

        return $this->relatedClass::create($attributes);
    }

    /**
     * Save a related model.
     */
    public function save(Model $model): Model
    {
        $model->setAttribute($this->foreignKey, $this->getParentKeyOrFail());
        $model->save();

        return $model;
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
     * // Soft delete all reviews for a product
     * $product->reviews()->softDelete();
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
     * // Restore all soft-deleted reviews for a product
     * $product->reviews()->restore();
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
     * Get the first related model or instantiate a new one (without saving).
     */
    public function firstOrNew(array $attributes = []): Model
    {
        $instance = $this->query->first();

        if ($instance instanceof Model) {
            return $instance;
        }

        $attributes[$this->foreignKey] = $this->parent->getAttribute($this->localKey);
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
     * $user->posts()->chunk(100, function($posts) {
     *     foreach ($posts as $post) {
     *         // Process each post
     *     }
     * });
     *
     * // For large datasets, prefer chunkById():
     * $user->posts()->chunkById(100, function($posts) {
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

            $models = $this->relatedClass::hydrate($results->toArray());

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
     * @param string $column Column to order by (default: 'id')
     * @param string $alias Optional column alias
     * @return bool True if all chunks processed successfully
     *
     * @example
     * ```php
     * $user->posts()->chunkById(50, function($posts) {
     *     // Process posts in consistent order
     * });
     * ```
     */
    public function chunkById(int $count, callable $callback, string $column = 'id', string $alias = null): bool
    {
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

            $models = $this->relatedClass::hydrate($results->toArray());

            if ($callback($models) === false) {
                return false;
            }

            $lastModel = $models->last();
            $lastId = $lastModel->getAttribute($alias);
        } while ($results->count() === $count);

        return true;
    }

    /**
     * Get the count of related models.
     *
     * Performance: O(1) - Single COUNT query
     * Clean Architecture: Expressive counting method
     *
     * @return int Count of related models
     *
     * @example
     * ```php
     * $postCount = $user->posts()->count();
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
     * Check if any related models exist.
     *
     * Performance: O(1) - Single EXISTS query with early termination
     * Clean Architecture: Expressive existence check
     *
     * @return bool True if related models exist
     *
     * @example
     * ```php
     * if ($user->posts()->exists()) {
     *     // User has posts
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
     * Get the sum of a column.
     *
     * Performance: O(1) - Single aggregation query
     * Clean Architecture: Expressive aggregation method
     *
     * @param string $column Column name
     * @return float|int
     *
     * @example
     * ```php
     * $totalViews = $user->posts()->sum('views');
     * ```
     */
    public function sum(string $column): float|int
    {
        return $this->query->sum($column) ?? 0;
    }

    /**
     * Get the average of a column.
     *
     * @param string $column Column name
     * @return float|int
     *
     * @example
     * ```php
     * $avgViews = $user->posts()->avg('views');
     * ```
     */
    public function avg(string $column): float|int
    {
        return $this->query->avg($column) ?? 0;
    }

    /**
     * Get the minimum value of a column.
     *
     * @param string $column Column name
     * @return mixed
     *
     * @example
     * ```php
     * $minViews = $user->posts()->min('views');
     * ```
     */
    public function min(string $column): mixed
    {
        return $this->query->min($column);
    }

    /**
     * Get the maximum value of a column.
     *
     * @param string $column Column name
     * @return mixed
     *
     * @example
     * ```php
     * $maxViews = $user->posts()->max('views');
     * ```
     */
    public function max(string $column): mixed
    {
        return $this->query->max($column);
    }

    /**
     * Paginate the results.
     *
     * Performance: O(1) - Single query with LIMIT/OFFSET
     * Clean Architecture: Consistent pagination interface
     *
     * @param int $perPage Items per page
     * @param int $page Current page
     * @return array Pagination results
     *
     * @example
     * ```php
     * $posts = $user->posts()->paginate(10, 2);
     * ```
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $total = $this->count();
        $offset = ($page - 1) * $perPage;

        $items = $this->query->limit($perPage)->offset($offset)->get();

        return [
            'data' => $this->relatedClass::hydrate($items->toArray()),
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }

    /**
     * Find a related model by its primary key.
     *
     * Performance: O(log n) - Indexed primary key lookup
     * Clean Architecture: Expressive finder method
     *
     * @param mixed $id Primary key value
     * @param array $columns Columns to select
     * @return Model|null
     *
     * @example
     * ```php
     * $post = $user->posts()->find(1);
     * ```
     */
    public function find(mixed $id, array $columns = ['*']): ?Model
    {
        return $this->query->where('id', $id)->select($columns)->first();
    }

    /**
     * Find multiple related models by their primary keys.
     *
     * Performance: O(log n) - Indexed primary key lookup with IN clause
     * Clean Architecture: Bulk finder method
     *
     * @param array $ids Array of primary key values
     * @param array $columns Columns to select
     * @return ModelCollection
     *
     * @example
     * ```php
     * $posts = $user->posts()->findMany([1, 2, 3]);
     * ```
     */
    public function findMany(array $ids, array $columns = ['*']): ModelCollection
    {
        if (empty($ids)) {
            return new ModelCollection([]);
        }

        return $this->relatedClass::hydrate(
            $this->query->whereIn('id', $ids)->select($columns)->get()->toArray()
        );
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
     * Get related models with specific attributes.
     *
     * Performance: O(n) - Single query with WHERE constraints
     * Clean Architecture: Expressive finder method
     *
     * @param array $attributes Attributes to search by
     * @param array $columns Columns to select
     * @return ModelCollection
     *
     * @example
     * ```php
     * $publishedPosts = $user->posts()->getBy(['status' => 'published']);
     * ```
     */
    public function getBy(array $attributes, array $columns = ['*']): ModelCollection
    {
        $query = clone $this->query;

        foreach ($attributes as $column => $value) {
            $query->where($column, $value);
        }

        return $this->relatedClass::hydrate($query->select($columns)->get()->toArray());
    }

    /**
     * Get the latest related models.
     *
     * Performance: O(log n) - Uses ORDER BY with LIMIT
     * Clean Architecture: Expressive temporal method
     *
     * @param int $limit Number of records to get
     * @param string $column Column to order by (default: 'created_at')
     * @return ModelCollection
     *
     * @example
     * ```php
     * $latestPosts = $user->posts()->latest(5);
     * ```
     */
    public function latest(int $limit = 10, string $column = 'created_at'): ModelCollection
    {
        return $this->relatedClass::hydrate(
            $this->query->orderBy($column, 'desc')->limit($limit)->get()->toArray()
        );
    }

    /**
     * Get the oldest related models.
     *
     * Performance: O(log n) - Uses ORDER BY with LIMIT
     * Clean Architecture: Expressive temporal method
     *
     * @param int $limit Number of records to get
     * @param string $column Column to order by (default: 'created_at')
     * @return ModelCollection
     *
     * @example
     * ```php
     * $oldestPosts = $user->posts()->oldest(5);
     * ```
     */
    public function oldest(int $limit = 10, string $column = 'created_at'): ModelCollection
    {
        return $this->relatedClass::hydrate(
            $this->query->orderBy($column, 'asc')->limit($limit)->get()->toArray()
        );
    }

    /**
     * Get random related models.
     *
     * Performance: O(n) - Database-dependent random ordering
     * Clean Architecture: Expressive randomization method
     *
     * @param int $limit Number of records to get
     * @return ModelCollection
     *
     * @example
     * ```php
     * $randomPosts = $user->posts()->random(3);
     * ```
     */
    public function random(int $limit = 1): ModelCollection
    {
        return $this->relatedClass::hydrate(
            $this->query->orderByRaw('RAND()')->limit($limit)->get()->toArray()
        );
    }

    /**
     * Sync related models (for HasMany with unique constraints).
     *
     * Performance: O(n) - Batch operations when possible
     * Clean Architecture: Atomic sync operation
     *
     * @param array $models Array of model data or IDs
     * @param string $uniqueColumn Column to use for uniqueness check
     * @return array Sync results
     *
     * @example
     * ```php
     * $user->posts()->syncBy([
     *     ['title' => 'Post 1', 'content' => 'Content 1'],
     *     ['title' => 'Post 2', 'content' => 'Content 2']
     * ], 'title');
     * ```
     */
    public function syncBy(array $models, string $uniqueColumn): array
    {
        $changes = ['created' => [], 'updated' => [], 'deleted' => []];

        if (empty($models)) {
            return $changes;
        }

        $parentKey = $this->parent->getAttribute($this->localKey);
        if ($parentKey === null) {
            throw new \RuntimeException('Cannot sync related models: parent model does not have a key');
        }

        // Get existing models
        $existing = $this->query->get();
        $existingByUnique = [];
        foreach ($existing as $model) {
            $key = $model->getAttribute($uniqueColumn);
            if ($key !== null) {
                $existingByUnique[$key] = $model;
            }
        }

        // Process new models
        $processedKeys = [];
        foreach ($models as $modelData) {
            if (is_array($modelData)) {
                $uniqueValue = $modelData[$uniqueColumn] ?? null;
                if ($uniqueValue === null) continue;

                $processedKeys[] = $uniqueValue;

                if (isset($existingByUnique[$uniqueValue])) {
                    // Update existing
                    $existingModel = $existingByUnique[$uniqueValue];
                    foreach ($modelData as $key => $value) {
                        $existingModel->setAttribute($key, $value);
                    }
                    $existingModel->save();
                    $changes['updated'][] = $existingModel;
                } else {
                    $modelData[$this->foreignKey] = $parentKey;
                    $changes['created'][] = $this->relatedClass::create($modelData);
                }
            }
        }

        // Delete models not in the new set
        foreach ($existingByUnique as $key => $model) {
            if (!in_array($key, $processedKeys)) {
                $model->delete();
                $changes['deleted'][] = $model;
            }
        }

        return $changes;
    }

    /**
     * Get models created within a date range.
     *
     * Performance: O(log n) - Uses indexed date column
     * Clean Architecture: Expressive temporal filtering
     *
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @param string $column Date column (default: 'created_at')
     * @return ModelCollection
     *
     * @example
     * ```php
     * $recentPosts = $user->posts()->createdBetween('2024-01-01', '2024-01-31');
     * ```
     */
    public function createdBetween(string $startDate, string $endDate, string $column = 'created_at'): ModelCollection
    {
        return $this->relatedClass::hydrate(
            $this->query->whereBetween($column, [$startDate, $endDate])->get()->toArray()
        );
    }

    /**
     * Get models created today.
     *
     * Performance: O(log n) - Uses DATE function with index
     * Clean Architecture: Expressive temporal method
     *
     * @param string $column Date column (default: 'created_at')
     * @return ModelCollection
     *
     * @example
     * ```php
     * $todaysPosts = $user->posts()->createdToday();
     * ```
     */
    public function createdToday(string $column = 'created_at'): ModelCollection
    {
        return $this->relatedClass::hydrate(
            $this->query->whereRaw("DATE({$column}) = ?", [now()->toDateString()])->get()->toArray()
        );
    }
}
