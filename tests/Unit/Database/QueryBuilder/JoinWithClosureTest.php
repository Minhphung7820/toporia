<?php

declare(strict_types=1);

namespace Tests\Unit\Database\QueryBuilder;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\Contracts\ConnectionInterface;

/**
 * JOIN with Closure Unit Tests
 *
 * Tests QueryBuilder's enhanced JOIN methods that support Closure for complex conditions
 *
 * Features tested:
 * - join() with Closure
 * - leftJoin() with Closure
 * - rightJoin() with Closure
 * - crossJoin()
 * - Backward compatibility with simple JOIN
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class JoinWithClosureTest extends TestCase
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

    // ==================== Backward Compatibility Tests ====================

    public function test_simple_join_still_works(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->join('orders', 'users.id', '=', 'orders.user_id');

        $sql = $query->toSql();

        $this->assertStringContainsString('INNER JOIN `orders`', $sql);
        $this->assertStringContainsString('ON `users`.`id` = `orders`.`user_id`', $sql);
    }

    public function test_left_join_still_works(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->leftJoin('orders', 'users.id', '=', 'orders.user_id');

        $sql = $query->toSql();

        $this->assertStringContainsString('LEFT JOIN `orders`', $sql);
    }

    public function test_right_join_still_works(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->rightJoin('orders', 'users.id', '=', 'orders.user_id');

        $sql = $query->toSql();

        $this->assertStringContainsString('RIGHT JOIN `orders`', $sql);
    }

    // ==================== JOIN with Closure Tests ====================

    public function test_join_with_closure_simple_on(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->join('orders', function($join) {
                $join->on('users.id', '=', 'orders.user_id');
            });

        $sql = $query->toSql();

        $this->assertStringContainsString('INNER JOIN `orders`', $sql);
        $this->assertStringContainsString('ON `users`.`id` = `orders`.`user_id`', $sql);
    }

    public function test_join_with_closure_multiple_on_conditions(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->join('orders', function($join) {
                $join->on('users.id', '=', 'orders.user_id')
                     ->on('users.status', '=', 'orders.user_status');
            });

        $sql = $query->toSql();

        $this->assertStringContainsString('`users`.`id` = `orders`.`user_id`', $sql);
        $this->assertStringContainsString('AND `users`.`status` = `orders`.`user_status`', $sql);
    }

    public function test_join_with_closure_with_where_condition(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->join('orders', function($join) {
                $join->on('users.id', '=', 'orders.user_id')
                     ->where('orders.status', '=', 'active');
            });

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('AND `orders`.`status` = ?', $sql);
        $this->assertContains('active', $bindings);
    }

    public function test_join_with_closure_or_where(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->join('orders', function($join) {
                $join->on('users.id', '=', 'orders.user_id')
                     ->where('orders.status', '=', 'active')
                     ->orWhere('orders.priority', '>', 5);
            });

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('OR `orders`.`priority` > ?', $sql);
        $this->assertContains('active', $bindings);
        $this->assertContains(5, $bindings);
    }

    public function test_join_with_closure_where_null(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->join('orders', function($join) {
                $join->on('users.id', '=', 'orders.user_id')
                     ->whereNull('orders.deleted_at');
            });

        $sql = $query->toSql();

        $this->assertStringContainsString('AND `orders`.`deleted_at` IS NULL', $sql);
    }

    public function test_join_with_closure_where_not_null(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->join('orders', function($join) {
                $join->on('users.id', '=', 'orders.user_id')
                     ->whereNotNull('orders.completed_at');
            });

        $sql = $query->toSql();

        $this->assertStringContainsString('AND `orders`.`completed_at` IS NOT NULL', $sql);
    }

    // ==================== LEFT/RIGHT JOIN with Closure ====================

    public function test_left_join_with_closure(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->leftJoin('orders', function($join) {
                $join->on('users.id', '=', 'orders.user_id')
                     ->where('orders.status', '=', 'completed');
            });

        $sql = $query->toSql();

        $this->assertStringContainsString('LEFT JOIN `orders`', $sql);
        $this->assertStringContainsString('AND `orders`.`status` = ?', $sql);
    }

    public function test_right_join_with_closure(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->rightJoin('orders', function($join) {
                $join->on('users.id', '=', 'orders.user_id')
                     ->whereNotNull('orders.shipped_at');
            });

        $sql = $query->toSql();

        $this->assertStringContainsString('RIGHT JOIN `orders`', $sql);
        $this->assertStringContainsString('AND `orders`.`shipped_at` IS NOT NULL', $sql);
    }

    // ==================== CROSS JOIN Tests ====================

    public function test_cross_join(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('products')
            ->crossJoin('categories');

        $sql = $query->toSql();

        $this->assertStringContainsString('CROSS JOIN `categories`', $sql);
        $this->assertStringNotContainsString('ON', $sql); // No ON clause for CROSS JOIN
    }

    // ==================== Multiple JOINs ====================

    public function test_multiple_joins_with_closure(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->join('orders', function($join) {
                $join->on('users.id', '=', 'orders.user_id');
            })
            ->join('order_items', function($join) {
                $join->on('orders.id', '=', 'order_items.order_id');
            });

        $sql = $query->toSql();

        $this->assertStringContainsString('INNER JOIN `orders`', $sql);
        $this->assertStringContainsString('INNER JOIN `order_items`', $sql);
    }

    public function test_mixed_simple_and_closure_joins(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->join('profiles', 'users.id', '=', 'profiles.user_id')
            ->join('orders', function($join) {
                $join->on('users.id', '=', 'orders.user_id')
                     ->where('orders.status', '=', 'active');
            });

        $sql = $query->toSql();

        $this->assertStringContainsString('INNER JOIN `profiles`', $sql);
        $this->assertStringContainsString('INNER JOIN `orders`', $sql);
    }

    // ==================== Complex Real-World Scenarios ====================

    public function test_complex_join_for_active_users_with_recent_orders(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->select('users.id', 'users.name', 'orders.total')
            ->table('users')
            ->join('orders', function($join) {
                $join->on('users.id', '=', 'orders.user_id')
                     ->where('orders.status', '=', 'completed')
                     ->where('orders.total', '>', 100)
                     ->whereNotNull('orders.paid_at');
            })
            ->where('users.status', '=', 'active');

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('INNER JOIN `orders`', $sql);
        $this->assertStringContainsString('AND `orders`.`status` = ?', $sql);
        $this->assertStringContainsString('AND `orders`.`total` > ?', $sql);
        $this->assertStringContainsString('AND `orders`.`paid_at` IS NOT NULL', $sql);
        $this->assertStringContainsString('WHERE `users`.`status` = ?', $sql);

        // Check bindings order
        $this->assertContains('completed', $bindings);
        $this->assertContains(100, $bindings);
        $this->assertContains('active', $bindings);
    }

    public function test_left_join_to_find_users_without_orders(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->select('users.id', 'users.name')
            ->table('users')
            ->leftJoin('orders', function($join) {
                $join->on('users.id', '=', 'orders.user_id')
                     ->whereNull('orders.cancelled_at');
            })
            ->whereNull('orders.id');

        $sql = $query->toSql();

        $this->assertStringContainsString('LEFT JOIN `orders`', $sql);
        $this->assertStringContainsString('AND `orders`.`cancelled_at` IS NULL', $sql);
        $this->assertStringContainsString('WHERE `orders`.`id` IS NULL', $sql);
    }

    // ==================== Performance Tests ====================

    public function test_join_invalidates_cache(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users');

        $sql1 = $query->toSql();

        $query->join('orders', function($join) {
            $join->on('users.id', '=', 'orders.user_id');
        });

        $sql2 = $query->toSql();

        $this->assertNotEquals($sql1, $sql2);
    }

    // ==================== Edge Cases ====================

    public function test_join_with_empty_closure(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->join('orders', function($join) {
                // Empty closure - no conditions
            });

        $sql = $query->toSql();

        // Should still generate JOIN but without ON clause
        $this->assertStringContainsString('INNER JOIN `orders`', $sql);
    }

    public function test_join_supports_different_operators(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->join('limits', function($join) {
                $join->on('users.score', '>', 'limits.min_score')
                     ->on('users.score', '<', 'limits.max_score');
            });

        $sql = $query->toSql();

        $this->assertStringContainsString('`users`.`score` > `limits`.`min_score`', $sql);
        $this->assertStringContainsString('AND `users`.`score` < `limits`.`max_score`', $sql);
    }
}
