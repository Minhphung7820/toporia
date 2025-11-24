<?php

declare(strict_types=1);

namespace Tests\Unit\Database\QueryBuilder;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\Contracts\ConnectionInterface;

/**
 * WHERE IN/NOT IN with Subquery Unit Tests
 *
 * Tests whereIn/whereNotIn methods enhanced with Closure support for subqueries
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class WhereInSubqueryTest extends TestCase
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

    // ==================== whereIn with Array (Backward Compatibility) ====================

    public function test_where_in_with_array_still_works(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->whereIn('id', [1, 2, 3, 4, 5]);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('WHERE `id` IN (?, ?, ?, ?, ?)', $sql);
        $this->assertCount(5, $bindings);
        $this->assertEquals([1, 2, 3, 4, 5], $bindings);
    }

    public function test_where_in_with_empty_array(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->whereIn('id', []);

        $sql = $query->toSql();

        // Should return always false condition
        $this->assertStringContainsString('WHERE 1 = 0', $sql);
    }

    // ==================== whereIn with Closure (Subquery) ====================

    public function test_where_in_with_simple_subquery(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->whereIn('id', function($query) {
                $query->select('user_id')->table('active_sessions');
            });

        $sql = $query->toSql();

        $this->assertStringContainsString('WHERE `id` IN (SELECT', $sql);
        $this->assertStringContainsString('FROM `active_sessions`', $sql);
    }

    public function test_where_in_with_subquery_with_conditions(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->whereIn('id', function($query) {
                $query->select('user_id')
                      ->table('active_sessions')
                      ->where('last_activity', '>', '2025-01-01');
            });

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('WHERE `id` IN (SELECT', $sql);
        $this->assertStringContainsString('WHERE `last_activity` > ?', $sql);
        $this->assertContains('2025-01-01', $bindings);
    }

    public function test_where_in_transfers_subquery_bindings(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->where('status', '=', 'active')
            ->whereIn('id', function($query) {
                $query->select('user_id')
                      ->table('orders')
                      ->where('total', '>', 100)
                      ->where('status', '=', 'completed');
            });

        $bindings = $query->getBindings();

        // Should have 3 bindings: 'active' + 100 + 'completed'
        $this->assertCount(3, $bindings);
        $this->assertContains('active', $bindings);
        $this->assertContains(100, $bindings);
        $this->assertContains('completed', $bindings);
    }

    // ==================== whereNotIn with Closure ====================

    public function test_where_not_in_with_subquery(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->whereNotIn('id', function($query) {
                $query->select('user_id')->table('banned_users');
            });

        $sql = $query->toSql();

        $this->assertStringContainsString('WHERE `id` NOT IN (SELECT', $sql);
        $this->assertStringContainsString('FROM `banned_users`', $sql);
    }

    public function test_where_not_in_with_empty_array(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->whereNotIn('id', []);

        $sql = $query->toSql();

        // NOT IN () should be always true
        $this->assertStringContainsString('WHERE 1 = 1', $sql);
    }

    public function test_where_not_in_with_array(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->whereNotIn('id', [1, 2, 3]);

        $sql = $query->toSql();

        $this->assertStringContainsString('WHERE `id` NOT IN (?, ?, ?)', $sql);
    }

    // ==================== orWhereIn / orWhereNotIn ====================

    public function test_or_where_in_with_subquery(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->where('status', '=', 'premium')
            ->orWhereIn('id', function($query) {
                $query->select('user_id')->table('vip_users');
            });

        $sql = $query->toSql();

        $this->assertStringContainsString('WHERE `status` = ?', $sql);
        $this->assertStringContainsString('OR `id` IN (SELECT', $sql);
    }

    public function test_or_where_not_in_with_subquery(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->where('status', '=', 'active')
            ->orWhereNotIn('id', function($query) {
                $query->select('user_id')->table('suspended_users');
            });

        $sql = $query->toSql();

        $this->assertStringContainsString('WHERE `status` = ?', $sql);
        $this->assertStringContainsString('OR `id` NOT IN (SELECT', $sql);
    }

    // ==================== Complex Real-World Scenarios ====================

    public function test_find_users_with_recent_activity(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->select('*')
            ->table('users')
            ->whereIn('id', function($query) {
                $query->select('user_id')
                      ->table('activity_log')
                      ->where('created_at', '>', '2025-01-01')
                      ->where('action', '=', 'login');
            })
            ->where('status', '=', 'active');

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('WHERE `id` IN (SELECT', $sql);
        $this->assertStringContainsString('AND `status` = ?', $sql);
        $this->assertContains('2025-01-01', $bindings);
        $this->assertContains('login', $bindings);
        $this->assertContains('active', $bindings);
    }

    public function test_find_products_not_in_any_active_cart(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->select('products.id', 'products.name', 'products.stock')
            ->table('products')
            ->whereNotIn('products.id', function($query) {
                $query->select('product_id')
                      ->table('cart_items')
                      ->join('carts', 'cart_items.cart_id', '=', 'carts.id')
                      ->where('carts.status', '=', 'active');
            })
            ->where('products.stock', '>', 0);

        $sql = $query->toSql();

        $this->assertStringContainsString('WHERE `products`.`id` NOT IN (SELECT', $sql);
        $this->assertStringContainsString('INNER JOIN', $sql);
    }

    public function test_multiple_where_in_subqueries(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->whereIn('id', function($query) {
                $query->select('user_id')
                      ->table('orders')
                      ->where('total', '>', 1000);
            })
            ->whereIn('country_id', function($query) {
                $query->select('id')
                      ->table('countries')
                      ->where('region', '=', 'EU');
            });

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('WHERE `id` IN (SELECT', $sql);
        $this->assertStringContainsString('AND `country_id` IN (SELECT', $sql);
        $this->assertCount(2, $bindings); // 1000 + 'EU'
    }

    public function test_where_in_and_where_not_in_combined(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->whereIn('id', function($query) {
                $query->select('user_id')
                      ->table('orders')
                      ->where('status', '=', 'completed');
            })
            ->whereNotIn('id', function($query) {
                $query->select('user_id')
                      ->table('refunds')
                      ->where('status', '=', 'approved');
            });

        $sql = $query->toSql();

        $this->assertStringContainsString('WHERE `id` IN (SELECT', $sql);
        $this->assertStringContainsString('AND `id` NOT IN (SELECT', $sql);
    }

    public function test_find_customers_who_bought_product_A_but_not_product_B(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->select('users.id', 'users.name', 'users.email')
            ->table('users')
            ->whereIn('users.id', function($query) {
                $query->select('orders.user_id')
                      ->table('orders')
                      ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                      ->where('order_items.product_id', '=', 1); // Product A
            })
            ->whereNotIn('users.id', function($query) {
                $query->select('orders.user_id')
                      ->table('orders')
                      ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                      ->where('order_items.product_id', '=', 2); // Product B
            });

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('WHERE `users`.`id` IN (SELECT', $sql);
        $this->assertStringContainsString('AND `users`.`id` NOT IN (SELECT', $sql);
        $this->assertContains(1, $bindings);
        $this->assertContains(2, $bindings);
    }

    // ==================== Performance Tests ====================

    public function test_where_in_invalidates_cache(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users');

        $sql1 = $query->toSql();

        $query->whereIn('id', function($query) {
            $query->select('user_id')->table('active_users');
        });

        $sql2 = $query->toSql();

        $this->assertNotEquals($sql1, $sql2);
    }

    // ==================== Edge Cases ====================

    public function test_where_in_with_complex_nested_subquery(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->whereIn('id', function($query) {
                $query->select('user_id')
                      ->table('orders')
                      ->whereIn('product_id', function($q) {
                          $q->select('id')
                            ->table('products')
                            ->where('category', '=', 'electronics');
                      });
            });

        $sql = $query->toSql();

        // Should have nested IN clauses
        $this->assertStringContainsString('WHERE `id` IN (SELECT', $sql);
        $this->assertStringContainsString('WHERE `product_id` IN (SELECT', $sql);
    }

    public function test_where_in_subquery_with_group_by_and_having(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->whereIn('id', function($query) {
                $query->select('user_id')
                      ->table('orders')
                      ->groupBy('user_id')
                      ->having('COUNT(*)', '>', 10);
            });

        $sql = $query->toSql();

        $this->assertStringContainsString('WHERE `id` IN (SELECT', $sql);
        $this->assertStringContainsString('GROUP BY', $sql);
        $this->assertStringContainsString('HAVING', $sql);
    }

    public function test_or_where_in_with_array(): void
    {
        $query = (new QueryBuilder($this->connection))
            ->table('users')
            ->where('status', '=', 'active')
            ->orWhereIn('role', ['admin', 'moderator']);

        $sql = $query->toSql();

        $this->assertStringContainsString('WHERE `status` = ?', $sql);
        $this->assertStringContainsString('OR `role` IN (?, ?)', $sql);
    }
}
