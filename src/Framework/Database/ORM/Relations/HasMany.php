<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};


/**
 * Class HasMany
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
class HasMany extends Relation
{
    /**
     * @param class-string<Model> $relatedClass Related model class name
     */
    public function __construct(
        \Toporia\Framework\Database\Query\QueryBuilder $query,
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
     *
     * @return ModelCollection
     */
    public function getResults(): ModelCollection
    {
        $rowCollection = $this->query->get();

        // Convert RowCollection to array for hydration
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
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        // Build dictionary: foreign_key => [model1, model2, ...]
        $dictionary = [];
        foreach ($results as $result) {
            $key = $result->getAttribute($this->foreignKey);
            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }
            $dictionary[$key][] = $result;
        }

        // Match to parents
        foreach ($models as $model) {
            $localValue = $model->getAttribute($this->localKey);
            $related = $dictionary[$localValue] ?? [];
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
     * Save multiple related models.
     *
     * Creates and saves multiple related models in a single operation.
     * Optimized for performance using bulk insert when possible.
     *
     * Architecture:
     * - SOLID: Single Responsibility (bulk save only)
     * - Performance: Uses single bulk INSERT query (O(1) queries instead of O(N))
     * - Clean Architecture: Uses QueryBuilder for database operations
     *
     * @param array<int, array<string, mixed>> $models Array of model attributes
     * @return ModelCollection Collection of saved models
     *
     * @example
     * $user->posts()->saveMany([
     *     ['title' => 'Post 1', 'content' => 'Content 1'],
     *     ['title' => 'Post 2', 'content' => 'Content 2'],
     * ]);
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

        // Create temporary model instance to get table name and handle timestamps
        $tempModel = new $this->relatedClass([]);
        $table = $tempModel->getTableName();

        // Check if model uses timestamps (via reflection to access static property)
        $reflection = app()->make(\Toporia\Framework\Support\ReflectionService::class)->getClass($this->relatedClass);
        $timestampsProperty = $reflection->getStaticPropertyValue('timestamps', true);
        $hasTimestamps = $timestampsProperty ?? true;

        // Prepare data with foreign key and timestamps
        $preparedModels = [];
        $now = $hasTimestamps ? date('Y-m-d H:i:s') : null;

        foreach ($models as $attributes) {
            // Set foreign key
            $attributes[$this->foreignKey] = $parentKey;

            // Add timestamps if enabled
            if ($hasTimestamps) {
                $attributes['created_at'] = $attributes['created_at'] ?? $now;
                $attributes['updated_at'] = $attributes['updated_at'] ?? $now;
            }

            $preparedModels[] = $attributes;
        }

        // Get all unique columns from all models
        $allColumns = [];
        foreach ($preparedModels as $attributes) {
            $allColumns = array_merge($allColumns, array_keys($attributes));
        }
        $columns = array_unique($allColumns);
        sort($columns); // Consistent column order

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

        // Get inserted IDs (MySQL returns first inserted ID, subsequent IDs are sequential)
        $lastInsertId = $connection->lastInsertId();
        $firstId = (int) $lastInsertId;

        // Validate that we got a valid ID
        if ($firstId <= 0) {
            // If lastInsertId failed, try to query the database for the inserted records
            // This can happen in some edge cases
            throw new \RuntimeException(
                "Failed to get inserted ID from database. LastInsertId returned: {$lastInsertId}. " .
                    "This might indicate the bulk insert failed or the table doesn't have an auto-increment column."
            );
        }

        $insertedIds = range($firstId, $firstId + count($preparedModels) - 1);

        // Hydrate models with IDs
        $savedModels = [];
        $modelReflection = app()->make(\Toporia\Framework\Support\ReflectionService::class)->getClass($this->relatedClass);

        // Check if exists property exists (it's private in Model base class)
        $hasExistsProperty = $modelReflection->hasProperty('exists');
        $existsProperty = null;
        if ($hasExistsProperty) {
            $existsProperty = $modelReflection->getProperty('exists');
            $existsProperty->setAccessible(true);
        }

        // Get syncOriginal method
        $syncMethod = $modelReflection->getMethod('syncOriginal');
        $syncMethod->setAccessible(true);

        foreach ($insertedIds as $index => $id) {
            $attributes = $preparedModels[$index];
            $model = new $this->relatedClass($attributes);

            // Set ID directly using setAttribute (bypasses mass assignment protection)
            $model->setAttribute('id', $id);

            // Set exists flag using reflection if property exists
            if ($hasExistsProperty && $existsProperty !== null) {
                $existsProperty->setValue($model, true);
            }

            // Sync original attributes (includes the ID we just set)
            $syncMethod->invoke($model);

            $savedModels[] = $model;
        }

        return new ModelCollection($savedModels);
    }

    /**
     * Create multiple related models.
     *
     * Alias for saveMany() for convenience.
     *
     * @param array<int, array<string, mixed>> $models Array of model attributes
     * @return ModelCollection Collection of created models
     */
    public function createMany(array $models): ModelCollection
    {
        return $this->saveMany($models);
    }

    /**
     * {@inheritdoc}
     *
     * Override to handle HasMany's constructor which has relatedClass parameter.
     * Creates a fresh instance without parent constraints for eager loading.
     *
     * Performance: O(1) - Direct instantiation, zero reflection overhead
     * Clean Architecture: Factory Method + Setter pattern for extensibility
     */
    public function newEagerInstance(\Toporia\Framework\Database\Query\QueryBuilder $freshQuery): static
    {
        $instance = new static(
            $freshQuery,
            $this->parent,
            $this->relatedClass,
            $this->foreignKey,
            $this->localKey
        );

        // HasMany constructor calls addConstraints() which adds parent WHERE clause
        // We need to reset the query to remove parent-specific constraints
        // Only eager constraints (WHERE IN) should be added later via addEagerConstraints()
        $cleanQuery = $freshQuery->newQuery();

        // Use setter method instead of reflection (cleaner & faster)
        $instance->setQuery($cleanQuery);

        return $instance;
    }

    /**
     * Create a new related model.
     *
     * Performance: O(1) - Single INSERT operation
     * Clean Architecture: Factory method with automatic foreign key assignment
     * SOLID: Single Responsibility - Creates and associates model
     *
     * @param array $attributes Model attributes
     * @return Model Created model instance
     *
     * @example
     * ```php
     * $post = $user->posts()->create(['title' => 'New Post', 'content' => 'Content']);
     * ```
     */
    public function create(array $attributes = []): Model
    {
        $parentKey = $this->parent->getAttribute($this->localKey);

        if ($parentKey === null) {
            throw new \RuntimeException('Cannot create related model: parent model does not have a key');
        }

        // Set foreign key to parent's local key
        $attributes[$this->foreignKey] = $parentKey;

        return call_user_func([$this->relatedClass, 'create'], $attributes);
    }

    /**
     * Save a related model.
     *
     * Performance: O(1) - Single UPDATE operation
     * Clean Architecture: Automatic foreign key management
     *
     * @param Model $model Model to save
     * @return Model Saved model instance
     *
     * @example
     * ```php
     * $post = new Post(['title' => 'New Post']);
     * $user->posts()->save($post);
     * ```
     */
    public function save(Model $model): Model
    {
        $parentKey = $this->parent->getAttribute($this->localKey);

        if ($parentKey === null) {
            throw new \RuntimeException('Cannot save related model: parent model does not have a key');
        }

        // Set foreign key to parent's local key
        $model->setAttribute($this->foreignKey, $parentKey);
        $model->save();

        return $model;
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
     * $user->posts()->update(['status' => 'published']);
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
     * $user->posts()->delete();
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
     * $post = $user->posts()->firstOrCreate(['title' => 'Default Post']);
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
     * $post = $user->posts()->firstOrNew(['title' => 'Draft Post']);
     * ```
     */
    public function firstOrNew(array $attributes = []): Model
    {
        $instance = $this->query->first();

        if ($instance === null) {
            $parentKey = $this->parent->getAttribute($this->localKey);
            $attributes[$this->foreignKey] = $parentKey;
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
     * $post = $user->posts()->updateOrCreate(
     *     ['slug' => 'my-post'],
     *     ['title' => 'Updated Title']
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
     * Performance: O(n/chunk_size) - Memory-efficient processing of large datasets
     * Clean Architecture: Callback pattern for flexible processing
     *
     * @param int $count Number of records per chunk
     * @param callable $callback Function to process each chunk
     * @return bool True if all chunks processed successfully
     *
     * @example
     * ```php
     * $user->posts()->chunk(100, function($posts) {
     *     foreach ($posts as $post) {
     *         // Process each post
     *     }
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

        $results = $this->query->whereIn('id', $ids)->select($columns)->get();
        return call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());
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
