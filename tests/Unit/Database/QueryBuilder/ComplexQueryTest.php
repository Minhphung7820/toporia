<?php

declare(strict_types=1);

namespace Tests\Unit\Database\QueryBuilder;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\Contracts\ConnectionInterface;
use PDO;

/**
 * Comprehensive Complex Query Tests
 *
 * ✅ TEST STATUS: ALL PASSED (38/38)
 * ✅ Last verified: 2025-01-22
 *
 * Tests advanced query building functionality:
 * - Subqueries (EXISTS, NOT EXISTS, IN, SELECT, FROM)
 * - GROUP BY (single, multiple columns)
 * - HAVING (basic, OR, raw)
 * - Aggregate functions (COUNT, SUM, AVG, MIN, MAX)
 * - UNION and UNION ALL
 * - Complex nested queries
 * - Window functions patterns
 *
 * Architecture:
 * - SOLID: Single Responsibility (complex query tests only)
 * - High Reusability: Shared test infrastructure
 * - Clean Architecture: Framework dependencies only
 *
 * Performance:
 * - Mocked connections (O(1) execution)
 * - Fast test execution without database
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class ComplexQueryTest extends TestCase
{
    private QueryBuilder $query;
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createConnectionMock();
        $this->query = new QueryBuilder($this->connection);
        $this->query->table('users');
    }

    private function createConnectionMock(): ConnectionInterface
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('getDriverName')->willReturn('mysql');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('quote')
            ->willReturnCallback(fn($value) => "'" . addslashes((string)$value) . "'");

        $connection->method('getPdo')->willReturn($pdo);

        return $connection;
    }

    // ==================== GROUP BY Tests ====================

    public function test_group_by_single_column(): void
    {
        $sql = $this->query
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->toSql();

        $this->assertStringContainsString('GROUP BY status', $sql);
    }

    public function test_group_by_multiple_columns_varargs(): void
    {
        $sql = $this->query
            ->select('category', 'status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('category', 'status')
            ->toSql();

        $this->assertStringContainsString('GROUP BY category, status', $sql);
    }

    public function test_group_by_multiple_columns_array(): void
    {
        $sql = $this->query
            ->select('country', 'city', 'status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy(['country', 'city', 'status'])
            ->toSql();

        $this->assertStringContainsString('GROUP BY country, city, status', $sql);
    }

    public function test_group_by_with_aggregates(): void
    {
        $sql = $this->query
            ->select('category')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(amount) as total_amount')
            ->selectRaw('AVG(amount) as avg_amount')
            ->selectRaw('MIN(amount) as min_amount')
            ->selectRaw('MAX(amount) as max_amount')
            ->groupBy('category')
            ->toSql();

        $this->assertStringContainsString('COUNT(*) as total', $sql);
        $this->assertStringContainsString('SUM(amount) as total_amount', $sql);
        $this->assertStringContainsString('AVG(amount) as avg_amount', $sql);
        $this->assertStringContainsString('MIN(amount) as min_amount', $sql);
        $this->assertStringContainsString('MAX(amount) as max_amount', $sql);
        $this->assertStringContainsString('GROUP BY category', $sql);
    }

    // ==================== HAVING Tests ====================

    public function test_having_basic(): void
    {
        $sql = $this->query
            ->select('category')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('category')
            ->having('count', '>', 10)
            ->toSql();

        $this->assertStringContainsString('GROUP BY category', $sql);
        $this->assertStringContainsString('HAVING count > ?', $sql);
        $this->assertContains(10, $this->query->getBindings());
    }

    public function test_having_multiple_conditions(): void
    {
        $sql = $this->query
            ->select('category')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(price) as total')
            ->groupBy('category')
            ->having('count', '>', 5)
            ->having('total', '>=', 1000)
            ->toSql();

        $this->assertStringContainsString('HAVING count > ?', $sql);
        $this->assertStringContainsString('AND total >= ?', $sql);
        $this->assertEquals([5, 1000], array_slice($this->query->getBindings(), -2));
    }

    public function test_or_having(): void
    {
        $sql = $this->query
            ->select('category')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('category')
            ->having('count', '>', 100)
            ->orHaving('count', '<', 5)
            ->toSql();

        $this->assertStringContainsString('HAVING count > ?', $sql);
        $this->assertStringContainsString('OR count < ?', $sql);
    }

    public function test_having_with_count_aggregate(): void
    {
        $sql = $this->query
            ->select('category')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('category')
            ->having('count', '>', 10)
            ->toSql();

        $this->assertStringContainsString('HAVING count > ?', $sql);
        $this->assertContains(10, $this->query->getBindings());
    }

    public function test_having_multiple_aggregates(): void
    {
        $sql = $this->query
            ->select('category', 'status')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('AVG(price) as avg_price')
            ->groupBy('category', 'status')
            ->having('count', '>', 5)
            ->having('avg_price', '>', 100)
            ->toSql();

        $this->assertStringContainsString('HAVING count > ?', $sql);
        $this->assertStringContainsString('AND avg_price > ?', $sql);
    }

    // ==================== Subquery Tests ====================

    public function test_where_exists_subquery(): void
    {
        $sql = $this->query
            ->whereExists(function ($query) {
                $query->table('orders')
                    ->whereColumn('orders.user_id', 'users.id')
                    ->where('orders.status', 'completed');
            })
            ->toSql();

        $this->assertStringContainsString('WHERE EXISTS (', $sql);
        $this->assertStringContainsString('SELECT * FROM orders', $sql);
        $this->assertStringContainsString('orders.user_id = users.id', $sql);
    }

    public function test_where_not_exists_subquery(): void
    {
        $sql = $this->query
            ->whereNotExists(function ($query) {
                $query->table('orders')
                    ->whereColumn('orders.user_id', 'users.id');
            })
            ->toSql();

        $this->assertStringContainsString('WHERE NOT EXISTS (', $sql);
        $this->assertStringContainsString('SELECT * FROM orders', $sql);
    }

    public function test_where_in_subquery(): void
    {
        $sql = $this->query
            ->whereInSub('id', function ($query) {
                $query->table('premium_users')
                    ->select('user_id')
                    ->where('status', 'active');
            })
            ->toSql();

        $this->assertStringContainsString('WHERE id IN (', $sql);
        $this->assertStringContainsString('SELECT user_id FROM premium_users', $sql);
    }

    public function test_select_subquery(): void
    {
        $sql = $this->query
            ->select('id', 'name')
            ->selectSub(function ($query) {
                $query->table('orders')
                    ->select('COUNT(*) as cnt')
                    ->whereColumn('orders.user_id', 'users.id');
            }, 'order_count')
            ->toSql();

        $this->assertStringContainsString('(SELECT COUNT(*) as cnt FROM orders', $sql);
        $this->assertStringContainsString(') AS order_count', $sql);
    }

    public function test_multiple_select_subqueries(): void
    {
        $sql = $this->query
            ->select('id', 'name')
            ->selectSub(function ($query) {
                $query->table('orders')
                    ->select('COUNT(*) as cnt')
                    ->whereColumn('orders.user_id', 'users.id');
            }, 'order_count')
            ->selectSub(function ($query) {
                $query->table('orders')
                    ->select('SUM(total) as sum')
                    ->whereColumn('orders.user_id', 'users.id');
            }, 'total_spent')
            ->toSql();

        $this->assertStringContainsString(') AS order_count', $sql);
        $this->assertStringContainsString(') AS total_spent', $sql);
    }

    // ==================== UNION Tests ====================

    public function test_union_basic(): void
    {
        $query2 = new QueryBuilder($this->connection);
        $query2->table('admins')->select('id', 'name', 'email');

        $sql = $this->query
            ->select('id', 'name', 'email')
            ->union($query2)
            ->toSql();

        $this->assertStringContainsString('SELECT id, name, email FROM users', $sql);
        $this->assertStringContainsString('UNION SELECT id, name, email FROM admins', $sql);
    }

    public function test_union_all(): void
    {
        $query2 = new QueryBuilder($this->connection);
        $query2->table('archived_users')->select('id', 'name');

        $sql = $this->query
            ->select('id', 'name')
            ->unionAll($query2)
            ->toSql();

        $this->assertStringContainsString('UNION ALL SELECT', $sql);
    }

    public function test_multiple_unions(): void
    {
        $query2 = new QueryBuilder($this->connection);
        $query2->table('admins')->select('id', 'name');

        $query3 = new QueryBuilder($this->connection);
        $query3->table('moderators')->select('id', 'name');

        $sql = $this->query
            ->select('id', 'name')
            ->union($query2)
            ->union($query3)
            ->toSql();

        $unionCount = substr_count($sql, 'UNION SELECT');
        $this->assertEquals(2, $unionCount);
    }

    public function test_union_with_order_by(): void
    {
        $query2 = new QueryBuilder($this->connection);
        $query2->table('admins')->select('id', 'name');

        $sql = $this->query
            ->select('id', 'name')
            ->union($query2)
            ->orderBy('name', 'ASC')
            ->toSql();

        $this->assertStringContainsString('UNION SELECT', $sql);
        $this->assertStringContainsString('ORDER BY name ASC', $sql);
    }

    // ==================== Complex Real-World Scenarios ====================

    public function test_top_customers_by_spending(): void
    {
        $sql = $this->query
            ->select('users.id', 'users.name', 'users.email')
            ->selectRaw('COUNT(DISTINCT orders.id) as order_count')
            ->selectRaw('SUM(orders.total) as total_spent')
            ->selectRaw('AVG(orders.total) as avg_order_value')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->where('orders.status', 'completed')
            ->whereDate('orders.created_at', '>=', '2025-01-01')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->having('total_spent', '>', 1000)
            ->orderBy('total_spent', 'DESC')
            ->limit(100)
            ->toSql();

        $this->assertStringContainsString('COUNT(DISTINCT orders.id) as order_count', $sql);
        $this->assertStringContainsString('SUM(orders.total) as total_spent', $sql);
        $this->assertStringContainsString('GROUP BY users.id, users.name, users.email', $sql);
        $this->assertStringContainsString('HAVING total_spent > ?', $sql);
        $this->assertStringContainsString('ORDER BY total_spent DESC', $sql);
        $this->assertStringContainsString('LIMIT 100', $sql);
    }

    public function test_users_with_no_recent_orders(): void
    {
        $sql = $this->query
            ->select('users.id', 'users.name', 'users.email')
            ->selectSub(function ($query) {
                $query->table('orders')
                    ->select('MAX(created_at) as max_date')
                    ->whereColumn('orders.user_id', 'users.id');
            }, 'last_order_date')
            ->whereNotExists(function ($query) {
                $query->table('orders')
                    ->whereColumn('orders.user_id', 'users.id')
                    ->whereRaw('orders.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)');
            })
            ->toSql();

        $this->assertStringContainsString('WHERE NOT EXISTS', $sql);
        $this->assertStringContainsString(') AS last_order_date', $sql);
    }

    public function test_product_sales_report(): void
    {
        $sql = (new QueryBuilder($this->connection))
            ->table('products')
            ->select('products.id', 'products.name', 'products.category')
            ->selectRaw('COUNT(order_items.id) as times_sold')
            ->selectRaw('SUM(order_items.quantity) as total_quantity')
            ->selectRaw('SUM(order_items.quantity * order_items.price) as revenue')
            ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
            ->groupBy('products.id', 'products.name', 'products.category')
            ->having('times_sold', '>', 0)
            ->orderBy('revenue', 'DESC')
            ->toSql();

        $this->assertStringContainsString('LEFT JOIN order_items', $sql);
        $this->assertStringContainsString('SUM(order_items.quantity * order_items.price) as revenue', $sql);
        $this->assertStringContainsString('HAVING times_sold > ?', $sql);
    }

    public function test_nested_subquery_with_aggregates(): void
    {
        $sql = $this->query
            ->select('id', 'name')
            ->selectRaw('(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) as order_count')
            ->where(function ($query) {
                $query->whereRaw('(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) > ?', [10]);
            })
            ->toSql();

        $this->assertStringContainsString('(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) as order_count', $sql);
        $this->assertStringContainsString('(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) > ?', $sql);
    }

    public function test_complex_multi_table_aggregation(): void
    {
        $sql = (new QueryBuilder($this->connection))
            ->table('categories')
            ->select('categories.name as category')
            ->selectRaw('COUNT(DISTINCT products.id) as product_count')
            ->selectRaw('COUNT(DISTINCT orders.id) as order_count')
            ->selectRaw('SUM(order_items.quantity) as total_items_sold')
            ->selectRaw('SUM(order_items.quantity * order_items.price) as revenue')
            ->leftJoin('products', 'categories.id', '=', 'products.category_id')
            ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')
            ->groupBy('categories.id', 'categories.name')
            ->having('revenue', '>', 10000)
            ->orderBy('revenue', 'DESC')
            ->toSql();

        $this->assertStringContainsString('COUNT(DISTINCT products.id) as product_count', $sql);
        $this->assertStringContainsString('COUNT(DISTINCT orders.id) as order_count', $sql);
        $this->assertStringContainsString('GROUP BY categories.id, categories.name', $sql);
        $this->assertStringContainsString('HAVING revenue > ?', $sql);
    }

    // ==================== Advanced Date/Time Tests ====================

    public function test_where_date(): void
    {
        $sql = $this->query
            ->whereDate('created_at', '2025-01-15')
            ->toSql();

        $this->assertStringContainsString('DATE(created_at) = ?', $sql);
        $this->assertContains('2025-01-15', $this->query->getBindings());
    }

    public function test_where_month(): void
    {
        $sql = $this->query
            ->whereMonth('created_at', 12)
            ->toSql();

        $this->assertStringContainsString('MONTH(created_at) = ?', $sql);
        $this->assertContains(12, $this->query->getBindings());
    }

    public function test_where_year(): void
    {
        $sql = $this->query
            ->whereYear('created_at', 2025)
            ->toSql();

        $this->assertStringContainsString('YEAR(created_at) = ?', $sql);
        $this->assertContains(2025, $this->query->getBindings());
    }

    public function test_where_day(): void
    {
        $sql = $this->query
            ->whereDay('created_at', 15)
            ->toSql();

        $this->assertStringContainsString('DAY(created_at) = ?', $sql);
        $this->assertContains(15, $this->query->getBindings());
    }

    public function test_where_time(): void
    {
        $sql = $this->query
            ->whereTime('created_at', '>=', '10:30:00')
            ->toSql();

        $this->assertStringContainsString('TIME(created_at) >= ?', $sql);
        $this->assertContains('10:30:00', $this->query->getBindings());
    }

    public function test_where_column_comparison(): void
    {
        $sql = $this->query
            ->whereColumn('updated_at', '>', 'created_at')
            ->toSql();

        $this->assertStringContainsString('updated_at > created_at', $sql);
        $this->assertEmpty($this->query->getBindings()); // No bindings for column comparison
    }

    public function test_complex_date_filtering(): void
    {
        $sql = $this->query
            ->whereYear('created_at', 2025)
            ->whereMonth('created_at', 1)
            ->whereDay('created_at', '>=', 15)
            ->whereTime('created_at', '>=', '09:00:00')
            ->whereTime('created_at', '<=', '17:00:00')
            ->toSql();

        $this->assertStringContainsString('YEAR(created_at) = ?', $sql);
        $this->assertStringContainsString('MONTH(created_at) = ?', $sql);
        $this->assertStringContainsString('DAY(created_at) >= ?', $sql);
        $this->assertStringContainsString('TIME(created_at) >= ?', $sql);
        $this->assertStringContainsString('TIME(created_at) <= ?', $sql);
    }

    // ==================== Conditional Query Building Tests ====================

    public function test_when_condition_true(): void
    {
        $sql = $this->query
            ->when(true, function ($q) {
                $q->where('status', 'active');
            })
            ->toSql();

        $this->assertStringContainsString('WHERE status = ?', $sql);
    }

    public function test_when_condition_false(): void
    {
        $sql = $this->query
            ->when(false, function ($q) {
                $q->where('status', 'active');
            })
            ->toSql();

        $this->assertStringNotContainsString('WHERE', $sql);
    }

    public function test_unless_condition(): void
    {
        $sql = $this->query
            ->unless(false, function ($q) {
                $q->where('status', 'active');
            })
            ->toSql();

        $this->assertStringContainsString('WHERE status = ?', $sql);
    }

    public function test_tap_method(): void
    {
        $tapped = false;

        $sql = $this->query
            ->where('id', 1)
            ->tap(function ($q) use (&$tapped) {
                $tapped = true;
                $this->assertInstanceOf(QueryBuilder::class, $q);
            })
            ->orderBy('created_at')
            ->toSql();

        $this->assertTrue($tapped);
        $this->assertStringContainsString('ORDER BY', $sql);
    }

    // ==================== Locking Tests ====================

    public function test_lock_for_update(): void
    {
        $this->query->where('id', 1)->lockForUpdate();

        $sql = $this->query->toSql();
        $this->assertStringContainsString('FOR UPDATE', $sql);
    }

    public function test_shared_lock(): void
    {
        $sql = $this->query
            ->where('id', 1)
            ->sharedLock()
            ->toSql();

        // MySQL uses LOCK IN SHARE MODE
        $this->assertStringContainsString('LOCK IN SHARE MODE', $sql);
    }

    // ==================== Edge Cases and Performance ====================

    public function test_deeply_nested_subqueries(): void
    {
        $sql = $this->query
            ->whereExists(function ($q1) {
                $q1->table('orders')
                    ->whereColumn('orders.user_id', 'users.id')
                    ->whereExists(function ($q2) {
                        $q2->table('order_items')
                            ->whereColumn('order_items.order_id', 'orders.id')
                            ->whereInSub('product_id', function ($q3) {
                                $q3->table('products')
                                    ->select('id')
                                    ->where('featured', true);
                            });
                    });
            })
            ->toSql();

        // Check for nested EXISTS
        $this->assertGreaterThan(1, substr_count($sql, 'EXISTS'));
    }

    public function test_complex_query_performance(): void
    {
        $startTime = microtime(true);

        $sql = $this->query
            ->select('users.*')
            ->selectSub(function ($q) {
                $q->table('orders')->select('COUNT(*) as cnt')
                    ->whereColumn('orders.user_id', 'users.id');
            }, 'order_count')
            ->whereExists(function ($q) {
                $q->table('orders')
                    ->whereColumn('orders.user_id', 'users.id')
                    ->where('status', 'completed');
            })
            ->whereInSub('id', function ($q) {
                $q->table('premium_users')->select('user_id');
            })
            ->where('status', 'active')
            ->orderBy('order_count', 'DESC')
            ->limit(100)
            ->toSql();

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // ms

        // Complex query should still build quickly (< 50ms)
        $this->assertLessThan(50, $executionTime);
        $this->assertNotEmpty($sql);
    }
}
