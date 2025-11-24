<?php

declare(strict_types=1);

namespace Tests\Unit\Database\QueryBuilder;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\Contracts\ConnectionInterface;

/**
 * JoinSub Unit Tests
 *
 * Tests QueryBuilder's joinSub, leftJoinSub, rightJoinSub methods
 * for joining with subqueries (derived tables)
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class JoinSubTest extends TestCase
{
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createConnectionMock();
    }

    private function createConnectionMock(): ConnectionInterface
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('getDriverName')->willReturn('mysql');

        $grammar = new \Toporia\Framework\Database\Grammar\MySQLGrammar();
        $connection->method('getGrammar')->willReturn($grammar);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('quote')
            ->willReturnCallback(fn($value) => "'" . addslashes((string)$value) . "'");

        $connection->method('getPdo')->willReturn($pdo);

        return $connection;
    }

    // ==================== joinSub Basic Tests ====================

    public function test_join_sub_with_query_builder(): void
    {
        $subQuery = (new QueryBuilder($this->connection))
            ->select('user_id', 'COUNT(*) as order_count')
            ->table('orders')
            ->groupBy('user_id');

        $query = (new QueryBuilder($this->connection))
            ->select('users.name', 'order_stats.order_count')
            ->table('users')
            ->joinSub($subQuery, 'order_stats', 'users.id', '=', 'order_stats.user_id');

        $sql = $query->toSql();

        $this->assertStringContainsString('INNER JOIN (SELECT', $sql);
        $this->assertStringContainsString('AS order_stats', $sql);
        $this->assertStringContainsString('ON `users`.`id` = `order_stats`.`user_id`', $sql);
    }

    public function test_join_sub_with_raw_sql(): void
    {
        $rawSql = 'SELECT user_id, COUNT(*) as total FROM orders GROUP BY user_id';

        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->joinSub($rawSql, 'order_stats', 'users.id', '=', 'order_stats.user_id');

        $sql = $query->toSql();

        $this->assertStringContainsString('INNER JOIN (' . $rawSql . ') AS order_stats', $sql);
    }

    public function test_join_sub_transfers_bindings_from_subquery(): void
    {
        $subQuery = (new QueryBuilder($this->connection))
            ->select('user_id', 'SUM(total) as total_spent')
            ->table('orders')
            ->where('status', '=', 'completed')
            ->where('total', '>', 100)
            ->groupBy('user_id');

        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->joinSub($subQuery, 'spending', 'users.id', '=', 'spending.user_id')
            ->where('users.status', '=', 'active');

        $bindings = $query->getBindings();

        // Should have 3 bindings: 2 from subquery + 1 from main query
        $this->assertCount(3, $bindings);
        $this->assertContains('completed', $bindings);
        $this->assertContains(100, $bindings);
        $this->assertContains('active', $bindings);
    }

    // ==================== joinSub with Closure ====================

    public function test_join_sub_with_closure(): void
    {
        $subQuery = (new QueryBuilder($this->connection))
            ->select('user_id', 'MAX(created_at) as last_order')
            ->table('orders')
            ->groupBy('user_id');

        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->joinSub($subQuery, 'recent_orders', function($join) {
                $join->on('users.id', '=', 'recent_orders.user_id')
                     ->where('recent_orders.last_order', '>', '2024-01-01');
            });

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('INNER JOIN (SELECT', $sql);
        $this->assertStringContainsString('ON `users`.`id` = `recent_orders`.`user_id`', $sql);
        $this->assertStringContainsString('AND `recent_orders`.`last_order` > ?', $sql);
        $this->assertContains('2024-01-01', $bindings);
    }

    // ==================== leftJoinSub Tests ====================

    public function test_left_join_sub(): void
    {
        $subQuery = (new QueryBuilder($this->connection))
            ->select('user_id', 'COUNT(*) as order_count')
            ->table('orders')
            ->groupBy('user_id');

        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->leftJoinSub($subQuery, 'order_stats', 'users.id', '=', 'order_stats.user_id');

        $sql = $query->toSql();

        $this->assertStringContainsString('LEFT JOIN (SELECT', $sql);
    }

    public function test_left_join_sub_with_closure(): void
    {
        $subQuery = (new QueryBuilder($this->connection))
            ->select('product_id', 'AVG(rating) as avg_rating')
            ->table('reviews')
            ->groupBy('product_id');

        $query = (new QueryBuilder($this->connection))
            ->table('products')
            ->leftJoinSub($subQuery, 'ratings', function($join) {
                $join->on('products.id', '=', 'ratings.product_id')
                     ->where('ratings.avg_rating', '>=', 4.0);
            });

        $sql = $query->toSql();

        $this->assertStringContainsString('LEFT JOIN (SELECT', $sql);
        $this->assertStringContainsString('AND `ratings`.`avg_rating` >= ?', $sql);
    }

    // ==================== rightJoinSub Tests ====================

    public function test_right_join_sub(): void
    {
        $subQuery = (new QueryBuilder($this->connection))
            ->select('category_id', 'COUNT(*) as product_count')
            ->table('products')
            ->groupBy('category_id');

        $query = (new QueryBuilder($this->connection))
            ->table('categories')
            ->rightJoinSub($subQuery, 'product_stats', 'categories.id', '=', 'product_stats.category_id');

        $sql = $query->toSql();

        $this->assertStringContainsString('RIGHT JOIN (SELECT', $sql);
    }

    // ==================== Complex Real-World Scenarios ====================

    public function test_multiple_subquery_joins(): void
    {
        $orderStats = (new QueryBuilder($this->connection))
            ->select('user_id', 'COUNT(*) as order_count', 'SUM(total) as total_spent')
            ->table('orders')
            ->where('status', '=', 'completed')
            ->groupBy('user_id');

        $reviewStats = (new QueryBuilder($this->connection))
            ->select('user_id', 'AVG(rating) as avg_rating')
            ->table('reviews')
            ->groupBy('user_id');

        $query = (new QueryBuilder($this->connection))
            ->select('users.name', 'order_stats.order_count', 'review_stats.avg_rating')
            ->table('users')
            ->joinSub($orderStats, 'order_stats', 'users.id', '=', 'order_stats.user_id')
            ->leftJoinSub($reviewStats, 'review_stats', 'users.id', '=', 'review_stats.user_id');

        $sql = $query->toSql();

        $this->assertStringContainsString('INNER JOIN (SELECT', $sql);
        $this->assertStringContainsString('LEFT JOIN (SELECT', $sql);
        $this->assertStringContainsString('AS order_stats', $sql);
        $this->assertStringContainsString('AS review_stats', $sql);
    }

    public function test_subquery_join_with_complex_conditions(): void
    {
        $highValueOrders = (new QueryBuilder($this->connection))
            ->select('user_id', 'MAX(created_at) as last_high_value_order')
            ->table('orders')
            ->where('total', '>=', 1000)
            ->where('status', '=', 'completed')
            ->groupBy('user_id');

        $query = (new QueryBuilder($this->connection))
            ->select('users.id', 'users.name', 'high_value.last_high_value_order')
            ->table('users')
            ->joinSub($highValueOrders, 'high_value', function($join) {
                $join->on('users.id', '=', 'high_value.user_id')
                     ->whereNotNull('high_value.last_high_value_order')
                     ->where('high_value.last_high_value_order', '>', '2024-01-01');
            })
            ->where('users.status', '=', 'premium');

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        // Bindings: 1000, 'completed' (from subquery) + '2024-01-01', 'premium' (from main query)
        $this->assertCount(4, $bindings);
        $this->assertContains(1000, $bindings);
        $this->assertContains('completed', $bindings);
        $this->assertContains('2024-01-01', $bindings);
        $this->assertContains('premium', $bindings);
    }

    public function test_find_customers_with_high_lifetime_value(): void
    {
        // Subquery: Calculate lifetime value per customer
        $lifetimeValue = (new QueryBuilder($this->connection))
            ->select('user_id', 'SUM(total) as lifetime_value', 'COUNT(*) as order_count')
            ->table('orders')
            ->where('status', '=', 'completed')
            ->groupBy('user_id');

        // Main query: Get customers with LTV > $5000
        $query = (new QueryBuilder($this->connection))
            ->select('users.id', 'users.name', 'users.email', 'ltv.lifetime_value', 'ltv.order_count')
            ->table('users')
            ->joinSub($lifetimeValue, 'ltv', function($join) {
                $join->on('users.id', '=', 'ltv.user_id')
                     ->where('ltv.lifetime_value', '>', 5000);
            })
            ->orderBy('ltv.lifetime_value', 'DESC')
            ->limit(100);

        $sql = $query->toSql();

        $this->assertStringContainsString('INNER JOIN (SELECT', $sql);
        $this->assertStringContainsString('AS ltv', $sql);
        $this->assertStringContainsString('AND `ltv`.`lifetime_value` > ?', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT 100', $sql);
    }

    // ==================== Edge Cases ====================

    public function test_join_sub_with_null_operator(): void
    {
        $subQuery = (new QueryBuilder($this->connection))
            ->select('user_id')
            ->table('orders')
            ->groupBy('user_id');

        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->joinSub($subQuery, 'has_orders', 'users.id', null, 'has_orders.user_id');

        $sql = $query->toSql();

        // Should default to '=' operator
        $this->assertStringContainsString('ON `users`.`id` = `has_orders`.`user_id`', $sql);
    }

    public function test_join_sub_invalidates_cache(): void
    {
        $subQuery = (new QueryBuilder($this->connection))
            ->select('user_id')
            ->table('orders');

        $query = (new QueryBuilder($this->connection))
            ->table('users');

        $sql1 = $query->toSql();

        $query->joinSub($subQuery, 'has_orders', 'users.id', '=', 'has_orders.user_id');

        $sql2 = $query->toSql();

        $this->assertNotEquals($sql1, $sql2);
    }

    // ==================== Performance Tests ====================

    public function test_subquery_compiled_only_once(): void
    {
        $subQuery = (new QueryBuilder($this->connection))
            ->select('user_id', 'COUNT(*) as cnt')
            ->table('orders')
            ->groupBy('user_id');

        // Compile subquery first
        $subSql1 = $subQuery->toSql();

        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->joinSub($subQuery, 'stats', 'users.id', '=', 'stats.user_id');

        // Compile main query (subquery should be reused)
        $mainSql = $query->toSql();

        // Compile subquery again - should be same
        $subSql2 = $subQuery->toSql();

        $this->assertSame($subSql1, $subSql2);
        $this->assertStringContainsString($subSql1, $mainSql);
    }
}
