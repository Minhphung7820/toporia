<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};


/**
 * Class MorphMany
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
class MorphMany extends Relation
{
    /**
     * @param \Toporia\Framework\Database\Query\QueryBuilder $query Query builder
     * @param Model $parent Parent model instance (Post or Video)
     * @param class-string<Model> $relatedClass Related model class (Comment)
     * @param string $morphName Morph name ('commentable')
     * @param string|null $morphType Type column (commentable_type)
     * @param string|null $morphId ID column (commentable_id)
     * @param string|null $localKey Local key on parent (id)
     */
    public function __construct(
        \Toporia\Framework\Database\Query\QueryBuilder $query,
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
     * Add constraints for morph relationship.
     *
     * @return static
     */
    public function addConstraints(): static
    {
        if ($this->parent->exists()) {
            // WHERE commentable_type = 'Post'
            $this->query->where($this->morphType, $this->getMorphClass());

            // AND commentable_id = ?
            $this->query->where(
                $this->foreignKey,
                $this->parent->getAttribute($this->localKey)
            );
        }
        return $this;
    }

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
     *
     * @return ModelCollection
     */
    public function getResults(): ModelCollection
    {
        // Ensure constraints are applied if parent exists now but didn't at construction
        if ($this->parent->exists()) {
            // Create fresh query to avoid conflicts with query modifications
            $freshQuery = $this->query->newQuery();
            $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);
            $freshQuery->table($relatedTable);

            // Apply morph constraints
            $freshQuery->where($this->morphType, $this->getMorphClass());
            $freshQuery->where($this->foreignKey, $this->parent->getAttribute($this->localKey));

            $rowCollection = $freshQuery->get();
        } else {
            $rowCollection = $this->query->get();
        }

        $rows = $rowCollection instanceof \Toporia\Framework\Database\Query\RowCollection
            ? $rowCollection->all()
            : $rowCollection;

        if (empty($rows)) {
            return new ModelCollection([]);
        }

        return call_user_func([$this->relatedClass, 'hydrate'], $rows);
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
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        // Build dictionary: type:id => [related_models]
        $dictionary = [];
        foreach ($results as $result) {
            $type = $result->getAttribute($this->morphType);
            $id = $result->getAttribute($this->foreignKey);
            $key = "{$type}:{$id}";

            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }
            $dictionary[$key][] = $result;
        }

        // Match to parents
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
     * Store morphType for access
     */
    protected string $morphType;

    /**
     * Create a new related model.
     *
     * Performance: O(1) - Single INSERT operation
     * Clean Architecture: Factory method with automatic morph key assignment
     * SOLID: Single Responsibility - Creates and associates model
     *
     * @param array $attributes Model attributes
     * @return Model Created model instance
     *
     * @example
     * ```php
     * $comment = $post->comments()->create(['content' => 'Great post!', 'author' => 'John']);
     * ```
     */
    public function create(array $attributes = []): Model
    {
        $parentKey = $this->parent->getAttribute($this->localKey);

        if ($parentKey === null) {
            throw new \RuntimeException('Cannot create related model: parent model does not have a key');
        }

        // Set morph keys
        $attributes[$this->foreignKey] = $parentKey;
        $attributes[$this->morphType] = $this->getMorphClass();

        return call_user_func([$this->relatedClass, 'create'], $attributes);
    }

    /**
     * Save a related model.
     *
     * Performance: O(1) - Single UPDATE operation
     * Clean Architecture: Automatic morph key management
     *
     * @param Model $model Model to save
     * @return Model Saved model instance
     *
     * @example
     * ```php
     * $comment = new Comment(['content' => 'Nice post!']);
     * $post->comments()->save($comment);
     * ```
     */
    public function save(Model $model): Model
    {
        $parentKey = $this->parent->getAttribute($this->localKey);

        if ($parentKey === null) {
            throw new \RuntimeException('Cannot save related model: parent model does not have a key');
        }

        // Set morph keys
        $model->setAttribute($this->foreignKey, $parentKey);
        $model->setAttribute($this->morphType, $this->getMorphClass());
        $model->save();

        return $model;
    }

    /**
     * Save multiple related models.
     *
     * Performance: O(1) - Bulk INSERT operation
     * Clean Architecture: Batch operation for efficiency
     *
     * @param array<int, array<string, mixed>> $models Array of model attributes
     * @return ModelCollection Collection of saved models
     *
     * @example
     * ```php
     * $comments = $post->comments()->saveMany([
     *     ['content' => 'First comment', 'author' => 'John'],
     *     ['content' => 'Second comment', 'author' => 'Jane'],
     * ]);
     * ```
     */
    public function saveMany(array $models): ModelCollection
    {
        if (empty($models)) {
            return new ModelCollection([]);
        }

        $parentKey = $this->parent->getAttribute($this->localKey);

        if ($parentKey === null) {
            throw new \RuntimeException('Cannot save related models: parent model does not have a key');
        }

        $morphClass = $this->getMorphClass();

        // Add morph keys to each model
        foreach ($models as &$attributes) {
            $attributes[$this->foreignKey] = $parentKey;
            $attributes[$this->morphType] = $morphClass;
        }

        // Use bulk insert similar to HasMany
        return $this->bulkInsert($models);
    }

    /**
     * Create multiple related models.
     *
     * @param array<int, array<string, mixed>> $models Array of model attributes
     * @return ModelCollection Collection of created models
     */
    public function createMany(array $models): ModelCollection
    {
        return $this->saveMany($models);
    }

    /**
     * Bulk insert models for performance.
     *
     * @param array $models Array of model attributes
     * @return ModelCollection
     */
    protected function bulkInsert(array $models): ModelCollection
    {
        // Create temporary model instance to get table name
        $tempModel = new $this->relatedClass([]);
        $table = $tempModel->getTableName();

        // Prepare data with timestamps
        $preparedModels = [];
        $now = date('Y-m-d H:i:s');

        foreach ($models as $attributes) {
            // Add timestamps
            $attributes['created_at'] = $attributes['created_at'] ?? $now;
            $attributes['updated_at'] = $attributes['updated_at'] ?? $now;
            $preparedModels[] = $attributes;
        }

        // Get all unique columns
        $allColumns = [];
        foreach ($preparedModels as $attributes) {
            $allColumns = array_merge($allColumns, array_keys($attributes));
        }
        $columns = array_unique($allColumns);
        sort($columns);

        // Prepare bulk insert
        $values = [];
        $placeholders = [];

        foreach ($preparedModels as $attributes) {
            $rowValues = [];
            foreach ($columns as $column) {
                $rowValues[] = $attributes[$column] ?? null;
            }
            $placeholders[] = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            $values = array_merge($values, $rowValues);
        }

        // Bulk insert
        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES " . implode(', ', $placeholders);
        $connection = $this->query->getConnection();
        $connection->execute($sql, $values);

        // Get inserted IDs and hydrate models
        $lastInsertId = (int) $connection->lastInsertId();
        $insertedIds = range($lastInsertId, $lastInsertId + count($preparedModels) - 1);

        $savedModels = [];
        foreach ($insertedIds as $index => $id) {
            $attributes = $preparedModels[$index];
            $model = new $this->relatedClass($attributes);
            $model->setAttribute('id', $id);
            $savedModels[] = $model;
        }

        return new ModelCollection($savedModels);
    }

    /**
     * Update all related models.
     *
     * Performance: O(1) - Single UPDATE operation with WHERE constraint
     * Clean Architecture: Bulk update operation
     *
     * @param array $attributes Attributes to update
     * @return int Number of affected rows
     *
     * @example
     * ```php
     * $post->comments()->update(['status' => 'approved']);
     * ```
     */
    public function update(array $attributes): int
    {
        if ($this->parent->exists()) {
            return $this->query->update($attributes);
        }

        return 0;
    }

    /**
     * Delete all related models.
     *
     * Performance: O(1) - Single DELETE operation
     * Clean Architecture: Bulk deletion operation
     *
     * @return int Number of deleted rows
     *
     * @example
     * ```php
     * $post->comments()->delete();
     * ```
     */
    public function delete(): int
    {
        if ($this->parent->exists()) {
            return $this->query->delete();
        }

        return 0;
    }

    /**
     * Get the first related model or create a new one.
     *
     * Performance: O(1) - Single SELECT, potential INSERT
     * Clean Architecture: Atomic find-or-create operation
     *
     * @param array $attributes Attributes for new model if not found
     * @return Model Found or created model
     *
     * @example
     * ```php
     * $comment = $post->comments()->firstOrCreate(['content' => 'First!']);
     * ```
     */
    public function firstOrCreate(array $attributes = []): Model
    {
        $instance = $this->query->first();

        if ($instance === null) {
            $instance = $this->create($attributes);
        }

        return $instance;
    }

    /**
     * Get the first related model or instantiate a new one.
     *
     * Performance: O(1) - Single SELECT operation
     * Clean Architecture: Non-persistent find-or-new operation
     *
     * @param array $attributes Attributes for new model if not found
     * @return Model Found or new model instance
     *
     * @example
     * ```php
     * $comment = $post->comments()->firstOrNew(['content' => 'Draft comment']);
     * ```
     */
    public function firstOrNew(array $attributes = []): Model
    {
        $instance = $this->query->first();

        if ($instance === null) {
            $parentKey = $this->parent->getAttribute($this->localKey);
            $attributes[$this->foreignKey] = $parentKey;
            $attributes[$this->morphType] = $this->getMorphClass();
            $instance = new $this->relatedClass($attributes);
        }

        return $instance;
    }

    /**
     * Update or create a related model.
     *
     * Performance: O(1) - Single SELECT, potential INSERT/UPDATE
     * Clean Architecture: Atomic upsert operation
     *
     * @param array $attributes Attributes to search by
     * @param array $values Additional values for creation
     * @return Model Updated or created model
     *
     * @example
     * ```php
     * $comment = $post->comments()->updateOrCreate(
     *     ['author' => 'John'],
     *     ['content' => 'Updated comment']
     * );
     * ```
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        $query = clone $this->query;

        // Apply where conditions for each attribute
        foreach ($attributes as $column => $value) {
            $query->where($column, $value);
        }

        $instance = $query->first();

        if ($instance instanceof Model) {
            foreach (array_merge($attributes, $values) as $key => $value) {
                $instance->setAttribute($key, $value);
            }
            $instance->save();
            return $instance;
        } else {
            return $this->create(array_merge($attributes, $values));
        }
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
     * $post->comments()->chunk(100, function($comments) {
     *     foreach ($comments as $comment) {
     *         // Process each comment
     *     }
     * });
     *
     * // For large datasets, prefer chunkById():
     * $post->comments()->chunkById(100, function($comments) {
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

            if ($callback($models, $page) === false) {
                return false;
            }

            $page++;
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
     * $commentCount = $post->comments()->count();
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
     * if ($post->comments()->exists()) {
     *     // Post has comments
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
     * $totalLikes = $post->comments()->sum('likes');
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
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $total = $this->count();
        $offset = ($page - 1) * $perPage;

        $items = $this->query->limit($perPage)->offset($offset)->get();
        $models = call_user_func([$this->relatedClass, 'hydrate'], $items->toArray());

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
     * Get the morph type value.
     *
     * Performance: O(1) - Direct method call
     * Clean Architecture: Expressive getter method
     *
     * @return string Morph type value
     */
    public function getMorphType(): string
    {
        return $this->morphType;
    }

    /**
     * Get the morph class value.
     *
     * Performance: O(1) - Direct method call
     * Clean Architecture: Expressive getter method
     *
     * @return string Morph class value
     */
    public function getMorphClassValue(): string
    {
        return $this->getMorphClass();
    }

    /**
     * Check if the parent is of a specific morph type.
     *
     * Performance: O(1) - Direct string comparison
     * Clean Architecture: Expressive type checking
     *
     * @param string $type Morph type to check
     * @return bool True if parent is of the specified type
     */
    public function isType(string $type): bool
    {
        return $this->getMorphClass() === $type;
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
     * $approvedComments = $post->comments()->getBy(['status' => 'approved']);
     * ```
     */
    public function getBy(array $attributes, array $columns = ['*']): ModelCollection
    {
        $query = clone $this->query;

        foreach ($attributes as $column => $value) {
            $query->where($column, $value);
        }

        $results = $query->select($columns)->get();
        return call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());
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
     * $latestComments = $post->comments()->latest(5);
     * ```
     */
    public function latest(int $limit = 10, string $column = 'created_at'): ModelCollection
    {
        $results = $this->query->orderBy($column, 'desc')->limit($limit)->get();
        return call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());
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
     * $oldestComments = $post->comments()->oldest(5);
     * ```
     */
    public function oldest(int $limit = 10, string $column = 'created_at'): ModelCollection
    {
        $results = $this->query->orderBy($column, 'asc')->limit($limit)->get();
        return call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());
    }

    /**
     * Sync related models by morph type and unique column.
     *
     * Performance: O(n) - Batch operations with morph constraints
     * Clean Architecture: Atomic sync operation for morph relations
     *
     * @param array $models Array of model data
     * @param string $uniqueColumn Column to use for uniqueness check
     * @return array Sync results
     *
     * @example
     * ```php
     * $post->comments()->syncBy([
     *     ['content' => 'Comment 1', 'author' => 'John'],
     *     ['content' => 'Comment 2', 'author' => 'Jane']
     * ], 'content');
     * ```
     */
    public function syncBy(array $models, string $uniqueColumn): array
    {
        $changes = ['created' => [], 'updated' => [], 'deleted' => []];

        if (empty($models)) {
            return $changes;
        }

        $parentKey = $this->parent->getAttribute($this->localKey);
        $morphClass = $this->getMorphClass();

        if ($parentKey === null) {
            throw new \RuntimeException('Cannot sync related models: parent model does not have a key');
        }

        // Get existing models with morph constraints
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
                    // Create new with morph keys
                    $modelData[$this->foreignKey] = $parentKey;
                    $modelData[$this->morphType] = $morphClass;
                    $newModel = call_user_func([$this->relatedClass, 'create'], $modelData);
                    $changes['created'][] = $newModel;
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
     * Performance: O(log n) - Uses indexed date column with morph constraints
     * Clean Architecture: Expressive temporal filtering
     *
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @param string $column Date column (default: 'created_at')
     * @return ModelCollection
     *
     * @example
     * ```php
     * $recentComments = $post->comments()->createdBetween('2024-01-01', '2024-01-31');
     * ```
     */
    public function createdBetween(string $startDate, string $endDate, string $column = 'created_at'): ModelCollection
    {
        $results = $this->query->whereBetween($column, [$startDate, $endDate])->get();
        return call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());
    }

    /**
     * Get models created today.
     *
     * Performance: O(log n) - Uses DATE function with index and morph constraints
     * Clean Architecture: Expressive temporal method
     *
     * @param string $column Date column (default: 'created_at')
     * @return ModelCollection
     *
     * @example
     * ```php
     * $todaysComments = $post->comments()->createdToday();
     * ```
     */
    public function createdToday(string $column = 'created_at'): ModelCollection
    {
        $today = date('Y-m-d');
        $results = $this->query->whereRaw("DATE({$column}) = ?", [$today])->get();
        return call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());
    }

    /**
     * Get models by morph type.
     *
     * Performance: O(log n) - Uses indexed morph type column
     * Clean Architecture: Morph-specific filtering method
     *
     * @param string $morphType Morph type to filter by
     * @return ModelCollection
     *
     * @example
     * ```php
     * $postComments = Comment::morphedBy('App\\Models\\Post')->get();
     * ```
     */
    public function morphedBy(string $morphType): ModelCollection
    {
        $results = $this->query->where($this->morphType, $morphType)->get();
        return call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());
    }

    /**
     * Get the related model class name.
     *
     * Performance: O(1) - Direct property access
     * Clean Architecture: Expressive getter method
     *
     * @return class-string<Model> Related model class
     */
    public function getRelatedClass(): string
    {
        return $this->relatedClass;
    }
}
