<?php

declare(strict_types=1);

namespace Tests\Unit\Database\QueryBuilder;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Database\Query\JoinClause;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\Contracts\ConnectionInterface;

/**
 * JoinClause Unit Tests
 *
 * Tests the new JoinClause class with fluent interface for complex JOIN conditions
 *
 * Architecture:
 * - SOLID: Tests single responsibility of JoinClause
 * - Clean Architecture: No external dependencies
 * - High Reusability: Mock helpers reused across tests
 *
 * Performance: O(1) per test
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class JoinClauseTest extends TestCase
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

        // Add Grammar mock
        $grammar = new \Toporia\Framework\Database\Grammar\MySQLGrammar();
        $connection->method('getGrammar')->willReturn($grammar);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('quote')
            ->willReturnCallback(fn($value) => "'" . addslashes((string)$value) . "'");

        $connection->method('getPdo')->willReturn($pdo);

        return $connection;
    }

    // ==================== Constructor Tests ====================

    public function test_constructor_sets_type_and_table(): void
    {
        $join = new JoinClause('INNER', 'orders');

        $this->assertSame('INNER', $join->getType());
        $this->assertSame('orders', $join->getTable());
    }

    public function test_constructor_uppercases_type(): void
    {
        $join = new JoinClause('left', 'orders');

        $this->assertSame('LEFT', $join->getType());
    }

    // ==================== ON Clause Tests ====================

    public function test_on_adds_simple_condition(): void
    {
        $join = new JoinClause('INNER', 'orders');
        $join->on('users.id', '=', 'orders.user_id');

        $clauses = $join->getClauses();

        $this->assertCount(1, $clauses);
        $this->assertSame('on', $clauses[0]['type']);
        $this->assertSame('users.id', $clauses[0]['first']);
        $this->assertSame('=', $clauses[0]['operator']);
        $this->assertSame('orders.user_id', $clauses[0]['second']);
        $this->assertSame('AND', $clauses[0]['boolean']);
    }

    public function test_on_supports_different_operators(): void
    {
        $join = new JoinClause('INNER', 'orders');
        $join->on('users.id', '>', 'orders.min_user_id')
             ->on('users.id', '<', 'orders.max_user_id');

        $clauses = $join->getClauses();

        $this->assertSame('>', $clauses[0]['operator']);
        $this->assertSame('<', $clauses[1]['operator']);
    }

    public function test_or_on_adds_or_condition(): void
    {
        $join = new JoinClause('INNER', 'orders');
        $join->on('users.id', '=', 'orders.user_id')
             ->orOn('users.email', '=', 'orders.email');

        $clauses = $join->getClauses();

        $this->assertSame('AND', $clauses[0]['boolean']);
        $this->assertSame('OR', $clauses[1]['boolean']);
    }

    // ==================== WHERE Clause Tests ====================

    public function test_where_adds_condition_with_value(): void
    {
        $join = new JoinClause('INNER', 'orders');
        $join->where('orders.status', '=', 'active');

        $clauses = $join->getClauses();

        $this->assertCount(1, $clauses);
        $this->assertSame('where', $clauses[0]['type']);
        $this->assertSame('orders.status', $clauses[0]['column']);
        $this->assertSame('=', $clauses[0]['operator']);
        $this->assertSame('active', $clauses[0]['value']);
        $this->assertSame('AND', $clauses[0]['boolean']);
    }

    public function test_or_where_adds_or_condition(): void
    {
        $join = new JoinClause('INNER', 'orders');
        $join->where('orders.status', '=', 'active')
             ->orWhere('orders.priority', '>', 5);

        $clauses = $join->getClauses();

        $this->assertSame('AND', $clauses[0]['boolean']);
        $this->assertSame('OR', $clauses[1]['boolean']);
        $this->assertSame(5, $clauses[1]['value']);
    }

    public function test_where_binds_values_to_parent_query(): void
    {
        $parentQuery = new QueryBuilder($this->connection);
        $parentQuery->table('users');

        $join = new JoinClause('INNER', 'orders');
        $join->setParentQuery($parentQuery);
        $join->where('orders.status', '=', 'active')
             ->where('orders.total', '>', 100);

        // Parent query should have 2 bindings
        $bindings = $parentQuery->getBindings();
        $this->assertCount(2, $bindings);
        $this->assertSame('active', $bindings[0]);
        $this->assertSame(100, $bindings[1]);
    }

    // ==================== NULL Tests ====================

    public function test_where_null_adds_null_condition(): void
    {
        $join = new JoinClause('INNER', 'orders');
        $join->whereNull('orders.deleted_at');

        $clauses = $join->getClauses();

        $this->assertSame('whereNull', $clauses[0]['type']);
        $this->assertSame('orders.deleted_at', $clauses[0]['column']);
    }

    public function test_or_where_null_adds_or_condition(): void
    {
        $join = new JoinClause('INNER', 'orders');
        $join->whereNull('orders.deleted_at')
             ->orWhereNull('orders.cancelled_at');

        $clauses = $join->getClauses();

        $this->assertSame('AND', $clauses[0]['boolean']);
        $this->assertSame('OR', $clauses[1]['boolean']);
    }

    public function test_where_not_null_adds_not_null_condition(): void
    {
        $join = new JoinClause('INNER', 'orders');
        $join->whereNotNull('orders.completed_at');

        $clauses = $join->getClauses();

        $this->assertSame('whereNotNull', $clauses[0]['type']);
        $this->assertSame('orders.completed_at', $clauses[0]['column']);
    }

    public function test_or_where_not_null_adds_or_condition(): void
    {
        $join = new JoinClause('INNER', 'orders');
        $join->whereNotNull('orders.completed_at')
             ->orWhereNotNull('orders.shipped_at');

        $clauses = $join->getClauses();

        $this->assertSame('AND', $clauses[0]['boolean']);
        $this->assertSame('OR', $clauses[1]['boolean']);
    }

    // ==================== Complex Combination Tests ====================

    public function test_complex_join_with_multiple_conditions(): void
    {
        $join = new JoinClause('INNER', 'orders');
        $join->on('users.id', '=', 'orders.user_id')
             ->where('orders.status', '=', 'active')
             ->orWhere('orders.priority', '>', 5)
             ->whereNotNull('orders.completed_at');

        $clauses = $join->getClauses();

        $this->assertCount(4, $clauses);
        $this->assertSame('on', $clauses[0]['type']);
        $this->assertSame('where', $clauses[1]['type']);
        $this->assertSame('where', $clauses[2]['type']);
        $this->assertSame('whereNotNull', $clauses[3]['type']);
    }

    // ==================== Helper Method Tests ====================

    public function test_has_clauses_returns_false_when_empty(): void
    {
        $join = new JoinClause('INNER', 'orders');

        $this->assertFalse($join->hasClauses());
    }

    public function test_has_clauses_returns_true_when_has_conditions(): void
    {
        $join = new JoinClause('INNER', 'orders');
        $join->on('users.id', '=', 'orders.user_id');

        $this->assertTrue($join->hasClauses());
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $join = new JoinClause('INNER', 'orders');
        $join->on('users.id', '=', 'orders.user_id')
             ->where('orders.status', '=', 'active');

        $array = $join->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('table', $array);
        $this->assertArrayHasKey('clauses', $array);
        $this->assertSame('INNER', $array['type']);
        $this->assertSame('orders', $array['table']);
        $this->assertCount(2, $array['clauses']);
    }

    // ==================== Fluent Interface Tests ====================

    public function test_methods_return_self_for_chaining(): void
    {
        $join = new JoinClause('INNER', 'orders');

        $result = $join->on('users.id', '=', 'orders.user_id')
                       ->where('orders.status', '=', 'active')
                       ->orWhere('orders.priority', '>', 5)
                       ->whereNull('orders.deleted_at')
                       ->whereNotNull('orders.completed_at');

        $this->assertInstanceOf(JoinClause::class, $result);
        $this->assertSame($join, $result);
    }

    // ==================== Edge Cases ====================

    public function test_empty_join_has_no_clauses(): void
    {
        $join = new JoinClause('CROSS', 'categories');

        $this->assertEmpty($join->getClauses());
        $this->assertFalse($join->hasClauses());
    }

    public function test_supports_all_join_types(): void
    {
        $types = ['INNER', 'LEFT', 'RIGHT', 'CROSS'];

        foreach ($types as $type) {
            $join = new JoinClause($type, 'table');
            $this->assertSame($type, $join->getType());
        }
    }
}
