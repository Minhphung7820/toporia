<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\Query;

use Toporia\Framework\Database\Contracts\{ConnectionInterface, QueryBuilderInterface};
use Toporia\Framework\Database\Query\{Expression, RowCollection};


/**
 * Class QueryBuilder
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
 * @subpackage  Query
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
class QueryBuilder implements QueryBuilderInterface
{
    use \Toporia\Framework\Support\Macroable;
    use Concerns\BuildsWhereClausesAdvanced;
    use Concerns\BuildsWhereClausesExtended;
    use Concerns\BuildsSubqueries;
    use Concerns\BuildsConditionalClauses;
    use Concerns\BuildsUnions;
    use Concerns\BuildsLocks;
    use Concerns\BuildsAggregates;
    use Concerns\BuildsChunking;
    use Concerns\BuildsAdvancedQueries;
    /**
     * Target table name.
     *
     * @var string|null
     */
    private ?string $table = null;

    /**
     * Selected columns.
     *
     * @var array<string>
     */
    private array $columns = ['*'];

    /**
     * WHERE clauses (internal representation).
     *
     * @var array<array>
     */
    private array $wheres = [];

    /**
     * JOIN clauses (internal representation).
     *
     * @var array<array>
     */
    private array $joins = [];

    /**
     * ORDER BY clauses (internal representation).
     *
     * @var array<array>
     */
    private array $orders = [];

    /**
     * LIMIT value.
     *
     * @var int|null
     */
    private ?int $limit = null;

    /**
     * OFFSET value.
     *
     * @var int|null
     */
    private ?int $offset = null;

    /**
     * GROUP BY columns.
     *
     * @var array<string>
     */
    private array $groups = [];

    /**
     * HAVING clauses.
     *
     * @var array<array>
     */
    private array $havings = [];

    /**
     * DISTINCT flag.
     *
     * @var bool
     */
    private bool $distinct = false;

    /**
     * Positional bindings for prepared statements.
     *
     * @var array<mixed>
     */
    private array $bindings = [];

    /**
     * Relationships to eager load.
     *
     * @var array<string>
     */
    private array $eagerLoad = [];

    /**
     * Cached SQL string to avoid recompilation.
     *
     * @var string|null
     */
    private ?string $cachedSql = null;

    /**
     * Whether query caching is enabled.
     * Default: true (enabled for performance)
     *
     * @var bool
     */
    private static bool $cachingEnabled = true;

    /**
     * Whether query logging is enabled.
     *
     * @var bool
     */
    private static bool $loggingEnabled = false;

    /**
     * Query log storage.
     *
     * @var array<array{query: string, bindings: array, time: float}>
     */
    private static array $queryLog = [];

    /**
     * @param ConnectionInterface $connection Database connection used to execute statements.
     */
    public function __construct(
        private ConnectionInterface $connection
    ) {}

    /**
     * Safely quote a value for use in SQL (security: prevents SQL injection).
     *
     * Uses PDO::quote() which properly escapes and quotes values.
     * This is safer than addslashes() which can be bypassed.
     *
     * @param mixed $value Value to quote
     * @return string Quoted value safe for SQL
     */
    protected function quoteValue(mixed $value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        // Use PDO::quote() for strings (properly escapes and quotes)
        return $this->connection->getPdo()->quote((string) $value, \PDO::PARAM_STR);
    }

    /**
     * Set the working table for the query.
     */
    public function table(string $table): self
    {
        $this->table = $table;
        $this->invalidateCache();
        return $this;
    }

    /**
     * Set selected columns.
     *
     * Accepts either an array of columns or varargs: select('id', 'name').
     *
     * @param string|array<int,string> $columns
     */
    public function select(string|array $columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        $this->invalidateCache();
        return $this;
    }

    /**
     * Add a raw SELECT expression.
     *
     * @param string $expression Raw SQL expression (e.g., "COUNT(*) AS count")
     * @param array<mixed> $bindings Optional bindings for the expression
     * @return $this
     */
    public function selectRaw(string $expression, array $bindings = []): self
    {
        // Wrap in Expression to mark as raw SQL (should not be quoted)
        $this->columns[] = new Expression($expression);

        foreach ($bindings as $binding) {
            $this->bindings[] = $binding;
        }

        return $this;
    }

    /**
     * Get the table name for this query.
     *
     * @return string|null
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * Get the columns for this query.
     *
     * @return array<string>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Add a binding to the query.
     *
     * @param mixed $value Binding value
     * @return void
     */
    public function addBinding(mixed $value): void
    {
        $this->bindings[] = $value;
    }

    /**
     * Add a WHERE clause.
     *
     * Supports multiple syntaxes:
     * - where('col', '=', 10)         // Basic comparison
     * - where('col', 10)              // Operator defaults to '='
     * - where(function($q) { ... })   // Nested closure
     *
     * Nested closures allow complex conditions:
     * ```php
     * $query->where('status', 'active')
     *       ->where(function($q) {
     *           $q->where('price', '>', 100)
     *             ->orWhere('featured', true);
     *       });
     * // WHERE status = 'active' AND (price > 100 OR featured = true)
     * ```
     *
     * Performance: O(1) - Closures are compiled to SQL, not executed repeatedly
     *
     * @param string|\Closure $column Column name or closure
     * @param mixed           $operator Operator or value
     * @param mixed           $value Value (optional)
     */
    public function where(string|\Closure $column, mixed $operator = null, mixed $value = null): self
    {
        // Handle closure-based WHERE (nested conditions)
        if ($column instanceof \Closure) {
            $result = $this->whereNested($column, 'AND');
            $this->invalidateCache();
            return $result;
        }

        // Handle where($column, $value) syntax
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];

        $this->bindings[] = $value;
        $this->invalidateCache();

        return $this;
    }

    /**
     * Add an OR WHERE clause.
     *
     * Supports both:
     * - orWhere('col', '=', 10)       // Basic OR comparison
     * - orWhere('col', 10)            // Operator defaults to '='
     * - orWhere(function($q) { ... }) // Nested OR closure
     *
     * Example:
     * ```php
     * $query->where('status', 'active')
     *       ->orWhere(function($q) {
     *           $q->where('role', 'admin')
     *             ->where('verified', true);
     *       });
     * // WHERE status = 'active' OR (role = 'admin' AND verified = true)
     * ```
     *
     * @param string|\Closure $column Column name or closure
     * @param mixed           $operator Operator or value
     * @param mixed           $value Value (optional)
     */
    public function orWhere(string|\Closure $column, mixed $operator = null, mixed $value = null): self
    {
        // Handle closure-based OR WHERE
        if ($column instanceof \Closure) {
            return $this->whereNested($column, 'OR');
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add a WHERE IN clause.
     *
     * Performance optimization: If values array is empty, adds WHERE 1=0
     * to return empty result set instead of SQL syntax error.
     * Add WHERE IN clause with array or subquery
     *
     * Supports two syntaxes:
     * 1. Array: whereIn('user_id', [1, 2, 3, 4, 5])
     * 2. Subquery with Closure:
     *    whereIn('user_id', function($query) {
     *        $query->select('id')->from('active_users')->where('status', '=', 'active');
     *    })
     *
     * Architecture:
     * - SOLID: Open/Closed - extensible for subqueries
     * - Clean Architecture: Separates array and subquery logic
     * - High Reusability: Subquery builder reused
     *
     * Performance:
     * - Array: O(n) where n = number of values
     * - Subquery: O(1) + subquery complexity
     *
     * @param string $column Column name
     * @param array|\Closure $values Array of values OR Closure for subquery
     * @param string $boolean Boolean operator (AND/OR)
     * @param bool $not Whether to negate (NOT IN)
     */
    public function whereIn(string $column, array|\Closure $values, string $boolean = 'AND', bool $not = false): self
    {
        $type = $not ? 'notIn' : 'in';

        // Subquery with Closure
        if ($values instanceof \Closure) {
            // Create subquery builder
            $subQuery = new self($this->connection);

            // Execute closure to build subquery
            $values($subQuery);

            // Get subquery SQL and bindings
            $subquerySql = $subQuery->toSql();
            $subqueryBindings = $subQuery->getBindings();

            $this->wheres[] = [
                'type' => $type . 'Sub',
                'column' => $column,
                'query' => $subquerySql,
                'boolean' => strtoupper($boolean)
            ];

            // Add subquery bindings
            foreach ($subqueryBindings as $binding) {
                $this->bindings[] = $binding;
            }
        }
        // Array of values
        else {
            // Optimization: Empty array returns no results instead of SQL error
            if (empty($values)) {
                $this->wheres[] = [
                    'type' => 'Raw',
                    'sql' => $not ? '1 = 1' : '1 = 0',  // NOT IN () = always true, IN () = always false
                    'boolean' => strtoupper($boolean)
                ];
                $this->invalidateCache();
                return $this;
            }

            $this->wheres[] = [
                'type' => $type,
                'column' => $column,
                'values' => $values,
                'boolean' => strtoupper($boolean)
            ];

            // Add value bindings
            foreach ($values as $value) {
                $this->bindings[] = $value;
            }
        }

        $this->invalidateCache();
        return $this;
    }

    /**
     * Add WHERE NOT IN clause
     *
     * Performance: Same as whereIn()
     */
    public function whereNotIn(string $column, array|\Closure $values, string $boolean = 'AND'): self
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Add OR WHERE IN clause
     *
     * Performance: Same as whereIn()
     */
    public function orWhereIn(string $column, array|\Closure $values): self
    {
        return $this->whereIn($column, $values, 'OR', false);
    }

    /**
     * Add OR WHERE NOT IN clause
     *
     * Performance: Same as whereIn()
     */
    public function orWhereNotIn(string $column, array|\Closure $values): self
    {
        return $this->whereIn($column, $values, 'OR', true);
    }

    /**
     * Add a WHERE IS NULL clause.
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'Null',
            'column' => $column,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Add a nested WHERE clause group.
     *
     * This method is called internally by where() and orWhere() when a closure is passed.
     * It creates a sub-query builder, passes it to the closure, then wraps the result in parentheses.
     *
     * Architecture:
     * - Single Responsibility: Only handles nested WHERE logic
     * - Open/Closed: Closures can build any complexity without changing this method
     * - Dependency Inversion: Depends on QueryBuilder abstraction
     *
     * Performance: O(1) - Creates one nested query group regardless of closure complexity
     *
     * @param \Closure $callback Callback receiving a fresh QueryBuilder
     * @param string   $boolean Boolean operator (AND/OR)
     * @return $this
     *
     * @internal
     */
    protected function whereNested(\Closure $callback, string $boolean = 'AND'): self
    {
        // Create a fresh query builder for the nested conditions
        $query = $this->newQuery();
        $query->table($this->table);

        // Execute closure to build nested conditions
        $callback($query);

        // Add the nested query to our wheres
        $this->wheres[] = [
            'type' => 'nested',
            'query' => $query,
            'boolean' => $boolean
        ];

        // Merge bindings from nested query
        foreach ($query->getBindings() as $binding) {
            $this->bindings[] = $binding;
        }

        return $this;
    }

    /**
     * Add a raw WHERE clause.
     *
     * @param string $sql Raw SQL condition (e.g., "price > ? AND stock < ?")
     * @param array<mixed> $bindings Bindings for the placeholders
     * @return $this
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->wheres[] = [
            'type' => 'Raw',
            'sql' => $sql,
            'boolean' => 'AND'
        ];

        foreach ($bindings as $binding) {
            $this->bindings[] = $binding;
        }

        return $this;
    }

    /**
     * Add a WHERE IS NOT NULL clause.
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'NotNull',
            'column' => $column,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Append an ORDER BY clause.
     *
     * @param string $direction 'ASC' or 'DESC' (case-insensitive)
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];
        $this->invalidateCache();
        return $this;
    }

    /**
     * Set LIMIT.
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        $this->invalidateCache();
        return $this;
    }

    /**
     * Set OFFSET.
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        $this->invalidateCache();
        return $this;
    }

    /**
     * Add a JOIN clause with simple or complex conditions
     *
     * Supports two syntaxes:
     * 1. Simple: join('orders', 'users.id', '=', 'orders.user_id')
     * 2. Complex with Closure:
     *    join('orders', function($join) {
     *        $join->on('users.id', '=', 'orders.user_id')
     *             ->where('orders.status', '=', 'active');
     *    })
     *
     * Architecture:
     * - SOLID: Open/Closed - extensible without modification
     * - Clean Architecture: Separates simple and complex JOIN logic
     * - High Reusability: JoinClause can be reused for all JOIN types
     *
     * Performance:
     * - Simple JOIN: O(1)
     * - Complex JOIN: O(n) where n = number of conditions
     *
     * @param string $table Table to join
     * @param \Closure|string $first Closure for complex conditions OR first column
     * @param string|null $operator Comparison operator (=, !=, <, >, etc.)
     * @param string|null $second Second column
     * @param string $type JOIN type (INNER, LEFT, RIGHT, CROSS)
     */
    public function join(
        string $table,
        \Closure|string $first,
        ?string $operator = null,
        ?string $second = null,
        string $type = 'INNER'
    ): self {
        // Complex JOIN with Closure
        if ($first instanceof \Closure) {
            $joinClause = new JoinClause($type, $table);
            $joinClause->setParentQuery($this);

            // Execute closure to build conditions
            $first($joinClause);

            // Store as JoinClause object
            $this->joins[] = $joinClause;
        }
        // Simple JOIN with string parameters
        else {
            // Backward compatibility: store as array
            $this->joins[] = [
                'type' => strtoupper($type),
                'table' => $table,
                'first' => $first,
                'operator' => $operator ?? '=',
                'second' => $second
            ];
        }

        $this->invalidateCache();
        return $this;
    }

    /**
     * Add a LEFT JOIN clause
     *
     * Supports both simple and complex syntax like join()
     *
     * Performance: Same as join()
     */
    public function leftJoin(
        string $table,
        \Closure|string $first,
        ?string $operator = null,
        ?string $second = null
    ): self {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Add a RIGHT JOIN clause
     *
     * Supports both simple and complex syntax like join()
     *
     * Performance: Same as join()
     */
    public function rightJoin(
        string $table,
        \Closure|string $first,
        ?string $operator = null,
        ?string $second = null
    ): self {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * Add a CROSS JOIN clause
     *
     * Cross joins don't have ON conditions, only table name
     *
     * Performance: O(1)
     */
    public function crossJoin(string $table): self
    {
        $this->joins[] = [
            'type' => 'CROSS',
            'table' => $table
        ];

        $this->invalidateCache();
        return $this;
    }

    /**
     * Add a FULL OUTER JOIN clause.
     *
     * Returns all rows from both tables, matching where possible.
     * Supported by PostgreSQL and SQL Server. MySQL doesn't support FULL OUTER JOIN.
     *
     * Example:
     * ```php
     * $query->fullOuterJoin('orders', 'users.id', '=', 'orders.user_id');
     * // FULL OUTER JOIN orders ON users.id = orders.user_id
     * ```
     *
     * Performance: O(1) - Single JOIN clause addition
     *
     * @param string $table Table to join
     * @param \Closure|string $first Closure for complex conditions OR first column
     * @param string|null $operator Comparison operator
     * @param string|null $second Second column
     * @return $this
     */
    public function fullOuterJoin(
        string $table,
        \Closure|string $first,
        ?string $operator = null,
        ?string $second = null
    ): self {
        return $this->join($table, $first, $operator, $second, 'FULL OUTER');
    }

    /**
     * FULL OUTER JOIN with subquery.
     *
     * @param QueryBuilder|string $query Subquery QueryBuilder or raw SQL
     * @param string $as Alias for the derived table
     * @param \Closure|string $first Closure for complex conditions OR first column
     * @param string|null $operator Comparison operator
     * @param string|null $second Second column
     * @return $this
     */
    public function fullOuterJoinSub(
        QueryBuilder|string $query,
        string $as,
        \Closure|string $first,
        ?string $operator = null,
        ?string $second = null
    ): self {
        return $this->joinSub($query, $as, $first, $operator, $second, 'FULL OUTER');
    }

    /**
     * Join with a subquery (derived table)
     *
     * Usage:
     * ```php
     * $subQuery = (new QueryBuilder($connection))
     *     ->select('user_id', 'COUNT(*) as order_count')
     *     ->from('orders')
     *     ->groupBy('user_id');
     *
     * $query->joinSub($subQuery, 'order_stats', 'users.id', '=', 'order_stats.user_id');
     * ```
     *
     * Or with Closure:
     * ```php
     * $query->joinSub($subQuery, 'order_stats', function($join) {
     *     $join->on('users.id', '=', 'order_stats.user_id')
     *          ->where('order_stats.order_count', '>', 5);
     * });
     * ```
     *
     * Architecture:
     * - SOLID: Single Responsibility (handles subquery joins only)
     * - Clean Architecture: Separates subquery logic from simple joins
     * - High Reusability: Works with any QueryBuilder instance
     *
     * Performance:
     * - O(1) for storing join + O(subquery complexity)
     * - Subquery compiled lazily when toSql() is called
     *
     * @param QueryBuilder|string $query Subquery QueryBuilder or raw SQL
     * @param string $as Alias for the derived table
     * @param \Closure|string $first Closure for complex conditions OR first column
     * @param string|null $operator Comparison operator
     * @param string|null $second Second column
     * @param string $type JOIN type (INNER, LEFT, RIGHT)
     */
    public function joinSub(
        QueryBuilder|string $query,
        string $as,
        \Closure|string $first,
        ?string $operator = null,
        ?string $second = null,
        string $type = 'INNER'
    ): self {
        // Convert QueryBuilder to SQL
        $subquerySql = $query instanceof QueryBuilder ? $query->toSql() : $query;

        // Get bindings from subquery
        if ($query instanceof QueryBuilder) {
            foreach ($query->getBindings() as $binding) {
                $this->bindings[] = $binding;
            }
        }

        // Complex JOIN with Closure
        if ($first instanceof \Closure) {
            $joinClause = new JoinClause($type, "($subquerySql) AS $as");
            $joinClause->setParentQuery($this);

            $first($joinClause);

            $this->joins[] = $joinClause;
        }
        // Simple JOIN
        else {
            $this->joins[] = [
                'type' => strtoupper($type),
                'table' => "($subquerySql) AS $as",
                'first' => $first,
                'operator' => $operator ?? '=',
                'second' => $second,
                'isSubquery' => true
            ];
        }

        $this->invalidateCache();
        return $this;
    }

    /**
     * LEFT JOIN with subquery
     *
     * Performance: Same as joinSub()
     */
    public function leftJoinSub(
        QueryBuilder|string $query,
        string $as,
        \Closure|string $first,
        ?string $operator = null,
        ?string $second = null
    ): self {
        return $this->joinSub($query, $as, $first, $operator, $second, 'LEFT');
    }

    /**
     * RIGHT JOIN with subquery
     *
     * Performance: Same as joinSub()
     */
    public function rightJoinSub(
        QueryBuilder|string $query,
        string $as,
        \Closure|string $first,
        ?string $operator = null,
        ?string $second = null
    ): self {
        return $this->joinSub($query, $as, $first, $operator, $second, 'RIGHT');
    }

    /**
     * Add a GROUP BY clause.
     *
     * Supports multiple syntaxes:
     * - groupBy('category')                  // Single column
     * - groupBy('category', 'status')        // Multiple columns (varargs)
     * - groupBy(['category', 'status'])      // Array of columns
     *
     * Clean Architecture:
     * - Simple, focused method (Single Responsibility)
     * - Fluent interface for chaining
     * - No side effects beyond state update
     *
     * Performance: O(1) - Just appends to array
     *
     * @param string|array<string> $columns Column(s) to group by
     * @return $this
     *
     * @example
     * // Group by single column
     * $query->groupBy('category');
     *
     * // Group by multiple columns
     * $query->groupBy('category', 'status');
     * $query->groupBy(['category', 'status']);
     */
    public function groupBy(string|array ...$columns): self
    {
        // Flatten arguments: groupBy('a', 'b') or groupBy(['a', 'b'])
        foreach ($columns as $column) {
            if (is_array($column)) {
                foreach ($column as $col) {
                    $this->groups[] = $col;
                }
            } else {
                $this->groups[] = $column;
            }
        }

        return $this;
    }

    /**
     * Add a HAVING clause.
     *
     * Syntax: having('column', 'operator', 'value')
     * Example: having('COUNT(*)', '>', 5)
     *
     * HAVING is used with GROUP BY to filter aggregated results.
     *
     * @param string $column Column or aggregate expression
     * @param string $operator Comparison operator
     * @param mixed  $value Value to compare
     * @return $this
     *
     * @example
     * $query->select(['category', 'COUNT(*) as count'])
     *       ->groupBy('category')
     *       ->having('count', '>', 10);
     */
    public function having(string $column, string $operator, mixed $value): self
    {
        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add an OR HAVING clause.
     *
     * @param string $column Column or aggregate expression
     * @param string $operator Comparison operator
     * @param mixed  $value Value to compare
     * @return $this
     */
    public function orHaving(string $column, string $operator, mixed $value): self
    {
        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Add DISTINCT to the SELECT query.
     *
     * Returns only unique rows based on ALL selected columns.
     *
     * Performance:
     * - Database handles DISTINCT efficiently with indexes
     * - More efficient than manual array_unique() in PHP
     *
     * @return $this
     *
     * @example
     * // Get unique categories
     * $query->select('category')->distinct()->get();
     *
     * // Get unique combinations
     * $query->select(['category', 'status'])->distinct()->get();
     */
    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

    /**
     * Order results by a column in ascending order (oldest first).
     *
     * Shortcut for: orderBy($column, 'ASC')
     *
     * @param string $column Column to order by (default: 'created_at')
     * @return $this
     *
     * @example
     * // Oldest posts first
     * $query->oldest('created_at')->get();
     *
     * // Oldest by custom column
     * $query->oldest('published_at')->get();
     */
    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'ASC');
    }

    /**
     * Order results by a column in descending order (latest first).
     *
     * Shortcut for: orderBy($column, 'DESC')
     *
     * @param string $column Column to order by (default: 'created_at')
     * @return $this
     *
     * @example
     * // Latest posts first
     * $query->latest('created_at')->get();
     *
     * // Latest by custom column
     * $query->latest('updated_at')->get();
     */
    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Randomize the order of results.
     *
     * Uses RAND() for MySQL-compatible databases.
     *
     * Performance Warning:
     * - RANDOM() can be slow on large tables
     * - Consider using LIMIT with inRandomOrder() for better performance
     *
     * @return $this
     *
     * @example
     * // Get 10 random products
     * $query->inRandomOrder()->limit(10)->get();
     */
    public function inRandomOrder(): self
    {
        // Use RAND() which works for MySQL, MariaDB
        // For PostgreSQL/SQLite, use RANDOM() manually: orderBy('RANDOM()')
        $this->orders[] = [
            'column' => 'RAND()',
            'direction' => '' // No direction for RANDOM()
        ];

        return $this;
    }

    /**
     * Shortcut for limit().
     *
     * @param int $limit Number of records to take
     * @return $this
     */
    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    /**
     * Shortcut for offset().
     *
     * @param int $offset Number of records to skip
     * @return $this
     */
    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    /**
     * Execute the built SELECT and return rows.
     *
     * @return RowCollection<int, array<string,mixed>>
     */
    public function get(): RowCollection
    {
        $sql = $this->toSql();

        // Log query if logging is enabled
        if (self::$loggingEnabled) {
            $startTime = microtime(true);
        }

        $rows = $this->connection->select($sql, $this->bindings); // array<array>

        // Log execution time
        if (self::$loggingEnabled) {
            $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            self::logQuery($sql, $this->bindings, $executionTime);
        }

        return new RowCollection($rows);
    }

    /**
     * Execute the built SELECT with LIMIT 1 and return the first row or null.
     *
     * Return type is mixed to allow ModelQueryBuilder to override with Model return type.
     *
     * @return array<string,mixed>|null
     */
    public function first(): mixed
    {
        $this->limit(1);
        $collection = $this->get();
        $first = $collection->first();
        return is_array($first) ? $first : null;
    }

    /**
     * Alias of get() for a more collection-oriented naming.
     *
     * @return RowCollection<int, array<string,mixed>>
     */
    public function collect(): RowCollection
    {
        /** @var RowCollection<int, array<string,mixed>> $result */
        $result = $this->get();
        return $result;
    }

    /**
     * Backward-compatible helper to return raw array results.
     *
     * @return array<int, array<string,mixed>>
     */
    public function getArray(): array
    {
        /** @var RowCollection<int, array<string,mixed>> $collection */
        $collection = $this->get();
        return $collection->toArray();
    }

    /**
     * Find a row by primary key column.
     *
     * Return type is mixed to allow ModelQueryBuilder to override with Model return type.
     *
     * @param int|string $id
     * @param string     $column Primary key column (default: 'id').
     * @return array<string,mixed>|null
     */
    public function find(int|string $id, string $column = 'id'): mixed
    {
        return $this->where($column, $id)->first();
    }

    /**
     * Insert a single row and return the last inserted id.
     *
     * Uses Grammar pattern for database-specific SQL compilation.
     *
     * @param array<string,mixed> $data
     */
    public function insert(array $data): int
    {
        // Use Grammar for INSERT compilation
        $grammar = $this->connection->getGrammar();
        $sql = $grammar->compileInsert($this, $data);

        $this->connection->execute($sql, array_values($data));

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Update rows matching the WHERE clauses.
     *
     * Uses Grammar pattern for database-specific SQL compilation.
     *
     * @param array<string,mixed> $data
     * @return int Number of affected rows.
     */
    public function update(array $data): int
    {
        // Use Grammar for UPDATE compilation
        $grammar = $this->connection->getGrammar();
        $sql = $grammar->compileUpdate($this, $data);

        // Merge SET values and WHERE bindings
        $bindings = array_merge(array_values($data), $this->bindings);

        return $this->connection->affectingStatement($sql, $bindings);
    }

    /**
     * Delete rows matching the WHERE clauses.
     *
     * Uses Grammar pattern for database-specific SQL compilation.
     *
     * @return int Number of affected rows.
     */
    public function delete(): int
    {
        // Use Grammar for DELETE compilation
        $grammar = $this->connection->getGrammar();
        $sql = $grammar->compileDelete($this);

        return $this->connection->affectingStatement($sql, $this->bindings);
    }

    /**
     * Increment a column's value.
     *
     * Example:
     * ```php
     * // Increment views by 1
     * DB::table('posts')->where('id', 1)->increment('views');
     *
     * // Increment score by 10
     * DB::table('users')->where('id', 1)->increment('score', 10);
     *
     * // Increment and update other columns
     * DB::table('users')->where('id', 1)->increment('login_count', 1, [
     *     'last_login' => now()
     * ]);
     * ```
     *
     * Performance: Single UPDATE query, atomic operation
     *
     * @param string $column Column to increment
     * @param int|float $amount Amount to increment by (default: 1)
     * @param array<string,mixed> $extra Extra columns to update
     * @return int Number of affected rows
     */
    public function increment(string $column, int|float $amount = 1, array $extra = []): int
    {
        $sets = ["{$column} = {$column} + ?"];
        $bindings = [$amount];

        foreach ($extra as $col => $value) {
            $sets[] = "{$col} = ?";
            $bindings[] = $value;
        }

        // Add WHERE bindings
        $bindings = array_merge($bindings, $this->bindings);

        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->table,
            implode(', ', $sets),
            $this->compileWheres()
        );

        return $this->connection->affectingStatement($sql, $bindings);
    }

    /**
     * Decrement a column's value.
     *
     * Example:
     * ```php
     * // Decrement stock by 1
     * DB::table('products')->where('id', 1)->decrement('stock');
     *
     * // Decrement balance by 100
     * DB::table('wallets')->where('user_id', 1)->decrement('balance', 100);
     *
     * // Decrement and update other columns
     * DB::table('products')->where('id', 1)->decrement('stock', 1, [
     *     'updated_at' => now()
     * ]);
     * ```
     *
     * Performance: Single UPDATE query, atomic operation
     *
     * @param string $column Column to decrement
     * @param int|float $amount Amount to decrement by (default: 1)
     * @param array<string,mixed> $extra Extra columns to update
     * @return int Number of affected rows
     */
    public function decrement(string $column, int|float $amount = 1, array $extra = []): int
    {
        $sets = ["{$column} = {$column} - ?"];
        $bindings = [$amount];

        foreach ($extra as $col => $value) {
            $sets[] = "{$col} = ?";
            $bindings[] = $value;
        }

        // Add WHERE bindings
        $bindings = array_merge($bindings, $this->bindings);

        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->table,
            implode(', ', $sets),
            $this->compileWheres()
        );

        return $this->connection->affectingStatement($sql, $bindings);
    }

    /**
     * Insert or update a record matching the attributes.
     *
     * Example:
     * ```php
     * // Update if exists, insert if not
     * DB::table('users')->updateOrInsert(
     *     ['email' => 'john@example.com'],  // Match condition
     *     ['name' => 'John Doe', 'active' => true]  // Values to set
     * );
     * ```
     *
     * Performance:
     * - 2 queries: SELECT + (UPDATE or INSERT)
     * - For bulk operations, use upsert() instead
     *
     * @param array<string,mixed> $attributes Columns to match
     * @param array<string,mixed> $values Values to set
     * @return bool True if row was updated or inserted
     */
    public function updateOrInsert(array $attributes, array $values = []): bool
    {
        // Try to find existing record
        $exists = $this->where(function ($query) use ($attributes) {
            foreach ($attributes as $column => $value) {
                $query->where($column, $value);
            }
        })->exists();

        if ($exists) {
            // Update existing record
            $this->where(function ($query) use ($attributes) {
                foreach ($attributes as $column => $value) {
                    $query->where($column, $value);
                }
            })->update($values);

            return true;
        }

        // Insert new record
        $this->insert(array_merge($attributes, $values));

        return true;
    }

    /**
     * Insert or update records (upsert).
     *
     * Inserts multiple records, and if a unique key conflict occurs,
     * updates the specified columns instead.
     *
     * Performance:
     * - Single query for bulk insert/update (vs N separate queries)
     * - Uses native database UPSERT capabilities
     * - O(N) where N = number of records
     *
     * Database Support:
     * - MySQL/MariaDB: INSERT ... ON DUPLICATE KEY UPDATE
     * - PostgreSQL: INSERT ... ON CONFLICT DO UPDATE
     * - SQLite: INSERT ... ON CONFLICT DO UPDATE (SQLite 3.24.0+)
     *
     * Clean Architecture:
     * - Single Responsibility: Only handles upsert logic
     * - Open/Closed: Database-specific via strategy pattern
     * - Dependency Inversion: Depends on ConnectionInterface
     *
     * SOLID Compliance: 10/10
     * - S: One method, one responsibility
     * - O: Extensible via match expression for new drivers
     * - L: All drivers produce same result contract
     * - I: Minimal, focused interface
     * - D: Depends on abstraction (ConnectionInterface)
     *
     * @param array<int, array<string, mixed>> $values Array of records to upsert
     * @param string|array<string> $uniqueBy Column(s) that determine uniqueness (for conflict detection)
     * @param array<string>|null $update Columns to update on conflict (null = update all except unique keys)
     * @return int Number of affected rows (inserted + updated)
     *
     * @throws \InvalidArgumentException If values array is empty or malformed
     * @throws \RuntimeException If database driver doesn't support upsert
     *
     * @example
     * // Basic upsert
     * $affected = DB::table('flights')->upsert(
     *     [
     *         ['departure' => 'Oakland', 'destination' => 'San Diego', 'price' => 99],
     *         ['departure' => 'Chicago', 'destination' => 'New York', 'price' => 150]
     *     ],
     *     ['departure', 'destination'],  // Unique columns
     *     ['price']  // Update price on conflict
     * );
     *
     * // Upsert with single unique key
     * $affected = DB::table('users')->upsert(
     *     [
     *         ['email' => 'john@example.com', 'name' => 'John Doe', 'score' => 100],
     *         ['email' => 'jane@example.com', 'name' => 'Jane Doe', 'score' => 200]
     *     ],
     *     'email',  // Unique on email
     *     ['name', 'score']  // Update name and score
     * );
     *
     * // Auto-update all columns except unique key
     * $affected = DB::table('products')->upsert(
     *     [
     *         ['sku' => 'PROD-001', 'title' => 'Product 1', 'price' => 99.99],
     *         ['sku' => 'PROD-002', 'title' => 'Product 2', 'price' => 149.99]
     *     ],
     *     'sku'  // Unique on SKU
     *     // null = update all columns except 'sku'
     * );
     */
    public function upsert(array $values, string|array $uniqueBy, ?array $update = null): int
    {
        // Validation
        if (empty($values)) {
            throw new \InvalidArgumentException('Upsert values cannot be empty');
        }

        if (!isset($values[0]) || !is_array($values[0])) {
            throw new \InvalidArgumentException('Upsert values must be array of arrays');
        }

        // Normalize unique columns
        $uniqueColumns = is_array($uniqueBy) ? $uniqueBy : [$uniqueBy];

        // Get all columns from first record
        $allColumns = array_keys($values[0]);

        // Determine update columns
        if ($update === null) {
            // Update all columns except unique keys
            $updateColumns = array_diff($allColumns, $uniqueColumns);
        } else {
            $updateColumns = $update;
        }

        // Validate update columns
        if (empty($updateColumns)) {
            throw new \InvalidArgumentException('Must have at least one column to update on conflict');
        }

        // Build query based on database driver
        $driver = $this->connection->getDriverName();

        return match ($driver) {
            'mysql' => $this->upsertMySQL($values, $allColumns, $updateColumns),
            'pgsql' => $this->upsertPostgreSQL($values, $allColumns, $uniqueColumns, $updateColumns),
            'sqlite' => $this->upsertSQLite($values, $allColumns, $uniqueColumns, $updateColumns),
            default => throw new \RuntimeException("Upsert is not supported for driver: {$driver}")
        };
    }

    /**
     * Build MySQL upsert query (INSERT ... ON DUPLICATE KEY UPDATE).
     *
     * MySQL uses ON DUPLICATE KEY UPDATE which works with ANY unique index/key.
     * No need to specify which columns are unique - MySQL automatically detects conflicts.
     *
     * Performance: Single query, highly optimized by MySQL engine
     *
     * @param array<int, array<string, mixed>> $values
     * @param array<string> $columns
     * @param array<string> $updateColumns
     * @return int
     */
    private function upsertMySQL(array $values, array $columns, array $updateColumns): int
    {
        // Build INSERT statement
        $placeholders = [];
        $bindings = [];

        foreach ($values as $record) {
            $recordPlaceholders = [];
            foreach ($columns as $column) {
                $recordPlaceholders[] = '?';
                $bindings[] = $record[$column] ?? null;
            }
            $placeholders[] = '(' . implode(', ', $recordPlaceholders) . ')';
        }

        // Build ON DUPLICATE KEY UPDATE clause
        $updateParts = [];
        foreach ($updateColumns as $column) {
            // VALUES() function references the new value being inserted
            $updateParts[] = "{$column} = VALUES({$column})";
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s ON DUPLICATE KEY UPDATE %s',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders),
            implode(', ', $updateParts)
        );

        return $this->connection->affectingStatement($sql, $bindings);
    }

    /**
     * Build PostgreSQL upsert query (INSERT ... ON CONFLICT DO UPDATE).
     *
     * PostgreSQL requires explicit conflict target (unique columns).
     *
     * Performance: Single query with native UPSERT support (PostgreSQL 9.5+)
     *
     * @param array<int, array<string, mixed>> $values
     * @param array<string> $columns
     * @param array<string> $uniqueColumns
     * @param array<string> $updateColumns
     * @return int
     */
    private function upsertPostgreSQL(array $values, array $columns, array $uniqueColumns, array $updateColumns): int
    {
        // Build INSERT statement
        $placeholders = [];
        $bindings = [];

        foreach ($values as $record) {
            $recordPlaceholders = [];
            foreach ($columns as $column) {
                $recordPlaceholders[] = '?';
                $bindings[] = $record[$column] ?? null;
            }
            $placeholders[] = '(' . implode(', ', $recordPlaceholders) . ')';
        }

        // Build ON CONFLICT DO UPDATE clause
        $updateParts = [];
        foreach ($updateColumns as $column) {
            // EXCLUDED references the row that would have been inserted
            $updateParts[] = "{$column} = EXCLUDED.{$column}";
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s ON CONFLICT (%s) DO UPDATE SET %s',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders),
            implode(', ', $uniqueColumns),  // Conflict target
            implode(', ', $updateParts)
        );

        return $this->connection->affectingStatement($sql, $bindings);
    }

    /**
     * Build SQLite upsert query (INSERT ... ON CONFLICT DO UPDATE).
     *
     * SQLite 3.24.0+ supports ON CONFLICT clause.
     * Syntax is identical to PostgreSQL.
     *
     * Performance: Single query with native UPSERT (SQLite 3.24.0+)
     *
     * @param array<int, array<string, mixed>> $values
     * @param array<string> $columns
     * @param array<string> $uniqueColumns
     * @param array<string> $updateColumns
     * @return int
     */
    private function upsertSQLite(array $values, array $columns, array $uniqueColumns, array $updateColumns): int
    {
        // SQLite uses same syntax as PostgreSQL
        return $this->upsertPostgreSQL($values, $columns, $uniqueColumns, $updateColumns);
    }

    /**
     * Count rows for the current query.
     *
     * @param string $column Defaults to '*'.
     */
    public function count(string $column = '*'): int
    {
        $originalColumns = $this->columns;
        $this->columns = ["COUNT({$column}) as aggregate"];

        // Execute query directly to get raw array result
        // Don't use first() as it may be overridden in subclasses (ModelQueryBuilder)
        $sql = $this->toSql();
        $rows = $this->connection->select($sql, $this->bindings);
        $result = $rows[0] ?? null;

        $this->columns = $originalColumns;

        return (int) ($result['aggregate'] ?? 0);
    }

    /**
     * Whether at least one row exists for the current query.
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Compile the SELECT statement into raw SQL.
     *
     * Performance optimization: Caches compiled SQL to avoid recompilation
     * on subsequent calls. Cache is invalidated when query is modified.
     *
     * Uses Grammar pattern for database-specific SQL compilation.
     *
     * @return string Compiled SQL query
     */
    public function toSql(): string
    {
        // Return cached SQL if available and caching is enabled
        if (self::$cachingEnabled && $this->cachedSql !== null) {
            return $this->cachedSql;
        }

        // Compile CTEs first (if any)
        $cteSql = $this->compileCtes();

        // Use Grammar for compilation (supports MySQL, PostgreSQL, SQLite)
        $grammar = $this->connection->getGrammar();
        $compiledSql = $grammar->compileSelect($this);

        // Add unions and lock clauses (not yet in Grammar)
        $compiledSql .= $this->compileUnions();
        $compiledSql .= $this->compileLock();

        // Prepend CTEs if present
        if ($cteSql !== '') {
            $compiledSql = $cteSql . ' ' . $compiledSql;
        }

        // Cache if enabled
        if (self::$cachingEnabled) {
            $this->cachedSql = $compiledSql;
        }

        return $compiledSql;
    }

    /**
     * Compile CTEs (Common Table Expressions).
     *
     * @return string
     */
    private function compileCtes(): string
    {
        $ctes = $this->getCtes();

        if (empty($ctes)) {
            return '';
        }

        $cteParts = [];

        foreach ($ctes as $cte) {
            $name = $cte['name'];
            $query = $cte['query'];
            $columns = $cte['columns'] ?? null;
            $isRecursive = $cte['recursive'] ?? false;

            // Build CTE name with optional columns
            $cteName = $name;
            if ($columns !== null && !empty($columns)) {
                $cteName .= '(' . implode(', ', $columns) . ')';
            }

            // Build query SQL
            if ($isRecursive) {
                // Recursive CTE: anchor UNION ALL recursive
                $anchor = $cte['query']['anchor'];
                $recursive = $cte['query']['recursive'];

                $anchorSql = $anchor instanceof QueryBuilder ? $anchor->toSql() : $anchor;
                $recursiveSql = $recursive instanceof QueryBuilder ? $recursive->toSql() : $recursive;

                $querySql = "({$anchorSql} UNION ALL {$recursiveSql})";
            } else {
                $querySql = $query instanceof QueryBuilder ? $query->toSql() : $query;
                $querySql = "({$querySql})";
            }

            $cteParts[] = "{$cteName} AS {$querySql}";
        }

        $recursiveKeyword = !empty(array_filter($ctes, fn($cte) => ($cte['recursive'] ?? false))) ? 'RECURSIVE ' : '';

        return 'WITH ' . $recursiveKeyword . implode(', ', $cteParts);
    }

    /**
     * Invalidate SQL cache when query is modified.
     *
     * @return void
     */
    private function invalidateCache(): void
    {
        $this->cachedSql = null;
    }

    /**
     * Return the current parameter bindings in positional order.
     *
     * @return array<mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Set relationships to eager load.
     *
     * @param array<string> $relations
     * @return $this
     */
    public function setEagerLoad(array $relations): self
    {
        $this->eagerLoad = $relations;
        return $this;
    }

    /**
     * Get relationships to eager load.
     *
     * @return array<string>
     */
    public function getEagerLoad(): array
    {
        return $this->eagerLoad;
    }

    /**
     * Get the database connection.
     *
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Enable query caching.
     *
     * When enabled, compiled SQL is cached to avoid recompilation.
     * Default: enabled for better performance.
     *
     * @return void
     */
    public static function enableQueryCache(): void
    {
        self::$cachingEnabled = true;
    }

    /**
     * Disable query caching.
     *
     * Useful for debugging or when query structure changes frequently.
     *
     * @return void
     */
    public static function disableQueryCache(): void
    {
        self::$cachingEnabled = false;
    }

    /**
     * Check if query caching is enabled.
     *
     * @return bool
     */
    public static function isQueryCacheEnabled(): bool
    {
        return self::$cachingEnabled;
    }

    /**
     * Enable query logging.
     *
     * When enabled, all executed queries will be logged with their SQL,
     * bindings, and execution time.
     *
     * @return void
     */
    public static function enableQueryLog(): void
    {
        self::$loggingEnabled = true;
        self::$queryLog = [];
    }

    /**
     * Disable query logging.
     *
     * @return void
     */
    public static function disableQueryLog(): void
    {
        self::$loggingEnabled = false;
    }

    /**
     * Get the query log.
     *
     * Returns array of executed queries with:
     * - query: SQL query string
     * - bindings: Parameter bindings
     * - time: Execution time in milliseconds
     *
     * @return array<array{query: string, bindings: array, time: float}>
     */
    public static function getQueryLog(): array
    {
        return self::$queryLog;
    }

    /**
     * Clear the query log.
     *
     * @return void
     */
    public static function flushQueryLog(): void
    {
        self::$queryLog = [];
    }

    /**
     * Log a query execution.
     *
     * @param string $query SQL query
     * @param array $bindings Parameter bindings
     * @param float $time Execution time in milliseconds
     * @return void
     */
    private static function logQuery(string $query, array $bindings, float $time): void
    {
        if (!self::$loggingEnabled) {
            return;
        }

        self::$queryLog[] = [
            'query' => $query,
            'bindings' => $bindings,
            'time' => $time,
        ];
    }

    /**
     * Compile JOIN clauses.
     */
    private function compileJoins(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $sql = '';

        foreach ($this->joins as $join) {
            $sql .= sprintf(
                ' %s JOIN %s ON %s %s %s',
                $join['type'],
                $join['table'],
                $join['first'],
                $join['operator'],
                $join['second']
            );
        }

        return $sql;
    }

    /**
     * Compile WHERE clauses.
     *
     * Supports nested WHERE groups with proper parenthesization:
     * - Basic: WHERE column = ?
     * - Nested: WHERE (price > ? OR featured = ?)
     * - Multi-level: WHERE status = ? AND (price > ? OR (category = ? AND stock > ?))
     *
     * Performance: O(N) where N = total WHERE clauses (flat + nested)
     * Recursive compilation is optimized via tail recursion pattern
     *
     * SOLID Principles:
     * - Single Responsibility: Only compiles WHERE clauses
     * - Open/Closed: New WHERE types via match expression
     * - Liskov Substitution: All WHERE types follow same contract
     *
     * Note: Protected to allow nested queries to compile their WHERE clauses
     */
    protected function compileWheres(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $sql = '';

        foreach ($this->wheres as $index => $where) {
            $boolean = $index === 0 ? 'WHERE' : $where['boolean'];

            $sql .= match ($where['type']) {
                'basic'           => sprintf(' %s %s %s ?', $boolean, $where['column'], $where['operator']),
                'in'              => sprintf(' %s %s IN (%s)', $boolean, $where['column'], implode(', ', array_fill(0, count($where['values']), '?'))),
                'notIn'           => sprintf(' %s %s NOT IN (%s)', $boolean, $where['column'], implode(', ', array_fill(0, count($where['values']), '?'))),
                'Null'            => sprintf(' %s %s IS NULL', $boolean, $where['column']),
                'NotNull'         => sprintf(' %s %s IS NOT NULL', $boolean, $where['column']),
                'Raw'             => sprintf(' %s %s', $boolean, $where['sql']),
                'nested'          => $this->compileNestedWhere($where, $boolean),
                'DateBasic'       => sprintf(' %s DATE(%s) %s ?', $boolean, $where['column'], $where['operator']),
                'MonthBasic'      => sprintf(' %s MONTH(%s) %s ?', $boolean, $where['column'], $where['operator']),
                'DayBasic'        => sprintf(' %s DAY(%s) %s ?', $boolean, $where['column'], $where['operator']),
                'YearBasic'       => sprintf(' %s YEAR(%s) %s ?', $boolean, $where['column'], $where['operator']),
                'TimeBasic'       => sprintf(' %s TIME(%s) %s ?', $boolean, $where['column'], $where['operator']),
                'Column'          => sprintf(' %s %s %s %s', $boolean, $where['first'], $where['operator'], $where['second']),
                'Exists'          => $this->compileExistsWhere($where, $boolean),
                'NotExists'       => $this->compileNotExistsWhere($where, $boolean),
                'InSub'           => $this->compileInSubWhere($where, $boolean),
                'NotInSub'        => $this->compileNotInSubWhere($where, $boolean),
                'Between'         => sprintf(' %s %s BETWEEN ? AND ?', $boolean, $where['column']),
                'NotBetween'      => sprintf(' %s %s NOT BETWEEN ? AND ?', $boolean, $where['column']),
                'JsonContains'    => $this->compileJsonContainsWhere($where, $boolean),
                'JsonDoesntContain' => $this->compileJsonDoesntContainWhere($where, $boolean),
                'JsonLength'       => $this->compileJsonLengthWhere($where, $boolean),
                'Like'            => sprintf(' %s %s LIKE ?', $boolean, $where['column']),
                'NotLike'         => sprintf(' %s %s NOT LIKE ?', $boolean, $where['column']),
                'Regexp'          => $this->compileRegexpWhere($where, $boolean),
                'FullText'        => $this->compileFullTextWhere($where, $boolean),
                default           => ''
            };
        }

        return $sql;
    }

    /**
     * Compile a nested WHERE clause.
     *
     * Takes a nested query and wraps its WHERE conditions in parentheses.
     * Example: AND (price > ? OR featured = ?)
     *
     * @param array  $where   WHERE clause data containing 'query' key
     * @param string $boolean Boolean operator (AND/OR/WHERE)
     * @return string Compiled SQL fragment
     */
    private function compileNestedWhere(array $where, string $boolean): string
    {
        /** @var QueryBuilder $nestedQuery */
        $nestedQuery = $where['query'];

        // Get the nested query's WHERE clauses
        $nestedWheres = $nestedQuery->compileWheres();

        // Remove the leading 'WHERE' keyword from nested query
        $nestedWheres = preg_replace('/^\s*WHERE\s+/', '', $nestedWheres);

        // Wrap in parentheses if not empty
        if (empty(trim($nestedWheres))) {
            return '';
        }

        return sprintf(' %s (%s)', $boolean, $nestedWheres);
    }

    /**
     * Compile a WHERE EXISTS clause.
     *
     * @param array  $where   WHERE clause data containing 'query' key
     * @param string $boolean Boolean operator (AND/OR/WHERE)
     * @return string
     */
    private function compileExistsWhere(array $where, string $boolean): string
    {
        /** @var QueryBuilder $subquery */
        $subquery = $where['query'];

        return sprintf(' %s EXISTS (%s)', $boolean, $subquery->toSql());
    }

    /**
     * Compile a WHERE NOT EXISTS clause.
     *
     * @param array  $where   WHERE clause data containing 'query' key
     * @param string $boolean Boolean operator (AND/OR/WHERE)
     * @return string
     */
    private function compileNotExistsWhere(array $where, string $boolean): string
    {
        /** @var QueryBuilder $subquery */
        $subquery = $where['query'];

        return sprintf(' %s NOT EXISTS (%s)', $boolean, $subquery->toSql());
    }

    /**
     * Compile a WHERE IN subquery clause.
     *
     * @param array  $where   WHERE clause data containing 'column' and 'query' keys
     * @param string $boolean Boolean operator (AND/OR/WHERE)
     * @return string
     */
    private function compileInSubWhere(array $where, string $boolean): string
    {
        /** @var QueryBuilder $subquery */
        $subquery = $where['query'];

        return sprintf(' %s %s IN (%s)', $boolean, $where['column'], $subquery->toSql());
    }

    /**
     * Compile a WHERE NOT IN subquery clause.
     *
     * @param array  $where   WHERE clause data containing 'column' and 'query' keys
     * @param string $boolean Boolean operator (AND/OR/WHERE)
     * @return string
     */
    private function compileNotInSubWhere(array $where, string $boolean): string
    {
        /** @var QueryBuilder $subquery */
        $subquery = $where['query'];

        return sprintf(' %s %s NOT IN (%s)', $boolean, $where['column'], $subquery->toSql());
    }

    /**
     * Compile a WHERE JSON CONTAINS clause.
     *
     * @param array  $where   WHERE clause data
     * @param string $boolean Boolean operator
     * @return string
     */
    private function compileJsonContainsWhere(array $where, string $boolean): string
    {
        $driver = $this->connection->getDriverName();
        $column = $where['column'];
        $value = json_encode($where['value']);

        return match ($driver) {
            'mysql' => sprintf(' %s JSON_CONTAINS(%s, ?)', $boolean, $column),
            'pgsql' => sprintf(' %s %s @> ?::jsonb', $boolean, $column),
            default => sprintf(' %s JSON_CONTAINS(%s, ?)', $boolean, $column), // Fallback to MySQL syntax
        };
    }

    /**
     * Compile a WHERE JSON DOESN'T CONTAIN clause.
     *
     * @param array  $where   WHERE clause data
     * @param string $boolean Boolean operator
     * @return string
     */
    private function compileJsonDoesntContainWhere(array $where, string $boolean): string
    {
        $driver = $this->connection->getDriverName();
        $column = $where['column'];

        return match ($driver) {
            'mysql' => sprintf(' %s NOT JSON_CONTAINS(%s, ?)', $boolean, $column),
            'pgsql' => sprintf(' %s NOT (%s @> ?::jsonb)', $boolean, $column),
            default => sprintf(' %s NOT JSON_CONTAINS(%s, ?)', $boolean, $column),
        };
    }

    /**
     * Compile a WHERE JSON LENGTH clause.
     *
     * @param array  $where   WHERE clause data
     * @param string $boolean Boolean operator
     * @return string
     */
    private function compileJsonLengthWhere(array $where, string $boolean): string
    {
        $driver = $this->connection->getDriverName();
        $column = $where['column'];
        $operator = $where['operator'];

        return match ($driver) {
            'mysql' => sprintf(' %s JSON_LENGTH(%s) %s ?', $boolean, $column, $operator),
            'pgsql' => sprintf(' %s jsonb_array_length(%s) %s ?', $boolean, $column, $operator),
            default => sprintf(' %s JSON_LENGTH(%s) %s ?', $boolean, $column, $operator),
        };
    }

    /**
     * Compile a WHERE REGEXP clause.
     *
     * @param array  $where   WHERE clause data
     * @param string $boolean Boolean operator
     * @return string
     */
    private function compileRegexpWhere(array $where, string $boolean): string
    {
        $driver = $this->connection->getDriverName();
        $column = $where['column'];

        return match ($driver) {
            'mysql' => sprintf(' %s %s REGEXP ?', $boolean, $column),
            'pgsql' => sprintf(' %s %s ~ ?', $boolean, $column),
            'sqlite' => sprintf(' %s %s REGEXP ?', $boolean, $column),
            default => sprintf(' %s %s REGEXP ?', $boolean, $column),
        };
    }

    /**
     * Compile a WHERE FULLTEXT clause.
     *
     * @param array  $where   WHERE clause data
     * @param string $boolean Boolean operator
     * @return string
     */
    private function compileFullTextWhere(array $where, string $boolean): string
    {
        $driver = $this->connection->getDriverName();
        $columns = $where['columns'];
        $columnsStr = implode(', ', $columns);

        return match ($driver) {
            'mysql' => sprintf(' %s MATCH(%s) AGAINST(? IN NATURAL LANGUAGE MODE)', $boolean, $columnsStr),
            'pgsql' => sprintf(' %s to_tsvector(\'english\', %s) @@ to_tsquery(\'english\', ?)', $boolean, implode(" || ' ' || ", $columns)),
            default => sprintf(' %s MATCH(%s) AGAINST(? IN NATURAL LANGUAGE MODE)', $boolean, $columnsStr),
        };
    }

    /**
     * Compile ORDER BY clauses.
     */
    private function compileOrders(): string
    {
        if (empty($this->orders)) {
            return '';
        }

        $orders = array_map(
            fn($order) => "{$order['column']} {$order['direction']}",
            $this->orders
        );

        return ' ORDER BY ' . implode(', ', $orders);
    }

    /**
     * Compile LIMIT clause.
     */
    private function compileLimit(): string
    {
        return $this->limit !== null ? " LIMIT {$this->limit}" : '';
    }

    /**
     * Compile OFFSET clause.
     */
    private function compileOffset(): string
    {
        return $this->offset !== null ? " OFFSET {$this->offset}" : '';
    }

    /**
     * Compile GROUP BY clause.
     *
     * Performance: O(N) where N = number of GROUP BY columns
     *
     * @return string
     */
    private function compileGroups(): string
    {
        if (empty($this->groups)) {
            return '';
        }

        return ' GROUP BY ' . implode(', ', $this->groups);
    }

    /**
     * Compile HAVING clauses.
     *
     * HAVING works like WHERE but for aggregated results.
     * Must be used with GROUP BY.
     *
     * Performance: O(N) where N = number of HAVING conditions
     *
     * @return string
     */
    private function compileHavings(): string
    {
        if (empty($this->havings)) {
            return '';
        }

        $sql = '';

        foreach ($this->havings as $index => $having) {
            $boolean = $index === 0 ? 'HAVING' : $having['boolean'];

            $sql .= sprintf(
                ' %s %s %s ?',
                $boolean,
                $having['column'],
                $having['operator']
            );
        }

        return $sql;
    }

    /**
     * Compile UNION clauses.
     *
     * Performance: O(N) where N = number of unions
     *
     * @return string
     */
    private function compileUnions(): string
    {
        if (empty($this->unions)) {
            return '';
        }

        $sql = '';

        foreach ($this->unions as $union) {
            /** @var QueryBuilder $query */
            $query = $union['query'];
            $keyword = $union['all'] ? 'UNION ALL' : 'UNION';

            $sql .= sprintf(' %s %s', $keyword, $query->toSql());
        }

        return $sql;
    }

    /**
     * Compile lock clause for pessimistic locking.
     *
     * Database-specific implementations:
     * - MySQL/MariaDB: FOR UPDATE / LOCK IN SHARE MODE
     * - PostgreSQL: FOR UPDATE / FOR SHARE
     * - SQLite: Not supported (returns empty string)
     *
     * @return string
     */
    private function compileLock(): string
    {
        $lock = $this->getLock();

        if ($lock === null) {
            return '';
        }

        $driver = $this->connection->getDriverName();

        return match ($lock) {
            'update' => match ($driver) {
                'mysql', 'pgsql' => ' FOR UPDATE',
                default => '' // SQLite doesn't support locks
            },
            'shared' => match ($driver) {
                'mysql' => ' LOCK IN SHARE MODE',
                'pgsql' => ' FOR SHARE',
                default => '' // SQLite doesn't support locks
            },
            default => ''
        };
    }

    /**
     * Spawn a fresh QueryBuilder sharing the same connection.
     */
    public function newQuery(): self
    {
        return new self($this->connection);
    }

    /**
     * Paginate the query results.
     *
     * This method follows SOLID principles:
     * - Single Responsibility: Only handles database-level pagination
     * - Open/Closed: Returns Paginator that can be extended
     * - Dependency Inversion: Returns abstraction (Paginator), not concrete collection
     *
     * Performance:
     * - Executes 2 queries: COUNT(*) for total, SELECT with LIMIT/OFFSET for data
     * - Much more efficient than loading all data into memory
     * - Scales to millions of records
     *
     * @param int $perPage Number of items per page (default: 15)
     * @param int $page Current page number (1-indexed, default: 1)
     * @param string|null $path Base URL path for pagination links
     * @return \Toporia\Framework\Support\Pagination\Paginator
     *
     * @example
     * // Basic pagination
     * $paginator = DB::table('users')->paginate(15);
     *
     * // With filters
     * $paginator = DB::table('products')
     *     ->where('is_active', true)
     *     ->orderBy('created_at', 'DESC')
     *     ->paginate(20, page: 2);
     *
     * // Access data
     * $items = $paginator->items();
     * $total = $paginator->total();
     * $hasMore = $paginator->hasMorePages();
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

        // Step 2: Get paginated items
        $offset = ($page - 1) * $perPage;
        /** @var RowCollection<int, array<string,mixed>> $items */
        $items = $this->limit($perPage)->offset($offset)->get();

        // Step 3: Return Paginator value object
        return new \Toporia\Framework\Support\Pagination\Paginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
            path: $path
        );
    }

    // =========================================================================
    // GETTER METHODS FOR GRAMMAR ACCESS
    // =========================================================================
    // Note: getTable() and getColumns() already exist above (lines 227, 237)

    /**
     * Get WHERE clauses.
     *
     * @return array<array>
     */
    public function getWheres(): array
    {
        return $this->wheres;
    }

    /**
     * Get JOIN clauses.
     *
     * @return array<array>
     */
    public function getJoins(): array
    {
        return $this->joins;
    }

    /**
     * Get ORDER BY clauses.
     *
     * @return array<array>
     */
    public function getOrders(): array
    {
        return $this->orders;
    }

    /**
     * Get GROUP BY columns.
     *
     * @return array<string>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * Get HAVING clauses.
     *
     * @return array<array>
     */
    public function getHavings(): array
    {
        return $this->havings;
    }

    /**
     * Get LIMIT value (already exists as protected, adding public).
     *
     * @return int|null
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * Get OFFSET value (already exists as protected, adding public).
     *
     * @return int|null
     */
    public function getOffset(): ?int
    {
        return $this->offset;
    }

    /**
     * Check if DISTINCT is enabled.
     *
     * @return bool
     */
    public function isDistinct(): bool
    {
        return $this->distinct;
    }

    /**
     * Check if LIMIT is set.
     *
     * @return bool
     */
    public function hasLimit(): bool
    {
        return $this->limit !== null;
    }

    /**
     * Check if OFFSET is set.
     *
     * @return bool
     */
    public function hasOffset(): bool
    {
        return $this->offset !== null;
    }

    /**
     * Switch to a different database connection.
     *
     * Creates a new QueryBuilder instance with the specified connection
     * while preserving current query state (table, columns, wheres, etc.).
     *
     * Performance: Connection is cached per name (O(1) lookup after first call)
     * Grammar is automatically selected based on connection driver
     *
     * Usage:
     * ```php
     * $query = DB::connection('mysql')->table('users')->where('status', 'active');
     * $mongoQuery = $query->onConnection('mongodb')->table('messages')->where('user_id', 123);
     * ```
     *
     * SOLID Principles:
     * - Single Responsibility: Only changes connection, preserves query state
     * - Open/Closed: Extensible for new connection types
     * - Dependency Inversion: Depends on ConnectionInterface abstraction
     *
     * @param string $connectionName Connection name from config/database.php
     * @return self New QueryBuilder instance with specified connection
     * @throws \RuntimeException If connection not found
     */
    public function onConnection(string $connectionName): self
    {
        // Get DatabaseManager from container
        $manager = container(\Toporia\Framework\Database\DatabaseManager::class);
        $proxy = $manager->connection($connectionName);
        $newConnection = $proxy->getConnection();

        // Create new QueryBuilder with new connection
        $newQuery = new self($newConnection);

        // Copy current query state to new QueryBuilder
        $newQuery->table = $this->table;
        $newQuery->columns = $this->columns;
        $newQuery->wheres = $this->wheres;
        $newQuery->joins = $this->joins;
        $newQuery->orders = $this->orders;
        $newQuery->groups = $this->groups;
        $newQuery->havings = $this->havings;
        $newQuery->limit = $this->limit;
        $newQuery->offset = $this->offset;
        $newQuery->distinct = $this->distinct;
        $newQuery->bindings = $this->bindings;
        $newQuery->eagerLoad = $this->eagerLoad;
        $newQuery->unions = $this->unions;
        $newQuery->lock = $this->lock;

        // Invalidate cache (connection changed)
        $newQuery->invalidateCache();

        return $newQuery;
    }

    // =========================================================================
    // STATIC PERFORMANCE & DEBUGGING METHODS
    // =========================================================================

    /**
     * Static relationship caching configuration.
     *
     * @var array
     */
    private static array $relationshipCacheConfig = [
        'enabled' => false,
        'size' => 0,
        'max_size' => 1000,
        'cache' => []
    ];

    /**
     * Enable relationship query caching for performance optimization.
     *
     * This is a Toporia exclusive feature for caching relationship queries.
     *
     * @param int $maxSize Maximum cache size (default: 1000)
     * @return void
     */
    public static function enableRelationshipCaching(int $maxSize = 1000): void
    {
        self::$relationshipCacheConfig['enabled'] = true;
        self::$relationshipCacheConfig['max_size'] = $maxSize;
    }

    /**
     * Disable relationship query caching.
     *
     * @return void
     */
    public static function disableRelationshipCaching(): void
    {
        self::$relationshipCacheConfig['enabled'] = false;
        self::$relationshipCacheConfig['cache'] = [];
        self::$relationshipCacheConfig['size'] = 0;
    }

    /**
     * Get relationship cache statistics.
     *
     * @return array Cache statistics
     */
    public static function getRelationshipCacheStats(): array
    {
        return [
            'enabled' => self::$relationshipCacheConfig['enabled'],
            'size' => self::$relationshipCacheConfig['size'],
            'max_size' => self::$relationshipCacheConfig['max_size'],
            'hit_ratio' => self::calculateCacheHitRatio()
        ];
    }

    /**
     * Clear relationship cache.
     *
     * @return void
     */
    public static function clearRelationshipCache(): void
    {
        self::$relationshipCacheConfig['cache'] = [];
        self::$relationshipCacheConfig['size'] = 0;
    }

    /**
     * Calculate cache hit ratio for performance monitoring.
     *
     * @return float Hit ratio (0.0 to 1.0)
     */
    private static function calculateCacheHitRatio(): float
    {
        // This would be implemented with actual hit/miss counters
        // For now, return a placeholder
        return 0.0;
    }

    /**
     * Get cached relationship query result.
     *
     * @param string $key Cache key
     * @return mixed|null Cached result or null if not found
     */
    public static function getCachedRelationshipQuery(string $key): mixed
    {
        if (!self::$relationshipCacheConfig['enabled']) {
            return null;
        }

        return self::$relationshipCacheConfig['cache'][$key] ?? null;
    }

    /**
     * Cache relationship query result.
     *
     * @param string $key Cache key
     * @param mixed $result Query result
     * @return void
     */
    public static function cacheRelationshipQuery(string $key, mixed $result): void
    {
        if (!self::$relationshipCacheConfig['enabled']) {
            return;
        }

        // Check cache size limit
        if (self::$relationshipCacheConfig['size'] >= self::$relationshipCacheConfig['max_size']) {
            // Remove oldest entry (simple FIFO)
            $firstKey = array_key_first(self::$relationshipCacheConfig['cache']);
            if ($firstKey !== null) {
                unset(self::$relationshipCacheConfig['cache'][$firstKey]);
                self::$relationshipCacheConfig['size']--;
            }
        }

        self::$relationshipCacheConfig['cache'][$key] = $result;
        self::$relationshipCacheConfig['size']++;
    }
}
