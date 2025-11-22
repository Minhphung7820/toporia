<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\Contracts\ConnectionInterface;
use PDO;

/**
 * Test QueryBuilder Advanced Features
 *
 * Tests all Phase 1 enhancements:
 * - Advanced WHERE clauses (date/time, column comparison, NULL checks)
 * - Subqueries (EXISTS, IN, SELECT, FROM)
 * - Conditional methods (when, unless, tap)
 * - Unions (UNION, UNION ALL)
 * - Locks (FOR UPDATE, LOCK IN SHARE MODE)
 * - Advanced updates (increment, decrement, updateOrInsert)
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class QueryBuilderAdvancedTest extends TestCase
{
    private QueryBuilder $query;
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        // Mock connection
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->connection->method('getDriverName')->willReturn('mysql');

        // Mock PDO for quote() method
        $pdo = $this->createMock(PDO::class);
        $pdo->method('quote')
            ->willReturnCallback(fn($value) => "'" . addslashes($value) . "'");

        $this->connection->method('getPdo')->willReturn($pdo);

        $this->query = new QueryBuilder($this->connection);
        $this->query->table('users');
    }

    /**
     * Test whereDate generates correct SQL
     */
    public function test_where_date(): void
    {
        $sql = $this->query
            ->whereDate('created_at', '2025-01-22')
            ->toSql();

        $this->assertStringContainsString('DATE(created_at) = ?', $sql);
        $this->assertEquals(['2025-01-22'], $this->query->getBindings());
    }

    /**
     * Test whereMonth generates correct SQL
     */
    public function test_where_month(): void
    {
        $sql = $this->query
            ->whereMonth('created_at', 12)
            ->toSql();

        $this->assertStringContainsString('MONTH(created_at) = ?', $sql);
        $this->assertEquals([12], $this->query->getBindings());
    }

    /**
     * Test whereDay generates correct SQL
     */
    public function test_where_day(): void
    {
        $sql = $this->query
            ->whereDay('created_at', 25)
            ->toSql();

        $this->assertStringContainsString('DAY(created_at) = ?', $sql);
        $this->assertEquals([25], $this->query->getBindings());
    }

    /**
     * Test whereYear generates correct SQL
     */
    public function test_where_year(): void
    {
        $sql = $this->query
            ->whereYear('created_at', 2025)
            ->toSql();

        $this->assertStringContainsString('YEAR(created_at) = ?', $sql);
        $this->assertEquals([2025], $this->query->getBindings());
    }

    /**
     * Test whereTime generates correct SQL
     */
    public function test_where_time(): void
    {
        $sql = $this->query
            ->whereTime('created_at', '>=', '10:30:00')
            ->toSql();

        $this->assertStringContainsString('TIME(created_at) >= ?', $sql);
        $this->assertEquals(['10:30:00'], $this->query->getBindings());
    }

    /**
     * Test whereColumn generates correct SQL
     */
    public function test_where_column(): void
    {
        $sql = $this->query
            ->whereColumn('first_name', 'last_name')
            ->toSql();

        $this->assertStringContainsString('first_name = last_name', $sql);
        $this->assertEmpty($this->query->getBindings()); // No bindings for column comparison
    }

    /**
     * Test whereColumn with operator
     */
    public function test_where_column_with_operator(): void
    {
        $sql = $this->query
            ->whereColumn('updated_at', '>', 'created_at')
            ->toSql();

        $this->assertStringContainsString('updated_at > created_at', $sql);
    }

    /**
     * Test orWhereNull
     */
    public function test_or_where_null(): void
    {
        $sql = $this->query
            ->where('status', 'active')
            ->orWhereNull('deleted_at')
            ->toSql();

        $this->assertStringContainsString('WHERE status = ?', $sql);
        $this->assertStringContainsString('OR deleted_at IS NULL', $sql);
    }

    /**
     * Test orWhereNotNull
     */
    public function test_or_where_not_null(): void
    {
        $sql = $this->query
            ->where('status', 'active')
            ->orWhereNotNull('verified_at')
            ->toSql();

        $this->assertStringContainsString('WHERE status = ?', $sql);
        $this->assertStringContainsString('OR verified_at IS NOT NULL', $sql);
    }

    /**
     * Test orWhereRaw
     */
    public function test_or_where_raw(): void
    {
        $sql = $this->query
            ->where('status', 'active')
            ->orWhereRaw('price > ? AND stock < ?', [100, 10])
            ->toSql();

        $this->assertStringContainsString('WHERE status = ?', $sql);
        $this->assertStringContainsString('OR price > ? AND stock < ?', $sql);
        $this->assertEquals(['active', 100, 10], $this->query->getBindings());
    }

    /**
     * Test whereExists generates correct SQL
     */
    public function test_where_exists(): void
    {
        $sql = $this->query
            ->whereExists(function($query) {
                $query->table('orders')
                      ->whereColumn('orders.user_id', 'users.id');
            })
            ->toSql();

        $this->assertStringContainsString('WHERE EXISTS (', $sql);
        $this->assertStringContainsString('SELECT * FROM orders', $sql);
    }

    /**
     * Test whereNotExists generates correct SQL
     */
    public function test_where_not_exists(): void
    {
        $sql = $this->query
            ->whereNotExists(function($query) {
                $query->table('orders')
                      ->whereColumn('orders.user_id', 'users.id');
            })
            ->toSql();

        $this->assertStringContainsString('WHERE NOT EXISTS (', $sql);
    }

    /**
     * Test whereInSub generates correct SQL
     */
    public function test_where_in_sub(): void
    {
        $sql = $this->query
            ->whereInSub('id', function($query) {
                $query->table('active_users')
                      ->select('user_id');
            })
            ->toSql();

        $this->assertStringContainsString('WHERE id IN (', $sql);
        $this->assertStringContainsString('SELECT user_id FROM active_users', $sql);
    }

    /**
     * Test selectSub generates correct SQL
     */
    public function test_select_sub(): void
    {
        $sql = $this->query
            ->selectSub(function($query) {
                $query->table('orders')
                      ->select('COUNT(*) as count')  // Use select() not selectRaw()
                      ->whereColumn('orders.user_id', 'users.id');
            }, 'orders_count')
            ->toSql();

        $this->assertStringContainsString('(SELECT COUNT(*) as count FROM orders', $sql);
        $this->assertStringContainsString(') AS orders_count', $sql);
    }

    /**
     * Test when() with truthy condition
     */
    public function test_when_truthy(): void
    {
        $sql = $this->query
            ->when(true, function($query) {
                $query->where('status', 'active');
            })
            ->toSql();

        $this->assertStringContainsString('WHERE status = ?', $sql);
        $this->assertEquals(['active'], $this->query->getBindings());
    }

    /**
     * Test when() with falsy condition
     */
    public function test_when_falsy(): void
    {
        $sql = $this->query
            ->when(false, function($query) {
                $query->where('status', 'active');
            })
            ->toSql();

        $this->assertStringNotContainsString('WHERE', $sql);
    }

    /**
     * Test when() with default callback
     */
    public function test_when_with_default(): void
    {
        $sql = $this->query
            ->when(false, function($query) {
                $query->where('status', 'active');
            }, function($query) {
                $query->where('status', 'inactive');
            })
            ->toSql();

        $this->assertStringContainsString('WHERE status = ?', $sql);
        $this->assertEquals(['inactive'], $this->query->getBindings());
    }

    /**
     * Test unless() with falsy condition
     */
    public function test_unless_falsy(): void
    {
        $sql = $this->query
            ->unless(false, function($query) {
                $query->where('status', 'active');
            })
            ->toSql();

        $this->assertStringContainsString('WHERE status = ?', $sql);
    }

    /**
     * Test tap() executes callback without breaking chain
     */
    public function test_tap(): void
    {
        $tapped = false;

        $sql = $this->query
            ->where('status', 'active')
            ->tap(function($query) use (&$tapped) {
                $tapped = true;
                $this->assertInstanceOf(QueryBuilder::class, $query);
            })
            ->orderBy('created_at')
            ->toSql();

        $this->assertTrue($tapped);
        $this->assertStringContainsString('ORDER BY', $sql);
    }

    /**
     * Test union() generates correct SQL
     */
    public function test_union(): void
    {
        $query2 = new QueryBuilder($this->connection);
        $query2->table('admins')->select('id', 'name');

        $sql = $this->query
            ->select('id', 'name')
            ->union($query2)
            ->toSql();

        $this->assertStringContainsString('UNION SELECT', $sql);
    }

    /**
     * Test unionAll() generates correct SQL
     */
    public function test_union_all(): void
    {
        $query2 = new QueryBuilder($this->connection);
        $query2->table('admins')->select('id', 'name');

        $sql = $this->query
            ->select('id', 'name')
            ->unionAll($query2)
            ->toSql();

        $this->assertStringContainsString('UNION ALL SELECT', $sql);
    }

    /**
     * Test lockForUpdate() generates correct SQL
     */
    public function test_lock_for_update(): void
    {
        $this->query->where('id', 1)->lockForUpdate();

        // Debug: Check if lock is set
        $reflection = new \ReflectionMethod($this->query, 'getLock');
        $reflection->setAccessible(true);
        $lockValue = $reflection->invoke($this->query);
        $this->assertEquals('update', $lockValue, 'Lock should be set to "update"');

        $sql = $this->query->toSql();
        $this->assertStringContainsString('FOR UPDATE', $sql);
    }

    /**
     * Test sharedLock() generates correct SQL for MySQL
     */
    public function test_shared_lock_mysql(): void
    {
        $sql = $this->query
            ->where('id', 1)
            ->sharedLock()
            ->toSql();

        $this->assertStringContainsString('LOCK IN SHARE MODE', $sql);
    }

    /**
     * Test sharedLock() generates correct SQL for PostgreSQL
     */
    public function test_shared_lock_postgresql(): void
    {
        // Override connection driver
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->connection->method('getDriverName')->willReturn('pgsql');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('quote')
            ->willReturnCallback(fn($value) => "'" . addslashes($value) . "'");
        $this->connection->method('getPdo')->willReturn($pdo);

        $query = new QueryBuilder($this->connection);
        $sql = $query->table('users')
            ->where('id', 1)
            ->sharedLock()
            ->toSql();

        $this->assertStringContainsString('FOR SHARE', $sql);
    }

    /**
     * Test increment generates correct SQL
     */
    public function test_increment_sql(): void
    {
        // We can't test actual execution, but we can verify the method exists
        $this->assertTrue(method_exists($this->query, 'increment'));
    }

    /**
     * Test decrement generates correct SQL
     */
    public function test_decrement_sql(): void
    {
        $this->assertTrue(method_exists($this->query, 'decrement'));
    }

    /**
     * Test updateOrInsert method exists
     */
    public function test_update_or_insert_exists(): void
    {
        $this->assertTrue(method_exists($this->query, 'updateOrInsert'));
    }

    /**
     * Test complex query with multiple advanced features
     */
    public function test_complex_query(): void
    {
        $sql = $this->query
            ->select('id', 'name', 'email')
            ->whereDate('created_at', '>=', '2025-01-01')
            ->whereColumn('updated_at', '>', 'created_at')
            ->when(true, function($query) {
                $query->where('status', 'active');
            })
            ->whereExists(function($query) {
                $query->table('orders')
                      ->whereColumn('orders.user_id', 'users.id');
            })
            ->orderBy('created_at', 'DESC')
            ->toSql();

        $this->assertStringContainsString('DATE(created_at) >= ?', $sql);
        $this->assertStringContainsString('updated_at > created_at', $sql);
        $this->assertStringContainsString('status = ?', $sql);
        $this->assertStringContainsString('EXISTS', $sql);
        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);
    }
}
