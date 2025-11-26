<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM;

use Closure;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\Contracts\ConnectionInterface;
use Toporia\Framework\Database\Contracts\RelationInterface;


/**
 * Class ModelQueryBuilder
 *
 * Fluent SQL query builder providing chainable interface for constructing
 * SELECT, INSERT, UPDATE, DELETE queries with automatic parameter binding
 * and join support.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  ORM
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
class ModelQueryBuilder extends QueryBuilder
{
    /**
     * @param ConnectionInterface $connection Database connection
     * @param class-string<TModel> $modelClass Model class to hydrate results into
     */
    /**
     * Whether to skip applying global scopes.
     * Used by SoftDeletes::withTrashed() to bypass scopes.
     *
     * @var bool
     */
    private bool $skipGlobalScopes = false;

    /**
     * Query hints for performance optimization.
     *
     * @var array
     */
    private array $queryHints = [];

    /**
     * Whether to explain query execution.
     *
     * @var bool
     */
    private bool $explainQuery = false;

    /**
     * Whether to include execution statistics in explain.
     *
     * @var bool
     */
    private bool $explainAnalyze = false;

    /**
     * Whether relationship caching is enabled for this query.
     *
     * @var bool
     */
    private bool $relationshipCachingEnabled = false;

    public function __construct(
        ConnectionInterface $connection,
        private readonly string $modelClass,
        bool $skipGlobalScopes = false
    ) {
        parent::__construct($connection);

        $this->skipGlobalScopes = $skipGlobalScopes;

        // Apply global scopes if not skipped
        if (!$this->skipGlobalScopes) {
            $this->applyGlobalScopes();
        }
    }

    /**
     * Apply global scopes to the query.
     *
     * Checks if model uses HasQueryScopes trait and applies all global scopes.
     * Also applies SoftDeletes scopes if model uses SoftDeletes trait.
     *
     * @return void
     */
    private function applyGlobalScopes(): void
    {
        // Apply scopes from HasQueryScopes trait
        if (method_exists($this->modelClass, 'getGlobalScopes')) {
            $globalScopes = call_user_func([$this->modelClass, 'getGlobalScopes']);

            foreach ($globalScopes as $scope) {
                $scope($this);
            }
        }

        // Apply scopes from SoftDeletes trait (works independently)
        if (method_exists($this->modelClass, 'getSoftDeleteGlobalScopes')) {
            $softDeleteScopes = call_user_func([$this->modelClass, 'getSoftDeleteGlobalScopes']);

            foreach ($softDeleteScopes as $scope) {
                $scope($this);
            }
        }
    }

    /**
     * Execute the query and return a ModelCollection.
     *
     * @internal This is an internal implementation method.
     *           Use get() instead for cleaner public API.
     *
     * Internal method used by get(), first(), find() and relationship loading.
     * 1. Gets raw rows from database
     * 2. Hydrates into model instances
     * 3. Loads eager relationships
     *
     * @return ModelCollection<TModel>
     */
    public function getModels(): ModelCollection
    {
        // Step 1: Get raw rows from parent QueryBuilder
        $rowCollection = parent::get();
        $rows = $rowCollection->all();

        // Step 2: Hydrate rows into models
        /** @var callable $hydrate */
        $hydrate = [$this->modelClass, 'hydrate'];
        $collection = $hydrate($rows);

        // Step 3: Load eager relationships if configured
        $eagerLoad = $this->getEagerLoad();
        if (!empty($eagerLoad) && !$collection->isEmpty()) {
            /** @var callable $eagerLoadRelations */
            $eagerLoadRelations = [$this->modelClass, 'eagerLoadRelations'];
            $eagerLoadRelations($collection, $eagerLoad);
        }

        return $collection;
    }

    /**
     * Paginate the query results with Model hydration.
     *
     * Overrides parent to return Paginator with ModelCollection.
     *
     * @param int $perPage Number of items per page (default: 15)
     * @param int $page Current page number (1-indexed, default: 1)
     * @param string|null $path Base URL path for pagination links
     * @return \Toporia\Framework\Support\Pagination\Paginator<TModel>
     */
    public function paginate(int $perPage = 15, int $page = 1, ?string $path = null): \Toporia\Framework\Support\Pagination\Paginator
    {
        // Validate parameters
        if ($perPage < 1) {
            throw new \InvalidArgumentException('Per page must be at least 1');
        }
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be at least 1');
        }

        // Step 1: Get total count (without limit/offset)
        $total = $this->count();

        // Step 2: Get paginated items as ModelCollection
        $offset = ($page - 1) * $perPage;
        $items = $this->limit($perPage)->offset($offset)->getModels(); // Hydrates and loads relationships

        // Step 3: Return Paginator value object
        return new \Toporia\Framework\Support\Pagination\Paginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
            path: $path
        );
    }

    /**
     * Spawn a fresh ModelQueryBuilder sharing the same connection and model class.
     */
    public function newQuery(): self
    {
        return new self($this->getConnection(), $this->modelClass);
    }

    /**
     * Get the first model from the query results.
     *
     * Overrides parent to return Model instance instead of array.
     * Supports fluent syntax: Model::query()->where(...)->first()
     *
     * @return TModel|null Model instance or null
     * @phpstan-return TModel|null
     */
    public function first(): mixed
    {
        $collection = $this->limit(1)->getModels();
        return $collection->first();
    }

    /**
     * Find a model by its primary key.
     *
     * Supports both Model::find(1) and Model::query()->find(1) syntax.
     * Preserves eager loading and other query configurations.
     *
     * @param int|string $id Primary key value
     * @param string $column Column name (default: 'id')
     * @return TModel|null Model instance or null
     * @phpstan-return TModel|null
     */
    public function find(int|string $id, string $column = 'id'): mixed
    {
        // Apply where and limit to current query (preserves eager load config)
        $this->where($column, $id)->limit(1);
        $collection = $this->getModels();
        return $collection->first();
    }



    // =========================================================================
    // RELATIONSHIP QUERY METHODS
    // =========================================================================

    /**
     * Filter models that have a related model matching the given constraints.
     *
     * Optimized implementation:
     * - Uses EXISTS subquery instead of JOIN when possible (better performance)
     * - Supports callback for complex constraints
     *
     * Clean Architecture & SOLID:
     * - Single Responsibility: Only adds WHERE EXISTS clause
     * - Open/Closed: Extensible via callback
     * - Dependency Inversion: Works with any RelationInterface
     *
     * @param string $relation Relationship method name
     * @param callable|null $callback Optional callback to constrain the relationship query
     * @param string $operator Comparison operator (>=, =, etc.)
     * @param int $count Minimum count (default: 1 means "has at least one")
     * @return $this
     *
     * @example
     * // Products that have at least one review
     * ProductModel::whereHas('reviews')->get();
     *
     * // Products with reviews rating >= 4
     * ProductModel::whereHas('reviews', function($query) {
     *     $query->where('rating', '>=', 4);
     * })->get();
     *
     * // Products with at least 5 reviews
     * ProductModel::whereHas('reviews', null, '>=', 5)->get();
     */
    public function whereHas(string $relation, ?callable $callback = null, string $operator = '>=', int $count = 1): self
    {
        // Get table name from model
        /** @var callable $getTableName */
        $getTableName = [$this->modelClass, 'getTableName'];
        $table = $getTableName();

        // Create a dummy model to get the relationship
        $model = new $this->modelClass([]);

        if (!method_exists($model, $relation)) {
            throw new \InvalidArgumentException("Relationship '{$relation}' does not exist on model {$this->modelClass}");
        }

        $relationInstance = $model->$relation();

        if (!$relationInstance instanceof RelationInterface) {
            throw new \InvalidArgumentException("Method '{$relation}' is not a valid relationship");
        }

        // Get the relationship's query builder
        $relationQuery = $relationInstance->getQuery();

        // Apply callback constraints if provided
        if ($callback !== null) {
            $callback($relationQuery);
        }

        // Handle different relationship types properly
        if (
            $relationInstance instanceof \Toporia\Framework\Database\ORM\Relations\BelongsToMany ||
            $relationInstance instanceof \Toporia\Framework\Database\ORM\Relations\MorphToMany
        ) {

            $subquerySql = $this->buildPivotWhereHasSubquery($relationInstance, $table, $relationQuery);
        } else {
            $subquerySql = $this->buildSimpleWhereHasSubquery($relationInstance, $table, $relationQuery);
        }

        // Add relation query wheres to subquery
        // Important: Inject bindings directly into SQL (same approach as withCount)
        // to avoid binding order issues
        $relationSql = $relationQuery->toSql();
        if (preg_match('/WHERE (.+?)(?:ORDER BY|LIMIT|$)/s', $relationSql, $matches)) {
            $whereClause = $matches[1];

            // Replace placeholders with actual values (safely quoted)
            // Security: Use PDO::quote() instead of addslashes() to prevent SQL injection
            $relationBindings = $relationQuery->getBindings();
            $boundWhereClause = $whereClause;
            foreach ($relationBindings as $binding) {
                // Safely quote value using PDO::quote() (prevents SQL injection)
                $quoted = $this->quoteValue($binding);
                $boundWhereClause = preg_replace('/\?/', $quoted, $boundWhereClause, 1);
            }

            $subquerySql .= " AND ({$boundWhereClause})";
        }

        // Add the EXISTS clause with count comparison
        // Example: WHERE (SELECT COUNT(*) ...) >= 1
        $this->whereRaw("({$subquerySql}) {$operator} ?", [$count]);

        return $this;
    }

    /**
     * Build subquery for pivot relationships (BelongsToMany, MorphToMany).
     *
     * @param \Toporia\Framework\Database\Contracts\RelationInterface $relation Relation instance
     * @param string $parentTable Parent table name
     * @param \Toporia\Framework\Database\Query\QueryBuilder $relationQuery Relation query
     * @return string Subquery SQL
     */
    protected function buildPivotWhereHasSubquery($relation, string $parentTable, $relationQuery): string
    {
        $reflectionService = app()->make(\Toporia\Framework\Support\ReflectionService::class);

        if ($relation instanceof \Toporia\Framework\Database\ORM\Relations\BelongsToMany) {
            // Get pivot table and keys for BelongsToMany
            $pivotTable = $reflectionService->getPropertyValue($relation, 'pivotTable');
            $foreignPivotKey = $reflectionService->getPropertyValue($relation, 'foreignPivotKey');
            $relatedPivotKey = $reflectionService->getPropertyValue($relation, 'relatedPivotKey');
            $parentKey = $reflectionService->getPropertyValue($relation, 'parentKey');
            $relatedKey = $reflectionService->getPropertyValue($relation, 'relatedKey');

            // Get related table
            $relatedTable = $relationQuery->getTable();

            // Build subquery with proper JOIN
            // SELECT COUNT(*) FROM pivot_table
            // INNER JOIN related_table ON pivot_table.related_key = related_table.id
            // WHERE pivot_table.parent_key = parent_table.id
            $subquerySql = "SELECT COUNT(*) FROM {$pivotTable} " .
                "INNER JOIN {$relatedTable} ON {$pivotTable}.{$relatedPivotKey} = {$relatedTable}.{$relatedKey} " .
                "WHERE {$pivotTable}.{$foreignPivotKey} = {$parentTable}.{$parentKey}";
        } elseif ($relation instanceof \Toporia\Framework\Database\ORM\Relations\MorphToMany) {
            // Similar logic for MorphToMany
            $pivotTable = $reflectionService->getPropertyValue($relation, 'pivotTable');
            $morphType = $reflectionService->getPropertyValue($relation, 'morphType');
            $morphId = $reflectionService->getPropertyValue($relation, 'foreignKey');
            $relatedPivotKey = $reflectionService->getPropertyValue($relation, 'relatedPivotKey');
            $parentKey = $reflectionService->getPropertyValue($relation, 'localKey');
            $relatedKey = $reflectionService->getPropertyValue($relation, 'relatedKey');

            // Get related table and morph class
            $relatedTable = $relationQuery->getTable();
            $morphClass = get_class($relation->getParent());

            $subquerySql = "SELECT COUNT(*) FROM {$pivotTable} " .
                "INNER JOIN {$relatedTable} ON {$pivotTable}.{$relatedPivotKey} = {$relatedTable}.{$relatedKey} " .
                "WHERE {$pivotTable}.{$morphId} = {$parentTable}.{$parentKey} " .
                "AND {$pivotTable}.{$morphType} = '{$morphClass}'";
        }

        return $subquerySql;
    }

    /**
     * Build subquery for simple relationships (HasOne, HasMany, BelongsTo, etc.).
     *
     * @param \Toporia\Framework\Database\Contracts\RelationInterface $relation Relation instance
     * @param string $parentTable Parent table name
     * @param \Toporia\Framework\Database\Query\QueryBuilder $relationQuery Relation query
     * @return string Subquery SQL
     */
    protected function buildSimpleWhereHasSubquery($relation, string $parentTable, $relationQuery): string
    {
        // Get foreign and local keys for simple relationships
        $foreignKey = $relation->getForeignKey();
        $localKey = $relation->getLocalKey();

        // Get relation table
        $relationTable = $relationQuery->getTable();

        // Use alias for self-referencing relationships
        $relationAlias = $parentTable === $relationTable ? "{$relationTable}_relation" : $relationTable;

        // Build EXISTS subquery with proper aliasing
        $fromClause = $parentTable === $relationTable ? "{$relationTable} AS {$relationAlias}" : $relationTable;
        $subquerySql = "SELECT COUNT(*) FROM {$fromClause} WHERE {$relationAlias}.{$foreignKey} = {$parentTable}.{$localKey}";

        return $subquerySql;
    }

    /**
     * Eager load relationships.
     *
     * Supports multiple syntaxes:
     * - with('relation')
     * - with(['relation'])
     * - with(['relation' => callback])
     * - with('relation:column1,column2')
     *
     * Clean Architecture & SOLID:
     * - Single Responsibility: Only configures eager loading
     * - Open/Closed: Extensible via callbacks
     * - Dependency Inversion: Works with any RelationInterface
     *
     * @param string|array|callable ...$relations Relationship specifications
     * @return $this
     *
     * @example
     * // Basic eager loading
     * $query->with('childrens')->get();
     *
     * // With column selection
     * $query->with('childrens:id,title,price')->get();
     *
     * // With callback constraints
     * $query->with(['childrens' => function($q) {
     *     $q->where('is_active', 1);
     * }])->get();
     *
     * // Multiple relationships
     * $query->with(['childrens', 'category'])->get();
     */
    public function with(string|array|callable ...$relations): self
    {
        // Delegate to Model's static method for normalization
        /** @var callable $normalizeMethod */
        $normalizeMethod = [$this->modelClass, 'normalizeEagerLoadRelations'];
        $normalized = $normalizeMethod($relations);

        // Merge with existing eager load configuration
        $existing = $this->getEagerLoad();
        $this->setEagerLoad(array_merge($existing, $normalized));

        return $this;
    }

    /**
     * Add a subselect count of a relationship to the query.
     *
     * Optimized implementation:
     * - Single query with subselect instead of separate query
     * - Automatically optimized by database engine
     *
     * Supports callbacks like with():
     * - withCount('reviews') - count all
     * - withCount(['reviews' => fn($q) => $q->where('rating', '>=', 4)]) - count with constraints
     *
     * @param string|array $relations Relationship name(s) or associative array with callbacks
     * @return $this
     *
     * @example
     * // Get products with review count
     * $products = ProductModel::withCount('reviews')->get();
     * // Access: $product->reviews_count
     *
     * // Multiple relationships
     * $products = ProductModel::withCount(['reviews', 'orders'])->get();
     *
     * // With callback constraints
     * $products = ProductModel::withCount(['reviews' => function($q) {
     *     $q->where('rating', '>=', 4);
     * }])->get();
     * // Access: $product->reviews_count (only counts reviews with rating >= 4)
     */
    public function withCount(string|array $relations): self
    {
        // Convert string to array
        if (is_string($relations)) {
            $relations = [$relations];
        }

        foreach ($relations as $key => $value) {
            // Case 1: 'relation' => callback
            if (is_string($key) && is_callable($value)) {
                $this->addRelationCountSelect($key, $value);
            }
            // Case 2: numeric key with string value (no callback)
            elseif (is_int($key) && is_string($value)) {
                $this->addRelationCountSelect($value, null);
            }
        }

        return $this;
    }

    /**
     * Filter models that DON'T have a related model matching the given constraints.
     *
     * This is the inverse of whereHas() - it finds records that lack the specified relationship.
     * Uses NOT EXISTS subquery for optimal performance.
     *
     * Clean Architecture & SOLID:
     * - Single Responsibility: Only adds WHERE NOT EXISTS clause
     * - Open/Closed: Extensible via callback
     * - Dependency Inversion: Works with any RelationInterface
     *
     * @param string $relation Relationship method name
     * @param callable|null $callback Optional callback to constrain the relationship query
     * @param string $operator Comparison operator (<, =, etc.)
     * @param int $count Maximum count (default: 1 means "has less than one")
     * @return $this
     *
     * @example
     * // Products that have no reviews
     * ProductModel::whereDoesntHave('reviews')->get();
     *
     * // Products without high-rated reviews (rating >= 4)
     * ProductModel::whereDoesntHave('reviews', function($query) {
     *     $query->where('rating', '>=', 4);
     * })->get();
     *
     * // Products with less than 5 reviews
     * ProductModel::whereDoesntHave('reviews', null, '<', 5)->get();
     */
    public function whereDoesntHave(string $relation, ?callable $callback = null, string $operator = '<', int $count = 1): self
    {
        // Get table name from model
        /** @var callable $getTableName */
        $getTableName = [$this->modelClass, 'getTableName'];
        $table = $getTableName();

        // Create a dummy model to get the relationship
        $model = new $this->modelClass([]);

        if (!method_exists($model, $relation)) {
            throw new \InvalidArgumentException("Relationship '{$relation}' does not exist on model {$this->modelClass}");
        }

        $relationInstance = $model->$relation();

        if (!$relationInstance instanceof RelationInterface) {
            throw new \InvalidArgumentException("Method '{$relation}' is not a valid relationship");
        }

        // Get the relationship's query builder
        $relationQuery = $relationInstance->getQuery();

        // Apply callback constraints if provided
        if ($callback !== null) {
            $callback($relationQuery);
        }

        // Handle different relationship types properly
        if (
            $relationInstance instanceof \Toporia\Framework\Database\ORM\Relations\BelongsToMany ||
            $relationInstance instanceof \Toporia\Framework\Database\ORM\Relations\MorphToMany
        ) {
            $subquerySql = $this->buildPivotWhereHasSubquery($relationInstance, $table, $relationQuery);
        } else {
            $subquerySql = $this->buildSimpleWhereHasSubquery($relationInstance, $table, $relationQuery);
        }

        // Add relation query wheres to subquery
        $relationSql = $relationQuery->toSql();
        if (preg_match('/WHERE (.+?)(?:ORDER BY|LIMIT|$)/s', $relationSql, $matches)) {
            $whereClause = $matches[1];

            // Replace placeholders with actual values (safely quoted)
            $relationBindings = $relationQuery->getBindings();
            $boundWhereClause = $whereClause;
            foreach ($relationBindings as $binding) {
                $quoted = $this->quoteValue($binding);
                $boundWhereClause = preg_replace('/\?/', $quoted, $boundWhereClause, 1);
            }

            $subquerySql .= " AND ({$boundWhereClause})";
        }

        // Add the NOT EXISTS clause with count comparison
        // Example: WHERE (SELECT COUNT(*) ...) < 1
        $this->whereRaw("({$subquerySql}) {$operator} ?", [$count]);

        return $this;
    }

    /**
     * OR version of whereDoesntHave.
     *
     * @param string $relation Relationship method name
     * @param callable|null $callback Optional callback to constrain the relationship query
     * @param string $operator Comparison operator (<, =, etc.)
     * @param int $count Maximum count (default: 1)
     * @return $this
     */
    public function orWhereDoesntHave(string $relation, ?callable $callback = null, string $operator = '<', int $count = 1): self
    {
        // Get table name from model
        /** @var callable $getTableName */
        $getTableName = [$this->modelClass, 'getTableName'];
        $table = $getTableName();

        // Create a dummy model to get the relationship
        $model = new $this->modelClass([]);

        if (!method_exists($model, $relation)) {
            throw new \InvalidArgumentException("Relationship '{$relation}' does not exist on model {$this->modelClass}");
        }

        $relationInstance = $model->$relation();

        if (!$relationInstance instanceof RelationInterface) {
            throw new \InvalidArgumentException("Method '{$relation}' is not a valid relationship");
        }

        // Get the relationship's query builder
        $relationQuery = $relationInstance->getQuery();

        // Apply callback constraints if provided
        if ($callback !== null) {
            $callback($relationQuery);
        }

        // Handle different relationship types properly
        if (
            $relationInstance instanceof \Toporia\Framework\Database\ORM\Relations\BelongsToMany ||
            $relationInstance instanceof \Toporia\Framework\Database\ORM\Relations\MorphToMany
        ) {
            $subquerySql = $this->buildPivotWhereHasSubquery($relationInstance, $table, $relationQuery);
        } else {
            $subquerySql = $this->buildSimpleWhereHasSubquery($relationInstance, $table, $relationQuery);
        }

        // Add relation query wheres to subquery
        $relationSql = $relationQuery->toSql();
        if (preg_match('/WHERE (.+?)(?:ORDER BY|LIMIT|$)/s', $relationSql, $matches)) {
            $whereClause = $matches[1];

            // Replace placeholders with actual values (safely quoted)
            $relationBindings = $relationQuery->getBindings();
            $boundWhereClause = $whereClause;
            foreach ($relationBindings as $binding) {
                $quoted = $this->quoteValue($binding);
                $boundWhereClause = preg_replace('/\?/', $quoted, $boundWhereClause, 1);
            }

            $subquerySql .= " AND ({$boundWhereClause})";
        }

        // Add the OR NOT EXISTS clause with count comparison
        $this->orWhereRaw("({$subquerySql}) {$operator} ?", [$count]);

        return $this;
    }

    /**
     * Filter models that don't have nested relationships.
     *
     * Supports dot notation for nested relationships like 'posts.comments'.
     * This is a Toporia exclusive feature - superior to Laravel.
     *
     * @param string $relation Nested relationship using dot notation (e.g., 'posts.comments')
     * @param callable|null $callback Optional callback to constrain the final relationship
     * @return $this
     *
     * @example
     * // Users without posts that have comments
     * UserModel::whereDoesntHaveNested('posts.comments')->get();
     *
     * // Categories without products that have high-rated reviews
     * CategoryModel::whereDoesntHaveNested('products.reviews', function($query) {
     *     $query->where('rating', '>=', 4);
     * })->get();
     */
    public function whereDoesntHaveNested(string $relation, ?callable $callback = null): self
    {
        $relations = explode('.', $relation);
        $finalRelation = array_pop($relations);

        // Build nested query from inside out
        $nestedCallback = $callback;

        // Work backwards through the relationship chain
        for ($i = count($relations) - 1; $i >= 0; $i--) {
            $currentRelation = $relations[$i];
            $previousCallback = $nestedCallback;

            $nestedCallback = function ($query) use ($currentRelation, $previousCallback) {
                $query->whereDoesntHave($currentRelation, $previousCallback);
            };
        }

        return $this->whereDoesntHave($finalRelation, $nestedCallback);
    }

    /**
     * Filter models that don't have relationships with specific IDs.
     *
     * This is a Toporia exclusive feature for ID-based filtering.
     *
     * @param string $relation Relationship method name
     * @param array $ids Array of IDs to exclude
     * @param string $column Column to check IDs against (default: 'id')
     * @return $this
     *
     * @example
     * // Products without reviews from specific users
     * ProductModel::whereDoesntHaveIn('reviews', [1, 2, 3, 4, 5], 'user_id')->get();
     *
     * // Users without specific roles
     * UserModel::whereDoesntHaveIn('roles', [1, 2, 3])->get();
     */
    public function whereDoesntHaveIn(string $relation, array $ids, string $column = 'id'): self
    {
        if (empty($ids)) {
            return $this;
        }

        return $this->whereDoesntHave($relation, function ($query) use ($ids, $column) {
            $query->whereIn($column, $ids);
        });
    }

    /**
     * Filter models that don't have relationships within a date range.
     *
     * This is a Toporia exclusive feature for date-based filtering.
     *
     * @param string $relation Relationship method name
     * @param string $dateColumn Date column to check
     * @param string|\DateTime $startDate Start date (inclusive)
     * @param string|\DateTime|null $endDate End date (inclusive, optional)
     * @return $this
     *
     * @example
     * // Users without orders in the last 30 days
     * UserModel::whereDoesntHaveInDateRange('orders', 'created_at', now()->subDays(30))->get();
     *
     * // Products without reviews this year
     * ProductModel::whereDoesntHaveInDateRange('reviews', 'created_at', '2024-01-01', '2024-12-31')->get();
     */
    public function whereDoesntHaveInDateRange(string $relation, string $dateColumn, string|\DateTime $startDate, string|\DateTime|null $endDate = null): self
    {
        return $this->whereDoesntHave($relation, function ($query) use ($dateColumn, $startDate, $endDate) {
            if ($endDate !== null) {
                $query->whereBetween($dateColumn, [$startDate, $endDate]);
            } else {
                $query->where($dateColumn, '>=', $startDate);
            }
        });
    }

    /**
     * Filter models that don't have relationships with specific JSON attribute values.
     *
     * This is a Toporia exclusive feature for JSON-based filtering.
     *
     * @param string $relation Relationship method name
     * @param string $jsonColumn JSON column name
     * @param string $jsonPath JSON path (e.g., '$.source')
     * @param mixed $value Value to match
     * @return $this
     *
     * @example
     * // Products without mobile reviews
     * ProductModel::whereDoesntHaveJsonAttribute('reviews', 'metadata', '$.source', 'mobile')->get();
     *
     * // Users without email notifications enabled
     * UserModel::whereDoesntHaveJsonAttribute('preferences', 'settings', '$.notifications.email', true)->get();
     */
    public function whereDoesntHaveJsonAttribute(string $relation, string $jsonColumn, string $jsonPath, mixed $value): self
    {
        return $this->whereDoesntHave($relation, function ($query) use ($jsonColumn, $jsonPath, $value) {
            $query->whereJsonContains($jsonColumn . '->' . $jsonPath, $value);
        });
    }

    /**
     * Add a subselect sum of a relationship column to the query.
     *
     * Supports callbacks for filtering:
     * - withSum('orders', 'total') - sum all
     * - withSum('orders', 'total', fn($q) => $q->where('status', 'completed')) - sum with constraints
     *
     * @param string $relation Relationship name
     * @param string $column Column to sum
     * @param callable|null $callback Optional callback to constrain the sum
     * @return $this
     *
     * @example
     * // Get users with total order amount
     * $users = UserModel::withSum('orders', 'total')->get();
     * // Access: $user->orders_sum_total
     *
     * // Sum only completed orders
     * $users = UserModel::withSum('orders', 'total', function($q) {
     *     $q->where('status', 'completed');
     * })->get();
     */
    public function withSum(string $relation, string $column, ?callable $callback = null): self
    {
        return $this->addRelationAggregateSelect($relation, $column, 'SUM', $callback);
    }

    /**
     * Add a subselect average of a relationship column to the query.
     *
     * @param string $relation Relationship name
     * @param string $column Column to average
     * @param callable|null $callback Optional callback to constrain the average
     * @return $this
     *
     * @example
     * // Average rating of all reviews
     * $products = ProductModel::withAvg('reviews', 'rating')->get();
     *
     * // Average rating of verified reviews only
     * $products = ProductModel::withAvg('reviews', 'rating', function($q) {
     *     $q->where('verified', true);
     * })->get();
     */
    public function withAvg(string $relation, string $column, ?callable $callback = null): self
    {
        return $this->addRelationAggregateSelect($relation, $column, 'AVG', $callback);
    }

    /**
     * Add a subselect minimum of a relationship column to the query.
     *
     * @param string $relation Relationship name
     * @param string $column Column to find minimum
     * @param callable|null $callback Optional callback to constrain
     * @return $this
     */
    public function withMin(string $relation, string $column, ?callable $callback = null): self
    {
        return $this->addRelationAggregateSelect($relation, $column, 'MIN', $callback);
    }

    /**
     * Add a subselect maximum of a relationship column to the query.
     *
     * @param string $relation Relationship name
     * @param string $column Column to find maximum
     * @param callable|null $callback Optional callback to constrain
     * @return $this
     */
    public function withMax(string $relation, string $column, ?callable $callback = null): self
    {
        return $this->addRelationAggregateSelect($relation, $column, 'MAX', $callback);
    }

    // =========================================================================
    // PRIVATE HELPER METHODS
    // =========================================================================

    /**
     * Add a relationship count subselect to the query.
     *
     * @param string $relation Relationship name
     * @param callable|null $callback Optional callback to constrain the count
     * @return void
     */
    private function addRelationCountSelect(string $relation, ?callable $callback = null): void
    {
        /** @var callable $getTableName */
        $getTableName = [$this->modelClass, 'getTableName'];
        $table = $getTableName();

        $model = new $this->modelClass([]);
        $relationInstance = $model->$relation();

        if (!$relationInstance instanceof RelationInterface) {
            throw new \InvalidArgumentException("Method '{$relation}' is not a valid relationship");
        }

        $relationQuery = $relationInstance->getQuery();

        // Apply callback constraints if provided
        if ($callback !== null) {
            $callback($relationQuery);
        }

        $foreignKey = $relationInstance->getForeignKey();
        $localKey = $relationInstance->getLocalKey();
        $relationTable = $relationQuery->getTable();

        // Use alias for self-referencing relationships (e.g., products.parent_id -> products.id)
        // This prevents ambiguity when parent and child tables are the same
        $relationAlias = $table === $relationTable ? "{$relationTable}_relation" : $relationTable;

        // Build subselect with proper aliasing
        // Example: (SELECT COUNT(*) FROM products AS products_relation WHERE products_relation.parent_id = products.id)
        $fromClause = $table === $relationTable ? "{$relationTable} AS {$relationAlias}" : $relationTable;
        $subquery = "SELECT COUNT(*) FROM {$fromClause} WHERE {$relationAlias}.{$foreignKey} = {$table}.{$localKey}";

        // Add relation query wheres to subquery
        // Important: We need to inject bindings directly into SQL because
        // selectRaw bindings are added to the end, but subquery bindings need to be
        // embedded within the subquery itself for correct ordering
        $relationSql = $relationQuery->toSql();
        if (preg_match('/WHERE (.+?)(?:ORDER BY|LIMIT|$)/s', $relationSql, $matches)) {
            $whereClause = $matches[1];

            // Replace placeholders with actual values (safely quoted)
            // Security: Use PDO::quote() instead of addslashes() to prevent SQL injection
            $relationBindings = $relationQuery->getBindings();
            $boundWhereClause = $whereClause;
            foreach ($relationBindings as $binding) {
                // Safely quote value using PDO::quote() (prevents SQL injection)
                $quoted = $this->quoteValue($binding);
                $boundWhereClause = preg_replace('/\?/', $quoted, $boundWhereClause, 1);
            }

            $subquery .= " AND ({$boundWhereClause})";
        }

        $columnAlias = "{$relation}_count";

        // Ensure we select table.* along with the subquery (only once)
        $columns = $this->getColumns();
        if (empty($columns) || !in_array("{$table}.*", $columns, true)) {
            $this->select("{$table}.*");
        }

        $this->selectRaw("({$subquery}) AS {$columnAlias}");
    }

    /**
     * Add a relationship aggregate subselect to the query.
     *
     * @param string $relation Relationship name
     * @param string $column Column to aggregate
     * @param string $function Aggregate function (SUM, AVG, MIN, MAX)
     * @param callable|null $callback Optional callback to constrain the aggregate
     * @return $this
     */
    private function addRelationAggregateSelect(string $relation, string $column, string $function, ?callable $callback = null): self
    {
        /** @var callable $getTableName */
        $getTableName = [$this->modelClass, 'getTableName'];
        $table = $getTableName();

        $model = new $this->modelClass([]);
        $relationInstance = $model->$relation();

        if (!$relationInstance instanceof RelationInterface) {
            throw new \InvalidArgumentException("Method '{$relation}' is not a valid relationship");
        }

        $relationQuery = $relationInstance->getQuery();

        // Apply callback constraints if provided
        if ($callback !== null) {
            $callback($relationQuery);
        }

        $foreignKey = $relationInstance->getForeignKey();
        $localKey = $relationInstance->getLocalKey();
        $relationTable = $relationQuery->getTable();

        // Use alias for self-referencing relationships
        $relationAlias = $table === $relationTable ? "{$relationTable}_relation" : $relationTable;

        // Build subselect with proper aliasing
        $fromClause = $table === $relationTable ? "{$relationTable} AS {$relationAlias}" : $relationTable;
        $subquery = "SELECT {$function}({$relationAlias}.{$column}) FROM {$fromClause} WHERE {$relationAlias}.{$foreignKey} = {$table}.{$localKey}";

        // Add relation query wheres to subquery
        // Important: Inject bindings directly into SQL to avoid binding order issues
        $relationSql = $relationQuery->toSql();
        if (preg_match('/WHERE (.+?)(?:ORDER BY|LIMIT|$)/s', $relationSql, $matches)) {
            $whereClause = $matches[1];

            // Replace placeholders with actual values (safely quoted)
            // Security: Use PDO::quote() instead of addslashes() to prevent SQL injection
            $relationBindings = $relationQuery->getBindings();
            $boundWhereClause = $whereClause;
            foreach ($relationBindings as $binding) {
                // Safely quote value using PDO::quote() (prevents SQL injection)
                $quoted = $this->quoteValue($binding);
                $boundWhereClause = preg_replace('/\?/', $quoted, $boundWhereClause, 1);
            }

            $subquery .= " AND ({$boundWhereClause})";
        }

        $functionLower = strtolower($function);
        $columnAlias = "{$relation}_{$functionLower}_{$column}";

        // Ensure we select table.* along with the subquery (only once)
        $columns = $this->getColumns();
        if (empty($columns) || !in_array("{$table}.*", $columns, true)) {
            $this->select("{$table}.*");
        }

        $this->selectRaw("({$subquery}) AS {$columnAlias}");

        return $this;
    }

    /**
     * Chunk the query results into smaller batches.
     *
     * Allows chunking with query constraints applied.
     * More flexible than Model::chunk() for complex queries.
     *
     * Performance: O(n/chunkSize) queries
     * Memory: O(chunkSize) - Only one chunk in memory at a time
     *
     * @param int $chunkSize Number of records per chunk
     * @param callable|null $callback Optional callback to process each chunk
     * @return \Generator<ModelCollection>|void Generator of chunks (if no callback), void (if callback provided)
     *
     * @example
     * ```php
     * // Chunk with WHERE clause
     * foreach (User::query()->where('age', '>=', 25)->chunk(100) as $chunk) {
     *     // Process chunk
     * }
     *
     * // With callback
     * User::query()->where('active', 1)->chunk(100, function ($chunk) {
     *     // Process chunk
     * });
     * ```
     */
    public function chunk(int $count, \Closure $callback): bool
    {
        $offset = 0;

        while (true) {
            // Clone query to preserve original state
            $query = clone $this;
            $chunk = $query
                ->limit($count)
                ->offset($offset)
                ->getModels();

            if ($chunk->isEmpty()) {
                break;
            }

            $callback($chunk);

            // If chunk is smaller than count, we're done
            if ($chunk->count() < $count) {
                break;
            }

            $offset += $count;

            // Force garbage collection to free memory
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        return true;
    }

    /**
     * Magic method to enable fluent get() method.
     *
     * Intercepts ->get() calls and redirects to ->getModels() to return ModelCollection.
     * This is needed because PHP doesn't support return type covariance for Collection types.
     *
     * @param string $method Method name
     * @param array<mixed> $arguments Method arguments
     * @return mixed
     */
    public function __call(string $method, array $arguments): mixed
    {
        // Intercept get() to return ModelCollection
        if ($method === 'get') {
            return $this->getModels();
        }

        // Forward to parent QueryBuilder for other methods
        if (method_exists(parent::class, $method)) {
            return parent::$method(...$arguments);
        }

        throw new \BadMethodCallException("Method {$method} does not exist on " . static::class);
    }

    /**
     * Chunk the query results using cursor-based pagination.
     *
     * More efficient than offset-based chunking for large datasets.
     * Uses WHERE id > lastId instead of OFFSET.
     *
     * Performance: O(n/chunkSize) queries, faster than OFFSET-based
     * Memory: O(chunkSize) - Only one chunk in memory at a time
     *
     * @param int $chunkSize Number of records per chunk
     * @param callable|null $callback Optional callback to process each chunk
     * @return \Generator<ModelCollection>|void
     *
     * @example
     * ```php
     * // More efficient for large datasets
     * foreach (User::query()->where('active', 1)->chunkById(1000) as $chunk) {
     *     // Process chunk
     * }
     * ```
     */
    public function chunkById(int $count, Closure $callback, ?string $column = null, ?string $alias = null): bool
    {
        /** @var callable $getPrimaryKey */
        $getPrimaryKey = [$this->modelClass, 'getPrimaryKey'];
        $primaryKey = $column ?? $getPrimaryKey();
        $lastId = 0;

        while (true) {
            // Clone query to preserve original state
            $query = clone $this;
            $chunk = $query
                ->where($primaryKey, '>', $lastId)
                ->orderBy($primaryKey, 'ASC')
                ->limit($count)
                ->getModels();

            if ($chunk->isEmpty()) {
                break;
            }

            $callback($chunk);

            // Get last ID from chunk
            /** @var \Toporia\Framework\Database\ORM\Model $lastModel */
            $lastModel = $chunk->last();
            $lastId = $lastModel->getKey();

            // If chunk is smaller than count, we're done
            if ($chunk->count() < $count) {
                break;
            }

            // Force garbage collection to free memory
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        return true;
    }

    // =========================================================================
    // PERFORMANCE OPTIMIZATION METHODS
    // =========================================================================

    /**
     * Add query hints for performance optimization.
     *
     * This is a Toporia exclusive feature for database optimization.
     *
     * @param string $type Hint type ('index', 'force_index', 'use_index')
     * @param array $values Hint values
     * @return $this
     *
     * @example
     * ProductModel::whereDoesntHave('reviews')
     *     ->addQueryHint('index', ['idx_product_id'])
     *     ->get();
     */
    public function addQueryHint(string $type, array $values): self
    {
        // Store hints for later use in query building
        if (!isset($this->queryHints)) {
            $this->queryHints = [];
        }

        $this->queryHints[$type] = $values;
        return $this;
    }

    /**
     * Optimize query for large result sets.
     *
     * @param bool $optimize Whether to enable optimization
     * @return $this
     */
    public function optimizeForLargeResults(bool $optimize = true): self
    {
        if ($optimize) {
            // Add SQL_NO_CACHE hint for large datasets
            $this->addQueryHint('no_cache', []);

            // Suggest using streaming for very large results
            $this->addQueryHint('stream_results', []);
        }

        return $this;
    }

    /**
     * Enable query explanation for debugging.
     *
     * @param bool $analyze Whether to include execution statistics
     * @return $this
     */
    public function explain(bool $analyze = false): self
    {
        $this->explainQuery = true;
        $this->explainAnalyze = $analyze;
        return $this;
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Safely quote a value for SQL injection prevention.
     *
     * Uses PDO::quote() for proper escaping based on database type.
     *
     * @param mixed $value Value to quote
     * @return string Quoted value
     */
    protected function quoteValue(mixed $value): string
    {
        $pdo = $this->getConnection()->getPdo();

        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        // Use PDO::quote for strings to prevent SQL injection
        return $pdo->quote((string) $value);
    }

    /**
     * Build a safe subquery for relationship filtering.
     *
     * This method ensures proper SQL generation and prevents injection attacks.
     *
     * @param RelationInterface $relation The relationship instance
     * @param string $parentTable Parent table name
     * @param QueryBuilder $relationQuery Relation query builder
     * @param bool $exists Whether this is for EXISTS (true) or NOT EXISTS (false)
     * @return string Safe subquery SQL
     */
    private function buildSafeRelationSubquery(RelationInterface $relation, string $parentTable, QueryBuilder $relationQuery, bool $exists = true): string
    {
        // Handle different relationship types
        if (
            $relation instanceof \Toporia\Framework\Database\ORM\Relations\BelongsToMany ||
            $relation instanceof \Toporia\Framework\Database\ORM\Relations\MorphToMany
        ) {
            $subquerySql = $this->buildPivotWhereHasSubquery($relation, $parentTable, $relationQuery);
        } else {
            $subquerySql = $this->buildSimpleWhereHasSubquery($relation, $parentTable, $relationQuery);
        }

        // Add relation constraints safely
        $relationSql = $relationQuery->toSql();
        if (preg_match('/WHERE (.+?)(?:ORDER BY|LIMIT|$)/s', $relationSql, $matches)) {
            $whereClause = $matches[1];

            // Safely bind parameters
            $relationBindings = $relationQuery->getBindings();
            $boundWhereClause = $whereClause;
            foreach ($relationBindings as $binding) {
                $quoted = $this->quoteValue($binding);
                $boundWhereClause = preg_replace('/\?/', $quoted, $boundWhereClause, 1);
            }

            $subquerySql .= " AND ({$boundWhereClause})";
        }

        return $subquerySql;
    }

    /**
     * Get relationship cache key for performance optimization.
     *
     * @param string $relation Relationship name
     * @param array $constraints Query constraints
     * @return string Cache key
     */
    private function getRelationshipCacheKey(string $relation, array $constraints = []): string
    {
        $modelClass = $this->modelClass;
        $constraintsHash = md5(serialize($constraints));

        return "relationship:{$modelClass}:{$relation}:{$constraintsHash}";
    }

    /**
     * Check if relationship caching is enabled.
     *
     * @return bool
     */
    private function isRelationshipCachingEnabled(): bool
    {
        return property_exists($this, 'relationshipCachingEnabled') && $this->relationshipCachingEnabled;
    }
}
