<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM\Relations;

use Toporia\Framework\Database\ORM\{Model, ModelCollection};


/**
 * Class HasManyThrough
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
class HasManyThrough extends Relation
{
    /**
     * @param \Toporia\Framework\Database\Query\QueryBuilder $query Query builder for related model
     * @param Model $parent Parent model instance
     * @param class-string<Model> $relatedClass Related model class (Post)
     * @param class-string<Model> $throughClass Through model class (User)
     * @param string $firstKey Foreign key on through table (users.country_id)
     * @param string $secondKey Foreign key on related table (posts.user_id)
     * @param string $localKey Local key on parent table (countries.id)
     * @param string $secondLocalKey Local key on through table (users.id)
     */
    public function __construct(
        \Toporia\Framework\Database\Query\QueryBuilder $query,
        Model $parent,
        protected string $relatedClass,
        protected string $throughClass,
        protected string $firstKey,  // users.country_id
        string $secondKey,           // posts.user_id
        string $localKey,            // countries.id
        protected string $secondLocalKey
    ) {
        // Call parent constructor first (will set $this->foreignKey and $this->localKey)
        parent::__construct($query, $parent, $firstKey, $localKey);

        // Override foreignKey to use secondKey (posts.user_id instead of users.country_id)
        // This is the actual foreign key on the related table
        $this->foreignKey = $secondKey; // posts.user_id
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
        // INNER JOIN users ON users.id = posts.user_id
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
     * HasManyThrough uses performJoin() instead.
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
     * @return ModelCollection
     */
    public function getResults(): ModelCollection
    {
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Select only related table columns to avoid column ambiguity
        $this->query->select("{$relatedTable}.*");

        $rowCollection = $this->query->get();

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
    public function addEagerConstraints(array $models): void
    {
        $throughTable = call_user_func([$this->throughClass, 'getTableName']);
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        // Get parent IDs
        $keys = array_map(fn($m) => $m->getAttribute($this->localKey), $models);

        // Clear existing where (from performJoin)
        $this->query = $this->query->newQuery()->table($relatedTable);

        // Re-add join
        $this->query->join(
            $throughTable,
            "{$throughTable}.{$this->secondLocalKey}",
            '=',
            "{$relatedTable}.{$this->foreignKey}"
        );

        // WHERE users.country_id IN (1, 2, 3, ...)
        $this->query->whereIn("{$throughTable}.{$this->firstKey}", $keys);

        // Select with through key for matching
        $this->query->select("{$relatedTable}.*", "{$throughTable}.{$this->firstKey}");
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, mixed $results, string $relationName): array
    {
        if (!$results instanceof ModelCollection) {
            return $models;
        }

        // Build dictionary: parent_id => [related_models]
        $dictionary = [];
        foreach ($results as $result) {
            // Get the through key from result
            $key = $result->getAttribute($this->firstKey);
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
     * Check if any related models exist.
     *
     * Performance: O(1) - Single EXISTS query with JOIN and early termination
     * Clean Architecture: Expressive existence check
     *
     * @return bool True if related models exist
     *
     * @example
     * ```php
     * if ($country->posts()->exists()) {
     *     // Country has posts through users
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
     * Get the count of related models.
     *
     * Performance: O(1) - Single COUNT query with JOIN
     * Clean Architecture: Expressive counting method
     *
     * @return int Count of related models
     *
     * @example
     * ```php
     * $postCount = $country->posts()->count();
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
     * Update all related models through the intermediate relationship.
     *
     * Performance: O(1) - Single UPDATE with JOIN constraint
     * Clean Architecture: Bulk update operation
     *
     * @param array $attributes Attributes to update
     * @return int Number of affected rows
     *
     * @example
     * ```php
     * $country->posts()->update(['status' => 'published']);
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
     * Delete all related models through the intermediate relationship.
     *
     * Performance: O(1) - Single DELETE with JOIN constraint
     * Clean Architecture: Bulk deletion operation
     *
     * @return int Number of deleted rows
     *
     * @example
     * ```php
     * $country->posts()->delete();
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
     * $post = $country->posts()->firstOrFail();
     * ```
     */
    public function firstOrFail(): Model
    {
        $results = $this->getResults();

        if ($results->isEmpty()) {
            throw new \RuntimeException('No related models found through intermediate relationship');
        }

        $first = $results->first();
        if (!$first instanceof Model) {
            throw new \RuntimeException('No related models found through intermediate relationship');
        }

        return $first;
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
     * $country->posts()->chunk(100, function($posts) {
     *     foreach ($posts as $post) {
     *         // Process each post
     *     }
     * });
     *
     * // For large datasets, prefer chunkById():
     * $country->posts()->chunkById(100, function($posts) {
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
     * $country->posts()->chunkById(50, function($posts) {
     *     // Process posts in consistent order
     * });
     * ```
     */
    public function chunkById(int $count, callable $callback, string $column = 'id', string $alias = null): bool
    {
        $alias = $alias ?: $column;
        $lastId = null;
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        do {
            $clone = clone $this->query;

            if ($lastId !== null) {
                $clone->where("{$relatedTable}.{$column}", '>', $lastId);
            }

            $results = $clone->orderBy("{$relatedTable}.{$column}")->limit($count)->get();

            if ($results->isEmpty()) {
                break;
            }

            $models = call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());

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
     * $totalViews = $country->posts()->sum('views');
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
     * Paginate the results.
     *
     * Performance: O(1) - Single query with LIMIT/OFFSET and JOIN
     * Clean Architecture: Consistent pagination interface
     *
     * @param int $perPage Items per page
     * @param int $page Current page
     * @return array Pagination results
     *
     * @example
     * ```php
     * $posts = $country->posts()->paginate(10, 2);
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
     * Performance: O(log n) - Indexed primary key lookup with JOIN
     * Clean Architecture: Expressive finder method
     *
     * @param mixed $id Primary key value
     * @param array $columns Columns to select
     * @return Model|null
     *
     * @example
     * ```php
     * $post = $country->posts()->find(1);
     * ```
     */
    public function find(mixed $id, array $columns = ['*']): ?Model
    {
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);
        return $this->query->where("{$relatedTable}.id", $id)->select($columns)->first();
    }

    /**
     * Find multiple related models by their primary keys.
     *
     * Performance: O(log n) - Indexed primary key lookup with IN clause and JOIN
     * Clean Architecture: Bulk finder method
     *
     * @param array $ids Array of primary key values
     * @param array $columns Columns to select
     * @return ModelCollection
     *
     * @example
     * ```php
     * $posts = $country->posts()->findMany([1, 2, 3]);
     * ```
     */
    public function findMany(array $ids, array $columns = ['*']): ModelCollection
    {
        if (empty($ids)) {
            return new ModelCollection([]);
        }

        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);
        $results = $this->query->whereIn("{$relatedTable}.id", $ids)->select($columns)->get();
        return call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());
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
     * $throughClass = $country->posts()->getThroughClass(); // User::class
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
     * $relatedClass = $country->posts()->getRelatedClass(); // Post::class
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
     * $firstKey = $country->posts()->getFirstKey(); // 'country_id'
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
     * $secondLocalKey = $country->posts()->getSecondLocalKey(); // 'id'
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
     * $posts = $country->posts()->whereThrough('status', 'active')->get();
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
     * Select additional columns from the through table.
     *
     * Performance: O(1) - Direct query modification
     * Clean Architecture: Fluent interface for column selection
     *
     * @param string ...$columns Through table column names
     * @return $this
     *
     * @example
     * ```php
     * $posts = $country->posts()->selectThrough('created_at', 'status')->get();
     * ```
     */
    public function selectThrough(string ...$columns): static
    {
        $throughTable = call_user_func([$this->throughClass, 'getTableName']);
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        $selectColumns = ["{$relatedTable}.*"];
        foreach ($columns as $column) {
            $selectColumns[] = "{$throughTable}.{$column} as through_{$column}";
        }

        $this->query->select($selectColumns);

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

    /**
     * Get related models with specific attributes.
     *
     * Performance: O(n) - Single query with JOIN and WHERE constraints
     * Clean Architecture: Expressive finder method for through relationships
     *
     * @param array $attributes Attributes to search by
     * @param array $columns Columns to select
     * @return ModelCollection
     *
     * @example
     * ```php
     * $publishedPosts = $country->posts()->getBy(['status' => 'published']);
     * ```
     */
    public function getBy(array $attributes, array $columns = ['*']): ModelCollection
    {
        $query = clone $this->query;
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);

        foreach ($attributes as $column => $value) {
            $query->where("{$relatedTable}.{$column}", $value);
        }

        $results = $query->select($columns)->get();
        return call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());
    }

    /**
     * Get the latest related models through the intermediate relationship.
     *
     * Performance: O(log n) - Uses ORDER BY with LIMIT through JOIN
     * Clean Architecture: Expressive temporal method
     *
     * @param int $limit Number of records to get
     * @param string $column Column to order by (default: 'created_at')
     * @return ModelCollection
     *
     * @example
     * ```php
     * $latestPosts = $country->posts()->latest(5);
     * ```
     */
    public function latest(int $limit = 10, string $column = 'created_at'): ModelCollection
    {
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);
        $results = $this->query->orderBy("{$relatedTable}.{$column}", 'desc')->limit($limit)->get();
        return call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());
    }

    /**
     * Get the oldest related models through the intermediate relationship.
     *
     * Performance: O(log n) - Uses ORDER BY with LIMIT through JOIN
     * Clean Architecture: Expressive temporal method
     *
     * @param int $limit Number of records to get
     * @param string $column Column to order by (default: 'created_at')
     * @return ModelCollection
     *
     * @example
     * ```php
     * $oldestPosts = $country->posts()->oldest(5);
     * ```
     */
    public function oldest(int $limit = 10, string $column = 'created_at'): ModelCollection
    {
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);
        $results = $this->query->orderBy("{$relatedTable}.{$column}", 'asc')->limit($limit)->get();
        return call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());
    }

    /**
     * Get random related models through the intermediate relationship.
     *
     * Performance: O(n) - Database-dependent random ordering through JOIN
     * Clean Architecture: Expressive randomization method
     *
     * @param int $limit Number of records to get
     * @return ModelCollection
     *
     * @example
     * ```php
     * $randomPosts = $country->posts()->random(3);
     * ```
     */
    public function random(int $limit = 1): ModelCollection
    {
        $results = $this->query->orderByRaw('RAND()')->limit($limit)->get();
        return call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());
    }

    /**
     * Get models created within a date range through the relationship.
     *
     * Performance: O(log n) - Uses indexed date column through JOIN
     * Clean Architecture: Expressive temporal filtering
     *
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @param string $column Date column (default: 'created_at')
     * @return ModelCollection
     *
     * @example
     * ```php
     * $recentPosts = $country->posts()->createdBetween('2024-01-01', '2024-01-31');
     * ```
     */
    public function createdBetween(string $startDate, string $endDate, string $column = 'created_at'): ModelCollection
    {
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);
        $results = $this->query->whereBetween("{$relatedTable}.{$column}", [$startDate, $endDate])->get();
        return call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());
    }

    /**
     * Get models created today through the relationship.
     *
     * Performance: O(log n) - Uses DATE function with index through JOIN
     * Clean Architecture: Expressive temporal method
     *
     * @param string $column Date column (default: 'created_at')
     * @return ModelCollection
     *
     * @example
     * ```php
     * $todaysPosts = $country->posts()->createdToday();
     * ```
     */
    public function createdToday(string $column = 'created_at'): ModelCollection
    {
        $today = date('Y-m-d');
        $relatedTable = call_user_func([$this->relatedClass, 'getTableName']);
        $results = $this->query->whereRaw("DATE({$relatedTable}.{$column}) = ?", [$today])->get();
        return call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());
    }

    /**
     * Get models with specific through table attributes.
     *
     * Performance: O(n) - Single query with through table constraints
     * Clean Architecture: Expressive through-table filtering
     *
     * @param array $throughAttributes Attributes on the through table
     * @param array $columns Columns to select
     * @return ModelCollection
     *
     * @example
     * ```php
     * $activePosts = $country->posts()->getByThrough(['status' => 'active']);
     * ```
     */
    public function getByThrough(array $throughAttributes, array $columns = ['*']): ModelCollection
    {
        $query = clone $this->query;
        $throughTable = call_user_func([$this->throughClass, 'getTableName']);

        foreach ($throughAttributes as $column => $value) {
            $query->where("{$throughTable}.{$column}", $value);
        }

        $results = $query->select($columns)->get();
        return call_user_func([$this->relatedClass, 'hydrate'], $results->toArray());
    }

    /**
     * Get distinct values from the through table.
     *
     * Performance: O(log n) - Uses database DISTINCT optimization through JOIN
     * Clean Architecture: Expressive method for through table analysis
     *
     * @param string $column Through table column name
     * @return array Array of distinct values
     *
     * @example
     * ```php
     * $departments = $country->posts()->distinctThrough('department');
     * ```
     */
    public function distinctThrough(string $column): array
    {
        $throughTable = call_user_func([$this->throughClass, 'getTableName']);

        $connection = $this->query->getConnection();
        $qb = new \Toporia\Framework\Database\Query\QueryBuilder($connection);

        return $qb->table($throughTable)
            ->distinct()
            ->pluck($column)
            ->toArray();
    }

    /**
     * Get aggregated values from the through table.
     *
     * Performance: O(1) - Single aggregation query through JOIN
     * Clean Architecture: Expressive aggregation method
     *
     * @param string $function Aggregation function (sum, avg, min, max, count)
     * @param string $column Through table column name
     * @return mixed Aggregated value
     *
     * @example
     * ```php
     * $totalUsers = $country->posts()->aggregateThrough('count', 'user_id');
     * ```
     */
    public function aggregateThrough(string $function, string $column): mixed
    {
        $throughTable = call_user_func([$this->throughClass, 'getTableName']);

        $connection = $this->query->getConnection();
        $qb = new \Toporia\Framework\Database\Query\QueryBuilder($connection);

        $query = $qb->table($throughTable)
            ->where($this->firstKey, $this->parent->getAttribute($this->localKey));

        return match (strtolower($function)) {
            'sum' => $query->sum($column),
            'avg' => $query->avg($column),
            'min' => $query->min($column),
            'max' => $query->max($column),
            'count' => $query->count($column),
            default => throw new \InvalidArgumentException("Unsupported aggregation function: {$function}")
        };
    }
}
