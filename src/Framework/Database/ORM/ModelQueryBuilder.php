<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\ORM;

use Closure;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\Contracts\ConnectionInterface;
use Toporia\Framework\Database\Contracts\RelationInterface;
use Toporia\Framework\Database\DatabaseCollection;
use Toporia\Framework\Database\ORM\Relations\BelongsToMany;


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
     * Whether relationship caching is enabled for this query.
     *
     * @var bool
     */
    private bool $relationshipCachingEnabled = false;

    /**
     * Eager loaded relationships configuration (ORM layer).
     *
     * @var array<string, callable|null>
     */
    private array $eagerLoad = [];

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
    public function paginate(int $perPage = 15, int $page = 1, ?string $path = null, ?string $baseUrl = null): \Toporia\Framework\Support\Pagination\Paginator
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
            path: $path,
            baseUrl: $baseUrl
        );
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
     * $paginator = ProductModel::query()
     *     ->orderBy('id', 'ASC')
     *     ->cursorPaginate(50);
     *
     * // Next page (using cursor from previous response)
     * $paginator = ProductModel::query()
     *     ->orderBy('id', 'ASC')
     *     ->cursorPaginate(50, ['cursor' => $request->get('cursor')]);
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
    /**
     * Paginate results using cursor-based pagination (Model-specific).
     *
     * Overrides parent to use model's primary key as default cursor column
     * and return ModelCollection instead of RowCollection.
     *
     * @param int $perPage Number of items per page
     * @param array<string, mixed>|null $options Options array with:
     *   - 'cursor': Encoded cursor string (optional)
     *   - 'column': Column name for cursor (default: model's primary key)
     *   - 'path': Base path for pagination URLs (optional)
     *   - 'baseUrl': Base URL for pagination URLs (optional)
     *   - 'cursorName': Query parameter name for cursor (default: 'cursor')
     * @param array<string, mixed>|null $options2 Alternative options format (for backward compatibility)
     * @return \Toporia\Framework\Support\Pagination\CursorPaginator
     */
    public function cursorPaginate(
        int $perPage = 15,
        ?array $options = null,
        ?array $options2 = null
    ): \Toporia\Framework\Support\Pagination\CursorPaginator {
        // Normalize options (support both formats)
        if ($options2 !== null) {
            $options = array_merge($options ?? [], $options2);
        }

        // Extract options with defaults
        $cursor = $options['cursor'] ?? null;
        // Default to model's primary key instead of 'id'
        $column = $options['column'] ?? $this->modelClass::getPrimaryKey();
        $path = $options['path'] ?? null;
        $baseUrl = $options['baseUrl'] ?? null;
        $cursorName = $options['cursorName'] ?? 'cursor';

        // Validate parameters
        if ($perPage < 1) {
            throw new \InvalidArgumentException('Per page must be at least 1');
        }

        // Get current order by direction for cursor column
        // Default to ASC if not specified
        $orderDirection = $this->getOrderDirectionForColumn($column) ?? 'ASC';

        // Build query with cursor constraint
        // Performance: Clone to avoid modifying original query
        $query = clone $this;

        // Performance Optimization: Ensure cursor column is indexed
        // For optimal performance, cursor column should have an index
        // This is especially important for large datasets
        // Note: Database will automatically use index if available

        // Apply cursor constraint if provided
        if ($cursor !== null && is_string($cursor)) {
            $cursorValue = $this->decodeCursor($cursor, $column);
            if ($cursorValue !== null) {
                // Performance: Use indexed WHERE clause (O(1) lookup)
                // WHERE id > cursor is much faster than OFFSET for large datasets
                if ($orderDirection === 'ASC') {
                    $query->where($column, '>', $cursorValue);
                } else {
                    $query->where($column, '<', $cursorValue);
                }
            }
        }

        // Ensure ordering by cursor column for consistent pagination
        // Critical: Cursor pagination requires stable ordering
        // The cursor column must be the primary sort key
        $query = $this->ensureOrderByCursorColumn($query, $column, $orderDirection);

        // Performance: Fetch one extra item to determine if there are more pages
        // This avoids an additional COUNT query (O(n) operation)
        // Instead, we use O(1) check: if we got perPage+1 items, there are more pages
        $items = $query->limit($perPage + 1)->getModels();

        // Determine if there are more pages
        $hasMore = $items->count() > $perPage;

        // Remove the extra item if it exists
        if ($hasMore) {
            $items = $items->take($perPage);
        }

        // Get cursors for next and previous pages
        $nextCursor = null;
        $prevCursor = null;

        if ($hasMore && $items->isNotEmpty()) {
            // Get the last item's cursor value
            $lastItem = $items->last();
            $nextCursorValue = $lastItem->getAttribute($column);
            $nextCursor = $this->encodeCursor($nextCursorValue, $column);
        }

        // Previous cursor is the current cursor (for backward navigation)
        $prevCursor = $cursor;

        return new \Toporia\Framework\Support\Pagination\CursorPaginator(
            items: $items,
            perPage: $perPage,
            nextCursor: $nextCursor,
            prevCursor: $prevCursor,
            hasMore: $hasMore,
            path: $path,
            baseUrl: $baseUrl,
            cursorName: $cursorName
        );
    }

    /**
     * Get the order direction for a specific column.
     *
     * Checks existing order by clauses to determine direction.
     * Uses public getOrders() method instead of reflection for better performance.
     *
     * Performance: O(n) where n = number of order by clauses (typically 1-3)
     * Clean Architecture: Uses public API instead of reflection
     *
     * @param string $column Column name
     * @return string|null 'ASC' or 'DESC', or null if not found
     */
    private function getOrderDirectionForColumn(string $column): ?string
    {
        // Use public getOrders() method (no reflection needed)
        $orders = $this->getOrders();

        // Find order by for this column
        foreach ($orders as $order) {
            if (isset($order['column']) && $order['column'] === $column) {
                return $order['direction'] ?? 'ASC';
            }
        }

        // Default to ASC if not found
        return 'ASC';
    }

    /**
     * Ensure query is ordered by cursor column.
     *
     * Adds cursor column as primary sort if not already present.
     * This is critical for cursor pagination to work correctly.
     *
     * Performance: O(n) where n = number of order by clauses
     * Clean Architecture: Uses public getOrders() method instead of reflection
     *
     * @param ModelQueryBuilder $query Query builder
     * @param string $column Cursor column
     * @param string $direction Order direction
     * @return ModelQueryBuilder
     */
    private function ensureOrderByCursorColumn(ModelQueryBuilder $query, string $column, string $direction): ModelQueryBuilder
    {
        // Use public getOrders() method (no reflection needed)
        $orders = $query->getOrders();

        // Check if cursor column is already in order by
        $hasCursorColumn = false;
        foreach ($orders as $order) {
            if (isset($order['column']) && $order['column'] === $column) {
                $hasCursorColumn = true;
                break;
            }
        }

        // Add cursor column as primary sort if not present
        if (!$hasCursorColumn) {
            // Note: We can't easily prepend, so we add it
            // The database will use the first order by as primary
            // For cursor pagination, cursor column should be first
            return $query->orderBy($column, $direction);
        }

        return $query;
    }

    /**
     * Encode cursor value for URL-safe transmission.
     *
     * Uses base64-encoded JSON for security and flexibility.
     * Can be extended to support complex cursors with multiple values.
     *
     * Security: Base64 encoding prevents direct manipulation
     * Performance: O(1) encoding operation
     *
     * @param mixed $value Cursor value (typically int for IDs, or string for UUIDs)
     * @param string $column Column name (for validation)
     * @return string Encoded cursor (URL-safe)
     */
    private function encodeCursor(mixed $value, string $column): string
    {
        // Format: {"column": "id", "value": 123, "ts": timestamp}
        // Timestamp can be used for cursor expiration/validation if needed
        $data = [
            'column' => $column,
            'value' => $value,
            'ts' => time(), // Optional: for cursor expiration
        ];

        return base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Decode cursor value from URL parameter.
     *
     * Validates cursor structure and column to prevent injection attacks.
     *
     * Security: Validates column name to prevent column injection
     * Performance: O(1) decoding operation
     *
     * @param string $cursor Encoded cursor
     * @param string $expectedColumn Expected column name (for validation)
     * @return mixed|null Decoded cursor value, or null if invalid
     */
    private function decodeCursor(string $cursor, string $expectedColumn): mixed
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
            // This ensures users can't manipulate cursor to query different columns
            if (isset($data['column']) && $data['column'] !== $expectedColumn) {
                return null;
            }

            return $data['value'];
        } catch (\Throwable $e) {
            // Invalid cursor format - return null to start from beginning
            return null;
        }
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
        // For count-based queries (count != 1), use the count approach
        if ($count !== 1 || $operator !== '>=') {
            return $this->whereHasWithCount($relation, $callback, $operator, $count);
        }

        // For simple existence check, use optimized EXISTS
        return $this->whereHasExists($relation, $callback);
    }

    /**
     * Optimized whereHas using EXISTS (fastest approach).
     *
     * @param string $relation Relationship method name
     * @param callable|null $callback Optional callback to constrain the relationship query
     * @return $this
     */
    private function whereHasExists(string $relation, ?callable $callback = null): self
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

        // Build EXISTS subquery
        $existsSubquery = $this->buildExistsSubquery($relationInstance, $table, $relationQuery);

        // Add EXISTS clause - much faster than COUNT(*)
        $this->whereRaw("EXISTS ({$existsSubquery})");

        return $this;
    }

    /**
     * whereHas with count comparison (for count != 1 cases).
     *
     * @param string $relation Relationship method name
     * @param callable|null $callback Optional callback to constrain the relationship query
     * @param string $operator Comparison operator
     * @param int $count Count threshold
     * @return $this
     */
    private function whereHasWithCount(string $relation, ?callable $callback = null, string $operator = '>=', int $count = 1): self
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

        // Build COUNT subquery (only when count comparison is needed)
        $countSubquery = $this->buildCountSubquery($relationInstance, $table, $relationQuery);

        // Add count comparison clause
        $this->whereRaw("({$countSubquery}) {$operator} ?", [$count]);

        return $this;
    }

    /**
     * OR version of whereHas using EXISTS for optimal performance.
     *
     * @param string $relation Relationship method name
     * @param callable|null $callback Optional callback to constrain the relationship query
     * @param string $operator Comparison operator (>=, =, etc.)
     * @param int $count Count threshold (default: 1)
     * @return $this
     */
    public function orWhereHas(string $relation, ?callable $callback = null, string $operator = '>=', int $count = 1): self
    {
        // For count-based queries (count != 1), use the count approach
        if ($count !== 1 || $operator !== '>=') {
            return $this->orWhereHasWithCount($relation, $callback, $operator, $count);
        }

        // For simple existence check, use optimized OR EXISTS
        return $this->orWhereHasExists($relation, $callback);
    }

    /**
     * OR version of optimized whereHas using EXISTS.
     *
     * @param string $relation Relationship method name
     * @param callable|null $callback Optional callback to constrain the relationship query
     * @return $this
     */
    private function orWhereHasExists(string $relation, ?callable $callback = null): self
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

        // Build EXISTS subquery
        $existsSubquery = $this->buildExistsSubquery($relationInstance, $table, $relationQuery);

        // Add OR EXISTS clause - much faster than COUNT(*)
        $this->orWhereRaw("EXISTS ({$existsSubquery})");

        return $this;
    }

    /**
     * OR version of whereHas with count comparison.
     *
     * @param string $relation Relationship method name
     * @param callable|null $callback Optional callback to constrain the relationship query
     * @param string $operator Comparison operator
     * @param int $count Count threshold
     * @return $this
     */
    private function orWhereHasWithCount(string $relation, ?callable $callback = null, string $operator = '>=', int $count = 1): self
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

        // Build COUNT subquery (only when count comparison is needed)
        $countSubquery = $this->buildCountSubquery($relationInstance, $table, $relationQuery);

        // Add OR count comparison clause
        $this->orWhereRaw("({$countSubquery}) {$operator} ?", [$count]);

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
        if ($relation instanceof \Toporia\Framework\Database\ORM\Relations\BelongsToMany) {
            // Get pivot table and keys for BelongsToMany
            $pivotTable = $this->getRelationProperty($relation, 'pivotTable');
            $foreignPivotKey = $this->getRelationProperty($relation, 'foreignPivotKey');
            $relatedPivotKey = $this->getRelationProperty($relation, 'relatedPivotKey');
            $parentKey = $this->getRelationProperty($relation, 'parentKey');
            $relatedKey = $this->getRelationProperty($relation, 'relatedKey');

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
            $pivotTable = $this->getRelationProperty($relation, 'pivotTable');
            $morphType = $this->getRelationProperty($relation, 'morphType');
            $morphId = $this->getRelationProperty($relation, 'foreignKey');
            $relatedPivotKey = $this->getRelationProperty($relation, 'relatedPivotKey');
            $parentKey = $this->getRelationProperty($relation, 'localKey');
            $relatedKey = $this->getRelationProperty($relation, 'relatedKey');

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
     * Get a property value from a relation object using reflection.
     *
     * This is internal framework code that needs to access protected/private
     * properties of relation classes for query building.
     *
     * @param object $relation Relation instance
     * @param string $property Property name
     * @return mixed Property value
     */
    private function getRelationProperty(object $relation, string $property): mixed
    {
        return (new \ReflectionProperty($relation, $property))->getValue($relation);
    }

    /**
     * Build EXISTS subquery for optimal performance (no counting).
     *
     * @param \Toporia\Framework\Database\Contracts\RelationInterface $relation Relation instance
     * @param string $parentTable Parent table name
     * @param \Toporia\Framework\Database\Query\QueryBuilder $relationQuery Relation query
     * @return string EXISTS subquery SQL
     */
    protected function buildExistsSubquery($relation, string $parentTable, $relationQuery): string
    {
        // Handle different relationship types
        if (
            $relation instanceof \Toporia\Framework\Database\ORM\Relations\BelongsToMany ||
            $relation instanceof \Toporia\Framework\Database\ORM\Relations\MorphToMany
        ) {
            return $this->buildPivotExistsSubquery($relation, $parentTable, $relationQuery);
        } else {
            return $this->buildSimpleExistsSubquery($relation, $parentTable, $relationQuery);
        }
    }

    /**
     * Build EXISTS subquery for simple relationships (HasOne, HasMany, BelongsTo, etc.).
     *
     * @param \Toporia\Framework\Database\Contracts\RelationInterface $relation Relation instance
     * @param string $parentTable Parent table name
     * @param \Toporia\Framework\Database\Query\QueryBuilder $relationQuery Relation query
     * @return string EXISTS subquery SQL
     */
    protected function buildSimpleExistsSubquery($relation, string $parentTable, $relationQuery): string
    {
        // Get foreign and local keys for simple relationships
        $foreignKey = $relation->getForeignKey();
        $localKey = $relation->getLocalKey();

        // Get relation table
        $relationTable = $relationQuery->getTable();

        // Use alias for self-referencing relationships
        $relationAlias = $parentTable === $relationTable ? "{$relationTable}_relation" : $relationTable;

        // Build EXISTS subquery - SELECT 1 is faster than SELECT COUNT(*)
        $fromClause = $parentTable === $relationTable ? "{$relationTable} AS {$relationAlias}" : $relationTable;
        $subquerySql = "SELECT 1 FROM {$fromClause} WHERE {$relationAlias}.{$foreignKey} = {$parentTable}.{$localKey}";

        // Add relation constraints
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

        // Add LIMIT 1 for maximum performance
        $subquerySql .= " LIMIT 1";

        return $subquerySql;
    }

    /**
     * Build EXISTS subquery for pivot relationships (BelongsToMany, MorphToMany).
     *
     * @param \Toporia\Framework\Database\Contracts\RelationInterface $relation Relation instance
     * @param string $parentTable Parent table name
     * @param \Toporia\Framework\Database\Query\QueryBuilder $relationQuery Relation query
     * @return string EXISTS subquery SQL
     */
    protected function buildPivotExistsSubquery($relation, string $parentTable, $relationQuery): string
    {
        if ($relation instanceof \Toporia\Framework\Database\ORM\Relations\BelongsToMany) {
            // Get pivot table and keys for BelongsToMany
            $pivotTable = $this->getRelationProperty($relation, 'pivotTable');
            $foreignPivotKey = $this->getRelationProperty($relation, 'foreignPivotKey');
            $relatedPivotKey = $this->getRelationProperty($relation, 'relatedPivotKey');
            $parentKey = $this->getRelationProperty($relation, 'parentKey');
            $relatedKey = $this->getRelationProperty($relation, 'relatedKey');

            // Get related table
            $relatedTable = $relationQuery->getTable();

            // Build EXISTS subquery with proper JOIN - SELECT 1 is faster
            $subquerySql = "SELECT 1 FROM {$pivotTable} " .
                "INNER JOIN {$relatedTable} ON {$pivotTable}.{$relatedPivotKey} = {$relatedTable}.{$relatedKey} " .
                "WHERE {$pivotTable}.{$foreignPivotKey} = {$parentTable}.{$parentKey}";
        } elseif ($relation instanceof \Toporia\Framework\Database\ORM\Relations\MorphToMany) {
            // Similar logic for MorphToMany
            $pivotTable = $this->getRelationProperty($relation, 'pivotTable');
            $morphType = $this->getRelationProperty($relation, 'morphType');
            $morphId = $this->getRelationProperty($relation, 'foreignKey');
            $relatedPivotKey = $this->getRelationProperty($relation, 'relatedPivotKey');
            $parentKey = $this->getRelationProperty($relation, 'localKey');
            $relatedKey = $this->getRelationProperty($relation, 'relatedKey');

            // Get related table and morph class
            $relatedTable = $relationQuery->getTable();
            $morphClass = get_class($relation->getParent());

            $subquerySql = "SELECT 1 FROM {$pivotTable} " .
                "INNER JOIN {$relatedTable} ON {$pivotTable}.{$relatedPivotKey} = {$relatedTable}.{$relatedKey} " .
                "WHERE {$pivotTable}.{$morphId} = {$parentTable}.{$parentKey} " .
                "AND {$pivotTable}.{$morphType} = '{$morphClass}'";
        }

        // Add relation constraints
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

        // Add LIMIT 1 for maximum performance
        $subquerySql .= " LIMIT 1";

        return $subquerySql;
    }

    /**
     * Build COUNT subquery (only when count comparison is needed).
     *
     * @param \Toporia\Framework\Database\Contracts\RelationInterface $relation Relation instance
     * @param string $parentTable Parent table name
     * @param \Toporia\Framework\Database\Query\QueryBuilder $relationQuery Relation query
     * @return string COUNT subquery SQL
     */
    protected function buildCountSubquery($relation, string $parentTable, $relationQuery): string
    {
        // Handle different relationship types
        if (
            $relation instanceof \Toporia\Framework\Database\ORM\Relations\BelongsToMany ||
            $relation instanceof \Toporia\Framework\Database\ORM\Relations\MorphToMany
        ) {
            return $this->buildPivotWhereHasSubquery($relation, $parentTable, $relationQuery);
        } else {
            return $this->buildSimpleWhereHasSubquery($relation, $parentTable, $relationQuery);
        }
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

        // Build COUNT subquery (only used when count comparison is needed)
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
        $normalizeMethod = [$this->modelClass, 'normalizeWithRelations'];
        $normalized = $normalizeMethod($relations);
        // Set eager load
        $this->setEagerLoad($normalized);

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
        // For count-based queries (count != 1), use the count approach
        if ($count !== 1 || $operator !== '<') {
            return $this->whereDoesntHaveWithCount($relation, $callback, $operator, $count);
        }

        // For simple existence check, use optimized NOT EXISTS
        return $this->whereDoesntHaveExists($relation, $callback);
    }

    /**
     * Optimized whereDoesntHave using NOT EXISTS (fastest approach).
     *
     * @param string $relation Relationship method name
     * @param callable|null $callback Optional callback to constrain the relationship query
     * @return $this
     */
    protected function whereDoesntHaveExists(string $relation, ?callable $callback = null): self
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

        // Build NOT EXISTS subquery
        $existsSubquery = $this->buildExistsSubquery($relationInstance, $table, $relationQuery);

        // Add NOT EXISTS clause - much faster than COUNT(*)
        $this->whereRaw("NOT EXISTS ({$existsSubquery})");

        return $this;
    }

    /**
     * whereDoesntHave with count comparison (for count != 1 cases).
     *
     * @param string $relation Relationship method name
     * @param callable|null $callback Optional callback to constrain the relationship query
     * @param string $operator Comparison operator
     * @param int $count Count threshold
     * @return $this
     */
    private function whereDoesntHaveWithCount(string $relation, ?callable $callback = null, string $operator = '<', int $count = 1): self
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

        // Build COUNT subquery (only when count comparison is needed)
        $countSubquery = $this->buildCountSubquery($relationInstance, $table, $relationQuery);

        // Add count comparison clause
        $this->whereRaw("({$countSubquery}) {$operator} ?", [$count]);

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
        // For count-based queries (count != 1), use the count approach
        if ($count !== 1 || $operator !== '<') {
            return $this->orWhereDoesntHaveWithCount($relation, $callback, $operator, $count);
        }

        // For simple existence check, use optimized OR NOT EXISTS
        return $this->orWhereDoesntHaveExists($relation, $callback);
    }

    /**
     * OR version of optimized whereDoesntHave using NOT EXISTS.
     *
     * @param string $relation Relationship method name
     * @param callable|null $callback Optional callback to constrain the relationship query
     * @return $this
     */
    private function orWhereDoesntHaveExists(string $relation, ?callable $callback = null): self
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

        // Build NOT EXISTS subquery
        $existsSubquery = $this->buildExistsSubquery($relationInstance, $table, $relationQuery);

        // Add OR NOT EXISTS clause - much faster than COUNT(*)
        $this->orWhereRaw("NOT EXISTS ({$existsSubquery})");

        return $this;
    }

    /**
     * OR version of whereDoesntHave with count comparison.
     *
     * @param string $relation Relationship method name
     * @param callable|null $callback Optional callback to constrain the relationship query
     * @param string $operator Comparison operator
     * @param int $count Count threshold
     * @return $this
     */
    private function orWhereDoesntHaveWithCount(string $relation, ?callable $callback = null, string $operator = '<', int $count = 1): self
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

        // Build COUNT subquery (only when count comparison is needed)
        $countSubquery = $this->buildCountSubquery($relationInstance, $table, $relationQuery);

        // Add OR count comparison clause
        $this->orWhereRaw("({$countSubquery}) {$operator} ?", [$count]);

        return $this;
    }

    /**
     * Filter models that don't have nested relationships.
     *
     * Supports dot notation for nested relationships like 'posts.comments'.
     * This is a Toporia exclusive feature - superior to Laravel.
     * Uses optimized EXISTS/NOT EXISTS for maximum performance.
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

        // Build nested query from inside out using EXISTS for optimal performance
        $nestedCallback = $callback;

        // Work backwards through the relationship chain
        for ($i = count($relations) - 1; $i >= 0; $i--) {
            $currentRelation = $relations[$i];
            $previousCallback = $nestedCallback;

            $nestedCallback = function ($query) use ($currentRelation, $previousCallback) {
                // Use standard whereDoesntHave which will automatically use EXISTS for simple cases
                $query->whereDoesntHave($currentRelation, $previousCallback);
            };
        }

        // Use EXISTS-based whereDoesntHave for the final relationship
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

        // Special handling for BelongsToMany relationships (many-to-many through pivot table)
        if ($relationInstance instanceof BelongsToMany) {
            $this->addBelongsToManyCountSelect($relationInstance, $relation, $table, $relationQuery);
            return;
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
     * Add count select for BelongsToMany relationships.
     *
     * For many-to-many relationships, we count records in the pivot table,
     * not the related table directly.
     *
     * @param BelongsToMany $relationInstance The BelongsToMany relation instance
     * @param string $relation The relation name
     * @param string $table The parent table name
     * @param QueryBuilder $relationQuery The relation query builder
     * @return void
     */
    private function addBelongsToManyCountSelect(
        BelongsToMany $relationInstance,
        string $relation,
        string $table,
        QueryBuilder $relationQuery
    ): void {
        // Get pivot table and keys using public methods
        $pivotTable = $relationInstance->getPivotTable();
        $foreignPivotKey = $relationInstance->getForeignPivotKey();
        $parentKey = $relationInstance->getParentKey();

        // Build subquery counting records in pivot table
        // Example: SELECT COUNT(*) FROM product_categories WHERE product_categories.product_id = products.id
        $pivotAlias = "{$pivotTable}_pivot";
        $subquery = "SELECT COUNT(*) FROM {$pivotTable} AS {$pivotAlias} WHERE {$pivotAlias}.{$foreignPivotKey} = {$table}.{$parentKey}";

        // Check if relation query has constraints on the related table
        // If so, we need to join the related table to apply those constraints
        $relationSql = $relationQuery->toSql();
        $hasRelatedConstraints = preg_match('/WHERE (.+?)(?:ORDER BY|LIMIT|$)/s', $relationSql, $matches);

        if ($hasRelatedConstraints) {
            $whereClause = $matches[1];

            // Get related table info using public methods
            $relatedTable = $relationInstance->getRelatedTable();
            $relatedPivotKey = $relationInstance->getRelatedPivotKey();
            $relatedKey = $relationInstance->getRelatedKey();

            // Join related table to apply constraints
            $relatedAlias = "{$relatedTable}_related";
            $subquery = "SELECT COUNT(*) FROM {$pivotTable} AS {$pivotAlias} " .
                "INNER JOIN {$relatedTable} AS {$relatedAlias} ON {$pivotAlias}.{$relatedPivotKey} = {$relatedAlias}.{$relatedKey} " .
                "WHERE {$pivotAlias}.{$foreignPivotKey} = {$table}.{$parentKey}";

            // Replace placeholders with actual values (safely quoted)
            $relationBindings = $relationQuery->getBindings();
            $boundWhereClause = $whereClause;
            foreach ($relationBindings as $binding) {
                $quoted = $this->quoteValue($binding);
                $boundWhereClause = preg_replace('/\?/', $quoted, $boundWhereClause, 1);
            }

            // Replace table references in where clause with alias
            $boundWhereClause = preg_replace('/\b' . preg_quote($relatedTable, '/') . '\./', "{$relatedAlias}.", $boundWhereClause);

            $subquery .= " AND ({$boundWhereClause})";
        }

        // Note: Pivot where constraints (wherePivot, wherePivotIn) are already applied
        // to the relationQuery, so they'll be included in the above handling

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

        // Special handling for BelongsToMany relationships (many-to-many through pivot table)
        if ($relationInstance instanceof BelongsToMany) {
            $this->addBelongsToManyAggregateSelect($relationInstance, $relation, $column, $function, $table, $relationQuery);
            return $this;
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
     * Add aggregate select for BelongsToMany relationships.
     *
     * For many-to-many relationships, the column can be:
     * 1. A column in the pivot table (e.g., 'sort_order' in product_categories)
     * 2. A column in the related table (e.g., 'name' in categories) - requires join
     *
     * By default, we assume the column is in the pivot table. If constraints exist
     * on the related table, we join it and can aggregate on either table.
     *
     * @param BelongsToMany $relationInstance The BelongsToMany relation instance
     * @param string $relation The relation name
     * @param string $column The column to aggregate (can be pivot or related table column)
     * @param string $function The aggregate function (SUM, AVG, MIN, MAX)
     * @param string $table The parent table name
     * @param QueryBuilder $relationQuery The relation query builder
     * @return void
     */
    private function addBelongsToManyAggregateSelect(
        BelongsToMany $relationInstance,
        string $relation,
        string $column,
        string $function,
        string $table,
        QueryBuilder $relationQuery
    ): void {
        // Get pivot table and keys using public methods
        $pivotTable = $relationInstance->getPivotTable();
        $foreignPivotKey = $relationInstance->getForeignPivotKey();
        $parentKey = $relationInstance->getParentKey();

        // Check if relation query has constraints on the related table
        $relationSql = $relationQuery->toSql();
        $hasRelatedConstraints = preg_match('/WHERE (.+?)(?:ORDER BY|LIMIT|$)/s', $relationSql, $matches);

        // Determine if column is in pivot table or related table
        // By default, assume pivot table. If column contains dot (e.g., "categories.name"),
        // it's explicitly from related table
        $isPivotColumn = !str_contains($column, '.');

        if ($hasRelatedConstraints || !$isPivotColumn) {
            // Need to join related table
            $relatedTable = $relationInstance->getRelatedTable();
            $relatedPivotKey = $relationInstance->getRelatedPivotKey();
            $relatedKey = $relationInstance->getRelatedKey();

            $pivotAlias = "{$pivotTable}_pivot";
            $relatedAlias = "{$relatedTable}_related";

            // Determine which table the column belongs to
            if (str_contains($column, '.')) {
                // Explicit table.column format
                [$tablePart, $columnPart] = explode('.', $column, 2);
                if ($tablePart === $relatedTable || $tablePart === 'categories') {
                    $aggregateColumn = "{$relatedAlias}.{$columnPart}";
                } else {
                    $aggregateColumn = "{$pivotAlias}.{$columnPart}";
                }
            } else {
                // Default: try pivot table first, but we'll join related table for constraints
                // If column doesn't exist in pivot, it should be in related table
                $aggregateColumn = "{$pivotAlias}.{$column}";
            }

            $subquery = "SELECT {$function}({$aggregateColumn}) FROM {$pivotTable} AS {$pivotAlias} " .
                "INNER JOIN {$relatedTable} AS {$relatedAlias} ON {$pivotAlias}.{$relatedPivotKey} = {$relatedAlias}.{$relatedKey} " .
                "WHERE {$pivotAlias}.{$foreignPivotKey} = {$table}.{$parentKey}";

            // Add constraints from relation query
            if ($hasRelatedConstraints) {
                $whereClause = $matches[1];

                // Replace placeholders with actual values (safely quoted)
                $relationBindings = $relationQuery->getBindings();
                $boundWhereClause = $whereClause;
                foreach ($relationBindings as $binding) {
                    $quoted = $this->quoteValue($binding);
                    $boundWhereClause = preg_replace('/\?/', $quoted, $boundWhereClause, 1);
                }

                // Replace table references in where clause with alias
                $boundWhereClause = preg_replace('/\b' . preg_quote($relatedTable, '/') . '\./', "{$relatedAlias}.", $boundWhereClause);

                $subquery .= " AND ({$boundWhereClause})";
            }
        } else {
            // Simple case: aggregate on pivot table column only
            $pivotAlias = "{$pivotTable}_pivot";
            $subquery = "SELECT {$function}({$pivotAlias}.{$column}) FROM {$pivotTable} AS {$pivotAlias} " .
                "WHERE {$pivotAlias}.{$foreignPivotKey} = {$table}.{$parentKey}";
        }

        $functionLower = strtolower($function);
        $columnAlias = "{$relation}_{$functionLower}_{$column}";

        // Ensure we select table.* along with the subquery (only once)
        $columns = $this->getColumns();
        if (empty($columns) || !in_array("{$table}.*", $columns, true)) {
            $this->select("{$table}.*");
        }

        $this->selectRaw("({$subquery}) AS {$columnAlias}");
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
    public function addQueryHint(string $type, array $values = []): self
    {
        // Delegate to parent QueryBuilder
        parent::addQueryHint($type, $values);
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
            $this->addQueryHint('no_cache');

            // Enable streaming mode flag
            $this->addQueryHint('stream_results');

            // Disable query result caching
            $this->disableQueryCaching();
        }

        return $this;
    }

    /**
     * Execute query in streaming mode for large datasets.
     *
     * @return \Generator<static> Generator yielding Model instances
     */
    public function stream(): \Generator
    {
        foreach (parent::stream() as $row) {
            // Hydrate each row into model instance
            $model = new $this->modelClass([]);

            // Set attributes directly (bypass mass assignment)
            foreach ($row as $key => $value) {
                $model->setAttribute($key, $value);
            }

            $model->exists = true;
            $model->syncOriginal();

            yield $model;
        }
    }

    /**
     * Process large model datasets in chunks using streaming.
     *
     * Overrides parent to work with ModelCollection instead of RowCollection.
     * Provides same signature as parent for consistency.
     *
     * @param int $chunkSize Number of models per chunk
     * @param callable $callback Callback to process each chunk
     * @return bool
     */
    public function streamChunk(int $chunkSize, callable $callback): bool
    {
        if ($chunkSize < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1');
        }

        $chunk = [];
        $count = 0;

        foreach ($this->stream() as $model) {
            $chunk[] = $model;
            $count++;

            if ($count >= $chunkSize) {
                // Create ModelCollection for chunk
                $collection = $this->newCollection($chunk);

                // Process chunk - same signature as parent (collection, count)
                $result = $callback($collection, $count);

                if ($result === false) {
                    return false;
                }

                // Reset for next chunk
                $chunk = [];
                $count = 0;
            }
        }

        // Process remaining models
        if (!empty($chunk)) {
            $collection = $this->newCollection($chunk);
            $callback($collection, $count);
        }

        return true;
    }

    /**
     * Create a new model collection.
     *
     * @param array $models
     * @return ModelCollection
     */
    private function newCollection(array $models = []): ModelCollection
    {
        return new ModelCollection($models);
    }

    /**
     * Enable query explanation for debugging.
     *
     * @param bool $analyze Whether to include execution statistics
     * @return $this
     */
    public function explain(bool $analyze = false): array
    {
        $sql = $this->toSql();
        $bindings = $this->getBindings();

        return $this->executeExplain($sql, $bindings, $analyze);
    }

    /**
     * Execute EXPLAIN query and return results.
     *
     * @param string $sql Base SQL query
     * @param array $bindings Query bindings
     * @param bool $analyze Whether to include execution statistics
     * @return array Explain results
     */
    private function executeExplain(string $sql, array $bindings, bool $analyze = false): array
    {
        $connection = $this->getConnection();
        $driver = $connection->getConfig()['driver'] ?? 'mysql';

        $explainSql = match ($driver) {
            'mysql' => $analyze ? "EXPLAIN ANALYZE {$sql}" : "EXPLAIN {$sql}",
            'pgsql' => $analyze ? "EXPLAIN (ANALYZE, BUFFERS) {$sql}" : "EXPLAIN {$sql}",
            'sqlite' => "EXPLAIN QUERY PLAN {$sql}",
            default => "EXPLAIN {$sql}"
        };

        try {
            $results = $connection->select($explainSql, $bindings);

            return [
                'driver' => $driver,
                'analyze' => $analyze,
                'original_sql' => $sql,
                'explain_sql' => $explainSql,
                'bindings' => $bindings,
                'results' => $results,
                'formatted' => $this->formatExplainResults($results, $driver, $analyze)
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'driver' => $driver,
                'sql' => $explainSql
            ];
        }
    }

    /**
     * Format explain results for better readability.
     *
     * @param array $results Raw explain results
     * @param string $driver Database driver
     * @param bool $analyze Whether analysis was performed
     * @return array Formatted results
     */
    private function formatExplainResults(array $results, string $driver, bool $analyze): array
    {
        if (empty($results)) {
            return ['message' => 'No explain results returned'];
        }

        return match ($driver) {
            'mysql' => $this->formatMySQLExplain($results, $analyze),
            'pgsql' => $this->formatPostgreSQLExplain($results, $analyze),
            'sqlite' => $this->formatSQLiteExplain($results),
            default => $results
        };
    }

    /**
     * Format MySQL explain results.
     *
     * @param array $results
     * @param bool $analyze
     * @return array
     */
    private function formatMySQLExplain(array $results, bool $analyze): array
    {
        $formatted = [];

        foreach ($results as $row) {
            $formatted[] = [
                'id' => $row['id'] ?? null,
                'select_type' => $row['select_type'] ?? null,
                'table' => $row['table'] ?? null,
                'type' => $row['type'] ?? null,
                'possible_keys' => $row['possible_keys'] ?? null,
                'key' => $row['key'] ?? null,
                'key_len' => $row['key_len'] ?? null,
                'ref' => $row['ref'] ?? null,
                'rows' => $row['rows'] ?? null,
                'filtered' => $row['filtered'] ?? null,
                'extra' => $row['Extra'] ?? null,
                'performance_analysis' => $this->analyzeMySQLPerformance($row)
            ];
        }

        return $formatted;
    }

    /**
     * Analyze MySQL performance from explain results.
     *
     * @param array $row
     * @return array
     */
    private function analyzeMySQLPerformance(array $row): array
    {
        $analysis = [];

        // Analyze access type
        $type = $row['type'] ?? '';
        $analysis['access_type'] = match ($type) {
            'const' => ['status' => 'excellent', 'message' => 'Constant time lookup'],
            'eq_ref' => ['status' => 'excellent', 'message' => 'Unique index lookup'],
            'ref' => ['status' => 'good', 'message' => 'Non-unique index lookup'],
            'range' => ['status' => 'acceptable', 'message' => 'Index range scan'],
            'index' => ['status' => 'poor', 'message' => 'Full index scan'],
            'ALL' => ['status' => 'bad', 'message' => 'Full table scan - consider adding index'],
            default => ['status' => 'unknown', 'message' => 'Unknown access type']
        };

        // Analyze rows examined
        $rows = (int)($row['rows'] ?? 0);
        $analysis['rows_examined'] = [
            'count' => $rows,
            'status' => match (true) {
                $rows <= 100 => 'excellent',
                $rows <= 1000 => 'good',
                $rows <= 10000 => 'acceptable',
                default => 'poor'
            }
        ];

        // Check for key usage
        $key = $row['key'] ?? null;
        $analysis['index_usage'] = [
            'using_index' => !empty($key),
            'index_name' => $key,
            'status' => empty($key) ? 'bad' : 'good'
        ];

        return $analysis;
    }

    /**
     * Format PostgreSQL explain results.
     *
     * @param array $results
     * @param bool $analyze
     * @return array
     */
    private function formatPostgreSQLExplain(array $results, bool $analyze): array
    {
        // PostgreSQL EXPLAIN returns text format
        return [
            'raw_output' => $results,
            'note' => 'PostgreSQL explain output is in text format'
        ];
    }

    /**
     * Format SQLite explain results.
     *
     * @param array $results
     * @return array
     */
    private function formatSQLiteExplain(array $results): array
    {
        return [
            'query_plan' => $results,
            'note' => 'SQLite query plan output'
        ];
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

    // =========================================================================
    // EAGER LOADING METHODS (ORM LAYER - NOT IN QUERY BUILDER)
    // =========================================================================

    /**
     * Set the relationships that should be eager loaded (ORM layer).
     *
     * @param array<string, callable|null> $relations
     * @return $this
     */
    public function setEagerLoad(array $relations): self
    {
        $this->eagerLoad = $relations;
        return $this;
    }

    /**
     * Get the relationships that should be eager loaded (ORM layer).
     *
     * @return array<string, callable|null>
     */
    public function getEagerLoad(): array
    {
        return $this->eagerLoad;
    }

    /**
     * Execute the query and return a DatabaseCollection (ModelCollection).
     *
     * Overrides parent get() method with same return type for compatibility.
     *
     * @return DatabaseCollection
     */
    public function get(): DatabaseCollection
    {
        return $this->getModels();
    }
}
