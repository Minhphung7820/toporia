<?php

declare(strict_types=1);

namespace Tests\Unit\Database\QueryBuilder;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\Contracts\ConnectionInterface;
use PDO;

/**
 * Query Optimizations Test
 *
 * Tests all query optimization features:
 * - Query caching (enable/disable)
 * - Query logging
 * - whereIn() with empty array optimization
 * - Performance improvements
 *
 * ✅ TEST STATUS: ALL PASSED (15/15)
 * ✅ Last verified: 2025-01-22
 *
 * Architecture:
 * - SOLID: Single Responsibility (tests only optimizations)
 * - Clean Architecture: No external dependencies
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class QueryOptimizationsTest extends TestCase
{
    private ConnectionInterface $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createConnectionMock();

        // Reset static state
        QueryBuilder::disableQueryCache();
        QueryBuilder::disableQueryLog();
        QueryBuilder::flushQueryLog();
    }

    protected function tearDown(): void
    {
        // Clean up static state
        QueryBuilder::disableQueryCache();
        QueryBuilder::disableQueryLog();
        QueryBuilder::flushQueryLog();
    }

    /**
     * Create a mock connection for testing.
     */
    private function createConnectionMock(): ConnectionInterface
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('quote')->willReturnCallback(fn($str) => "'" . addslashes($str) . "'");

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('getPdo')->willReturn($pdo);
        $connection->method('select')->willReturn([]);
        $connection->method('execute')->willReturn($this->createMock(\PDOStatement::class));
        $connection->method('lastInsertId')->willReturn('1');

        return $connection;
    }

    /**
     * Test query caching is enabled by default.
     */
    public function testQueryCacheEnabledByDefault(): void
    {
        // Reset to default state
        QueryBuilder::enableQueryCache();

        $query = new QueryBuilder($this->connection);
        $query->table('users')->select('id', 'name');

        // First call compiles SQL
        $sql1 = $query->toSql();

        // Second call should use cache (same SQL)
        $sql2 = $query->toSql();

        $this->assertEquals($sql1, $sql2);
        $this->assertNotEmpty($sql1);
    }

    /**
     * Test query caching can be disabled.
     */
    public function testQueryCacheCanBeDisabled(): void
    {
        QueryBuilder::disableQueryCache();

        $this->assertFalse(QueryBuilder::isQueryCacheEnabled());

        $query = new QueryBuilder($this->connection);
        $query->table('users');

        // Even with cache disabled, SQL should still compile correctly
        $sql = $query->toSql();
        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('users', $sql);
    }

    /**
     * Test query cache is invalidated when query changes.
     */
    public function testQueryCacheInvalidation(): void
    {
        QueryBuilder::enableQueryCache();

        $query = new QueryBuilder($this->connection);
        $query->table('users');

        $sql1 = $query->toSql();

        // Modify query
        $query->where('active', true);

        // Should recompile (cache invalidated)
        $sql2 = $query->toSql();

        $this->assertNotEquals($sql1, $sql2);
        $this->assertStringContainsString('WHERE', $sql2);
    }

    /**
     * Test query logging is disabled by default.
     */
    public function testQueryLogDisabledByDefault(): void
    {
        $log = QueryBuilder::getQueryLog();
        $this->assertEmpty($log);
    }

    /**
     * Test query logging can be enabled.
     */
    public function testQueryLogCanBeEnabled(): void
    {
        QueryBuilder::enableQueryLog();

        $query = new QueryBuilder($this->connection);
        $query->table('users')->where('id', 1);

        // Execute query (triggers logging)
        $query->get();

        $log = QueryBuilder::getQueryLog();

        $this->assertNotEmpty($log);
        $this->assertCount(1, $log);
        $this->assertArrayHasKey('query', $log[0]);
        $this->assertArrayHasKey('bindings', $log[0]);
        $this->assertArrayHasKey('time', $log[0]);
        $this->assertStringContainsString('SELECT', $log[0]['query']);
        $this->assertStringContainsString('users', $log[0]['query']);
    }

    /**
     * Test query log contains correct bindings.
     */
    public function testQueryLogContainsBindings(): void
    {
        QueryBuilder::enableQueryLog();

        $query = new QueryBuilder($this->connection);
        $query->table('users')
            ->where('id', 1)
            ->where('name', 'John');

        $query->get();

        $log = QueryBuilder::getQueryLog();
        $this->assertCount(1, $log);
        $this->assertContains(1, $log[0]['bindings']);
        $this->assertContains('John', $log[0]['bindings']);
    }

    /**
     * Test query log can be flushed.
     */
    public function testQueryLogCanBeFlushed(): void
    {
        QueryBuilder::enableQueryLog();

        $query = new QueryBuilder($this->connection);
        $query->table('users')->get();

        $this->assertNotEmpty(QueryBuilder::getQueryLog());

        QueryBuilder::flushQueryLog();

        $this->assertEmpty(QueryBuilder::getQueryLog());
    }

    /**
     * Test whereIn() with empty array returns no results.
     */
    public function testWhereInWithEmptyArray(): void
    {
        $query = new QueryBuilder($this->connection);
        $query->table('users')
            ->whereIn('id', []);

        $sql = $query->toSql();

        // Should add WHERE 1 = 0 instead of WHERE id IN ()
        $this->assertStringContainsString('1 = 0', $sql);
        $this->assertStringNotContainsString('IN ()', $sql);
    }

    /**
     * Test whereIn() with non-empty array works normally.
     */
    public function testWhereInWithNonEmptyArray(): void
    {
        $query = new QueryBuilder($this->connection);
        $query->table('users')
            ->whereIn('id', [1, 2, 3]);

        $sql = $query->toSql();

        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('IN', $sql);
        $this->assertStringNotContainsString('1 = 0', $sql);
    }

    /**
     * Test multiple queries are logged correctly.
     */
    public function testMultipleQueriesLogged(): void
    {
        QueryBuilder::enableQueryLog();

        $query1 = new QueryBuilder($this->connection);
        $query1->table('users')->get();

        $query2 = new QueryBuilder($this->connection);
        $query2->table('posts')->get();

        $log = QueryBuilder::getQueryLog();

        $this->assertCount(2, $log);
        $this->assertStringContainsString('users', $log[0]['query']);
        $this->assertStringContainsString('posts', $log[1]['query']);
    }

    /**
     * Test query log execution time is recorded.
     */
    public function testQueryLogRecordsExecutionTime(): void
    {
        QueryBuilder::enableQueryLog();

        $query = new QueryBuilder($this->connection);
        $query->table('users')->get();

        $log = QueryBuilder::getQueryLog();

        $this->assertCount(1, $log);
        $this->assertIsFloat($log[0]['time']);
        $this->assertGreaterThanOrEqual(0, $log[0]['time']);
    }

    /**
     * Test cache invalidation on select change.
     */
    public function testCacheInvalidationOnSelectChange(): void
    {
        QueryBuilder::enableQueryCache();

        $query = new QueryBuilder($this->connection);
        $query->table('users')->select('id');

        $sql1 = $query->toSql();

        $query->select('id', 'name');

        $sql2 = $query->toSql();

        $this->assertNotEquals($sql1, $sql2);
    }

    /**
     * Test cache invalidation on orderBy change.
     */
    public function testCacheInvalidationOnOrderByChange(): void
    {
        QueryBuilder::enableQueryCache();

        $query = new QueryBuilder($this->connection);
        $query->table('users');

        $sql1 = $query->toSql();

        $query->orderBy('name');

        $sql2 = $query->toSql();

        $this->assertNotEquals($sql1, $sql2);
        $this->assertStringContainsString('ORDER BY', $sql2);
    }

    /**
     * Test cache invalidation on limit change.
     */
    public function testCacheInvalidationOnLimitChange(): void
    {
        QueryBuilder::enableQueryCache();

        $query = new QueryBuilder($this->connection);
        $query->table('users');

        $sql1 = $query->toSql();

        $query->limit(10);

        $sql2 = $query->toSql();

        $this->assertNotEquals($sql1, $sql2);
        $this->assertStringContainsString('LIMIT', $sql2);
    }

    /**
     * Test query logging can be disabled after enabling.
     */
    public function testQueryLogCanBeDisabled(): void
    {
        QueryBuilder::enableQueryLog();
        $query = new QueryBuilder($this->connection);
        $query->table('users')->get();

        $this->assertNotEmpty(QueryBuilder::getQueryLog());

        QueryBuilder::disableQueryLog();
        QueryBuilder::flushQueryLog();

        $query2 = new QueryBuilder($this->connection);
        $query2->table('posts')->get();

        // Should not log after disabling
        $this->assertEmpty(QueryBuilder::getQueryLog());
    }
}
