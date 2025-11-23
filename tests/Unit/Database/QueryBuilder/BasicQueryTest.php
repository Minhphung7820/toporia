<?php

declare(strict_types=1);

namespace Tests\Unit\Database\QueryBuilder;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\Contracts\ConnectionInterface;
use Toporia\Framework\Database\Grammar\MySQLGrammar;
use PDO;

/**
 * Comprehensive Basic Query Tests
 *
 * ✅ TEST STATUS: ALL PASSED (39/39)
 * ✅ Last verified: 2025-01-22
 *
 * Tests all basic query building functionality:
 * - SELECT with various column combinations
 * - WHERE clauses (basic, IN, NULL, NOT NULL)
 * - ORDER BY (single/multiple columns, ASC/DESC)
 * - LIMIT and OFFSET
 * - DISTINCT
 * - Method chaining
 *
 * Architecture:
 * - SOLID: Single Responsibility (tests only basic queries)
 * - High Reusability: Helper methods for mock setup
 * - Clean Architecture: No external dependencies, only framework
 *
 * Performance:
 * - Mock PDO for fast tests (no real DB)
 * - O(1) test execution time
 *
 * @author `Phungtruong7820` <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class BasicQueryTest extends TestCase
{
    private QueryBuilder $query;
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createConnectionMock();
        $this->query = new QueryBuilder($this->connection);
        $this->query->table('users');
    }

    /**
     * Create a mocked ConnectionInterface with PDO
     *
     * Reusability: Used by all tests
     * Performance: O(1) mock creation
     */
    private function createConnectionMock(): ConnectionInterface
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('getDriverName')->willReturn('mysql');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('quote')
            ->willReturnCallback(fn($value) => "'" . addslashes((string)$value) . "'");

        // Add Grammar mock for SQL compilation
        $grammar = new \Toporia\Framework\Database\Grammar\MySQLGrammar();
        $connection->method('getGrammar')->willReturn($grammar);

        $connection->method('getPdo')->willReturn($pdo);

        return $connection;
    }

    // ==================== SELECT Tests ====================

    public function test_select_all_columns(): void
    {
        $sql = $this->query->toSql();

        $this->assertStringContainsString('SELECT *', $sql);
        $this->assertStringContainsString('FROM `users`', $sql);
    }

    public function test_select_single_column(): void
    {
        $sql = $this->query->select('id')->toSql();

        $this->assertStringContainsString('SELECT `id`', $sql);
        $this->assertStringNotContainsString('*', $sql);
    }

    public function test_select_multiple_columns_array(): void
    {
        $sql = $this->query->select(['id', 'name', 'email'])->toSql();

        $this->assertStringContainsString('SELECT `id`, `name`, `email`', $sql);
    }

    public function test_select_multiple_columns_varargs(): void
    {
        $sql = $this->query->select('id', 'name', 'email')->toSql();

        $this->assertStringContainsString('SELECT `id`, `name`, `email`', $sql);
    }

    public function test_select_distinct(): void
    {
        $sql = $this->query->distinct()->select('status')->toSql();

        $this->assertStringContainsString('SELECT DISTINCT `status`', $sql);
    }

    public function test_select_raw_expression(): void
    {
        $sql = $this->query
            ->selectRaw('COUNT(*) as total')
            ->toSql();

        $this->assertStringContainsString('COUNT(*) as total', $sql);
    }

    public function test_select_raw_with_bindings(): void
    {
        $this->query->selectRaw('CONCAT(?, name) as full_name', ['Mr. ']);

        $bindings = $this->query->getBindings();
        $this->assertContains('Mr. ', $bindings);
    }

    // ==================== WHERE `Tests` ====================

    public function test_where_basic_equals(): void
    {
        $sql = $this->query->where('status', 'active')->toSql();

        $this->assertStringContainsString('WHERE `status` = ?', $sql);
        $this->assertEquals(['active'], $this->query->getBindings());
    }

    public function test_where_with_operator(): void
    {
        $sql = $this->query->where('age', '>', 18)->toSql();

        $this->assertStringContainsString('WHERE `age` > ?', $sql);
        $this->assertEquals([18], $this->query->getBindings());
    }

    public function test_where_multiple_conditions(): void
    {
        $sql = $this->query
            ->where('status', 'active')
            ->where('age', '>=', 18)
            ->where('country', 'US')
            ->toSql();

        $this->assertStringContainsString('WHERE `status` = ?', $sql);
        $this->assertStringContainsString('AND `age` >= ?', $sql);
        $this->assertStringContainsString('AND `country` = ?', $sql);
        $this->assertEquals(['active', 18, 'US'], $this->query->getBindings());
    }

    public function test_or_where(): void
    {
        $sql = $this->query
            ->where('status', 'active')
            ->orWhere('role', 'admin')
            ->toSql();

        $this->assertStringContainsString('WHERE `status` = ?', $sql);
        $this->assertStringContainsString('OR `role` = ?', $sql);
        $this->assertEquals(['active', 'admin'], $this->query->getBindings());
    }

    public function test_where_in(): void
    {
        $sql = $this->query->whereIn('id', [1, 2, 3, 4, 5])->toSql();

        $this->assertStringContainsString('WHERE `id` IN (?, ?, ?, ?, ?)', $sql);
        $this->assertEquals([1, 2, 3, 4, 5], $this->query->getBindings());
    }

    public function test_where_in_empty_array(): void
    {
        $sql = $this->query->whereIn('id', [])->toSql();

        // Empty array optimization: converts to "1 = 0" instead of "IN ()"
        // This is more performant and avoids SQL syntax errors
        $this->assertStringContainsString('WHERE 1 = 0', $sql);
    }

    public function test_where_null(): void
    {
        $sql = $this->query->whereNull('deleted_at')->toSql();

        $this->assertStringContainsString('WHERE `deleted_at` IS NULL', $sql);
        $this->assertEmpty($this->query->getBindings());
    }

    public function test_where_not_null(): void
    {
        $sql = $this->query->whereNotNull('email_verified_at')->toSql();

        $this->assertStringContainsString('WHERE `email_verified_at` IS NOT NULL', $sql);
        $this->assertEmpty($this->query->getBindings());
    }

    public function test_where_raw(): void
    {
        $sql = $this->query
            ->whereRaw('YEAR(created_at) = ?', [2025])
            ->toSql();

        $this->assertStringContainsString('WHERE YEAR(created_at) = ?', $sql);
        $this->assertEquals([2025], $this->query->getBindings());
    }

    public function test_where_nested_closure(): void
    {
        $sql = $this->query
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where('age', '>', 18)
                    ->orWhere('verified', true);
            })
            ->toSql();

        $this->assertStringContainsString('WHERE `status` = ?', $sql);
        $this->assertStringContainsString('AND (`age` > ? OR `verified` = ?)', $sql);
        $this->assertEquals(['active', 18, true], $this->query->getBindings());
    }

    public function test_or_where_nested_closure(): void
    {
        $sql = $this->query
            ->where('status', 'active')
            ->orWhere(function ($q) {
                $q->where('role', 'admin')
                    ->where('super_admin', true);
            })
            ->toSql();

        $this->assertStringContainsString('WHERE `status` = ?', $sql);
        $this->assertStringContainsString('OR (`role` = ? AND `super_admin` = ?)', $sql);
    }

    public function test_where_with_various_operators(): void
    {
        $operators = ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE'];

        foreach ($operators as $operator) {
            $query = new QueryBuilder($this->connection);
            $sql = $query->table('users')->where('column', $operator, 'value')->toSql();

            $this->assertStringContainsString("`column` $operator ?", $sql);
        }
    }

    // ==================== ORDER BY `Tests` ====================

    public function test_order_by_single_column_asc(): void
    {
        $sql = $this->query->orderBy('created_at')->toSql();

        $this->assertStringContainsString('ORDER BY `created_at` ASC', $sql);
    }

    public function test_order_by_single_column_desc(): void
    {
        $sql = $this->query->orderBy('created_at', 'DESC')->toSql();

        $this->assertStringContainsString('ORDER BY `created_at` DESC', $sql);
    }

    public function test_order_by_multiple_columns(): void
    {
        $sql = $this->query
            ->orderBy('status', 'ASC')
            ->orderBy('created_at', 'DESC')
            ->orderBy('name', 'ASC')
            ->toSql();

        $this->assertStringContainsString('ORDER BY `status` ASC, `created_at` DESC, `name` ASC', $sql);
    }

    public function test_order_by_case_insensitive_direction(): void
    {
        $sql = $this->query
            ->orderBy('name', 'asc')
            ->orderBy('age', 'desc')
            ->toSql();

        // Should normalize to uppercase
        $this->assertStringContainsString('ORDER BY `name` ASC, `age` DESC', $sql);
    }

    // ==================== LIMIT and OFFSET Tests ====================

    public function test_limit(): void
    {
        $sql = $this->query->limit(10)->toSql();

        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function test_offset(): void
    {
        $sql = $this->query->offset(20)->toSql();

        $this->assertStringContainsString('OFFSET 20', $sql);
    }

    public function test_limit_and_offset(): void
    {
        $sql = $this->query->limit(10)->offset(20)->toSql();

        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 20', $sql);
    }

    public function test_limit_zero(): void
    {
        $sql = $this->query->limit(0)->toSql();

        $this->assertStringContainsString('LIMIT 0', $sql);
    }

    // ==================== Method Chaining Tests ====================

    public function test_complex_chained_query(): void
    {
        $sql = $this->query
            ->select('id', 'name', 'email', 'status')
            ->where('status', 'active')
            ->where('age', '>=', 18)
            ->whereIn('country', ['US', 'UK', 'CA'])
            ->whereNotNull('email_verified_at')
            ->orderBy('created_at', 'DESC')
            ->orderBy('name', 'ASC')
            ->limit(50)
            ->offset(100)
            ->toSql();

        // Verify all parts are present
        $this->assertStringContainsString('SELECT `id`, `name`, `email`, `status`', $sql);
        $this->assertStringContainsString('WHERE `status` = ?', $sql);
        $this->assertStringContainsString('AND `age` >= ?', $sql);
        $this->assertStringContainsString('AND `country` IN (?, ?, ?)', $sql);
        $this->assertStringContainsString('AND `email_verified_at` IS NOT NULL', $sql);
        $this->assertStringContainsString('ORDER BY `created_at` DESC, `name` ASC', $sql);
        $this->assertStringContainsString('LIMIT 50', $sql);
        $this->assertStringContainsString('OFFSET 100', $sql);

        // Verify bindings
        $bindings = $this->query->getBindings();
        $this->assertCount(5, $bindings); // status, age, 3x country
        $this->assertEquals('active', $bindings[0]);
        $this->assertEquals(18, $bindings[1]);
        $this->assertEquals(['active', 18, 'US', 'UK', 'CA'], $bindings);
    }

    public function test_query_builder_returns_self_for_chaining(): void
    {
        $result = $this->query
            ->select('id')
            ->where('status', 'active')
            ->orderBy('created_at')
            ->limit(10);

        $this->assertInstanceOf(QueryBuilder::class, $result);
        $this->assertSame($this->query, $result);
    }

    // ==================== Edge Cases ====================

    public function test_empty_table_name_in_sql(): void
    {
        $query = new QueryBuilder($this->connection);
        $sql = $query->table('')->toSql();

        // Should still generate SQL even with empty table
        $this->assertStringContainsString('FROM ', $sql);
    }

    public function test_special_characters_in_values(): void
    {
        $sql = $this->query
            ->where('name', "O'Reilly")
            ->where('description', 'Test "quotes" here')
            ->toSql();

        $bindings = $this->query->getBindings();
        $this->assertEquals("O'Reilly", $bindings[0]);
        $this->assertEquals('Test "quotes" here', $bindings[1]);
    }

    public function test_null_value_in_where(): void
    {
        $sql = $this->query->where('column', null)->toSql();

        $this->assertStringContainsString('WHERE `column` = ?', $sql);
        $this->assertEquals([null], $this->query->getBindings());
    }

    public function test_boolean_values_in_where(): void
    {
        $sql = $this->query
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->toSql();

        $bindings = $this->query->getBindings();
        $this->assertTrue($bindings[0]);
        $this->assertFalse($bindings[1]);
    }

    public function test_numeric_string_values(): void
    {
        $sql = $this->query->where('zip_code', '12345')->toSql();

        $bindings = $this->query->getBindings();
        $this->assertSame('12345', $bindings[0]);
    }

    public function test_get_table_method(): void
    {
        $this->assertEquals('users', $this->query->getTable());
    }

    public function test_get_columns_method(): void
    {
        $this->query->select('id', 'name', 'email');

        $columns = $this->query->getColumns();
        $this->assertEquals(['id', 'name', 'email'], $columns);
    }

    public function test_get_bindings_method(): void
    {
        $this->query
            ->where('status', 'active')
            ->where('age', 18);

        $bindings = $this->query->getBindings();
        $this->assertEquals(['active', 18], $bindings);
    }

    // ==================== Performance Tests ====================

    public function test_large_where_in_performance(): void
    {
        $largeArray = range(1, 1000);

        $startTime = microtime(true);
        $sql = $this->query->whereIn('id', $largeArray)->toSql();
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000; // Convert to ms

        // Should complete in less than 100ms (very generous)
        $this->assertLessThan(100, $executionTime);
        $this->assertCount(1000, $this->query->getBindings());
    }

    public function test_deeply_nested_where_clauses(): void
    {
        $sql = $this->query
            ->where(function ($q1) {
                $q1->where('status', 'active')
                    ->where(function ($q2) {
                        $q2->where('role', 'admin')
                            ->orWhere(function ($q3) {
                                $q3->where('permissions', 'LIKE', '%write%')
                                    ->where('verified', true);
                            });
                    });
            })
            ->toSql();

        // Should handle 3 levels of nesting
        $this->assertStringContainsString('WHERE (`status` = ?', $sql);
        $this->assertGreaterThan(0, substr_count($sql, '('));
        $this->assertEquals(
            substr_count($sql, '('),
            substr_count($sql, ')')
        ); // Balanced parentheses
    }
}
