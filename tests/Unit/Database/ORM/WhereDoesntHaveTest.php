<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\ModelQueryBuilder;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\Connection;
use Toporia\Framework\Database\ORM\Relations\HasMany;
use Toporia\Framework\Database\ORM\Relations\BelongsToMany;
use Mockery;

/**
 * Test suite for whereDoesntHave and related functionality.
 *
 * Tests all the new whereDoesntHave methods added to the ORM:
 * - whereDoesntHave()
 * - orWhereDoesntHave()
 * - whereDoesntHaveNested()
 * - whereDoesntHaveIn()
 * - whereDoesntHaveInDateRange()
 * - whereDoesntHaveJsonAttribute()
 */
class WhereDoesntHaveTest extends TestCase
{
    private $connection;
    private $modelQueryBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the database connection
        $this->connection = Mockery::mock(Connection::class);
        $this->connection->shouldReceive('getPdo')->andReturn(Mockery::mock(\PDO::class));

        // Create a test model query builder
        $this->modelQueryBuilder = new ModelQueryBuilder($this->connection, TestUserModel::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testWhereDoesntHaveBasic(): void
    {
        // Test basic whereDoesntHave without callback
        $query = $this->modelQueryBuilder->whereDoesntHave('posts');

        $this->assertInstanceOf(ModelQueryBuilder::class, $query);

        // Verify the query contains NOT EXISTS logic
        $sql = $query->toSql();
        $this->assertStringContainsString('SELECT COUNT(*)', $sql);
        $this->assertStringContainsString('< ?', $sql);
    }

    public function testWhereDoesntHaveWithCallback(): void
    {
        // Test whereDoesntHave with callback constraints
        $query = $this->modelQueryBuilder->whereDoesntHave('posts', function ($q) {
            $q->where('published', true);
        });

        $this->assertInstanceOf(ModelQueryBuilder::class, $query);

        $sql = $query->toSql();
        $this->assertStringContainsString('SELECT COUNT(*)', $sql);
    }

    public function testWhereDoesntHaveWithCountOperator(): void
    {
        // Test whereDoesntHave with custom count and operator
        $query = $this->modelQueryBuilder->whereDoesntHave('posts', null, '<', 5);

        $this->assertInstanceOf(ModelQueryBuilder::class, $query);

        $bindings = $query->getBindings();
        $this->assertContains(5, $bindings);
    }

    public function testOrWhereDoesntHave(): void
    {
        // Test OR version of whereDoesntHave
        $query = $this->modelQueryBuilder
            ->where('active', true)
            ->orWhereDoesntHave('posts');

        $this->assertInstanceOf(ModelQueryBuilder::class, $query);

        $sql = $query->toSql();
        $this->assertStringContainsString('OR', $sql);
    }

    public function testWhereDoesntHaveNested(): void
    {
        // Test nested relationship filtering
        $query = $this->modelQueryBuilder->whereDoesntHaveNested('posts.comments');

        $this->assertInstanceOf(ModelQueryBuilder::class, $query);

        // Should handle dot notation properly
        $sql = $query->toSql();
        $this->assertStringContainsString('SELECT COUNT(*)', $sql);
    }

    public function testWhereDoesntHaveIn(): void
    {
        // Test ID-based filtering
        $query = $this->modelQueryBuilder->whereDoesntHaveIn('posts', [1, 2, 3, 4, 5], 'user_id');

        $this->assertInstanceOf(ModelQueryBuilder::class, $query);

        $bindings = $query->getBindings();
        $this->assertContains(1, $bindings);
        $this->assertContains(5, $bindings);
    }

    public function testWhereDoesntHaveInDateRange(): void
    {
        // Test date range filtering
        $startDate = '2024-01-01';
        $endDate = '2024-12-31';

        $query = $this->modelQueryBuilder->whereDoesntHaveInDateRange('posts', 'created_at', $startDate, $endDate);

        $this->assertInstanceOf(ModelQueryBuilder::class, $query);

        $bindings = $query->getBindings();
        $this->assertContains($startDate, $bindings);
        $this->assertContains($endDate, $bindings);
    }

    public function testWhereDoesntHaveJsonAttribute(): void
    {
        // Test JSON attribute filtering
        $query = $this->modelQueryBuilder->whereDoesntHaveJsonAttribute('posts', 'metadata', '$.source', 'mobile');

        $this->assertInstanceOf(ModelQueryBuilder::class, $query);

        $bindings = $query->getBindings();
        $this->assertContains('mobile', $bindings);
    }

    public function testStaticWhereDoesntHaveMethods(): void
    {
        // Test static convenience methods
        $query1 = TestUserModel::whereDoesntHave('posts');
        $query2 = TestUserModel::whereDoesntHaveNested('posts.comments');
        $query3 = TestUserModel::whereDoesntHaveIn('roles', [1, 2, 3]);

        $this->assertInstanceOf(ModelQueryBuilder::class, $query1);
        $this->assertInstanceOf(ModelQueryBuilder::class, $query2);
        $this->assertInstanceOf(ModelQueryBuilder::class, $query3);
    }

    public function testPerformanceOptimizations(): void
    {
        // Test performance optimization methods
        $query = $this->modelQueryBuilder
            ->whereDoesntHave('posts')
            ->addQueryHint('index', ['idx_user_id'])
            ->optimizeForLargeResults(true);

        $this->assertInstanceOf(ModelQueryBuilder::class, $query);
    }

    public function testRelationshipCaching(): void
    {
        // Test relationship caching functionality
        QueryBuilder::enableRelationshipCaching(500);

        $stats = QueryBuilder::getRelationshipCacheStats();
        $this->assertTrue($stats['enabled']);
        $this->assertEquals(500, $stats['max_size']);

        QueryBuilder::disableRelationshipCaching();

        $stats = QueryBuilder::getRelationshipCacheStats();
        $this->assertFalse($stats['enabled']);
    }

    public function testQueryLogging(): void
    {
        // Test query logging functionality
        QueryBuilder::enableQueryLog();
        QueryBuilder::logQuery('SELECT * FROM users', [], 1.5);

        $log = QueryBuilder::getQueryLog();
        $this->assertCount(1, $log);
        $this->assertEquals('SELECT * FROM users', $log[0]['query']);
        $this->assertEquals(1.5, $log[0]['time']);

        QueryBuilder::clearQueryLog();
        $this->assertEmpty(QueryBuilder::getQueryLog());

        QueryBuilder::disableQueryLog();
    }

    public function testInvalidRelationshipThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Relationship 'nonexistent' does not exist");

        $this->modelQueryBuilder->whereDoesntHave('nonexistent');
    }

    public function testEmptyIdsArraySkipsQuery(): void
    {
        // Test that empty IDs array doesn't modify query
        $originalSql = $this->modelQueryBuilder->toSql();
        $query = $this->modelQueryBuilder->whereDoesntHaveIn('posts', []);

        $this->assertEquals($originalSql, $query->toSql());
    }
}

/**
 * Test model for unit testing.
 */
class TestUserModel extends Model
{
    protected static string $table = 'users';

    protected static array $fillable = ['name', 'email', 'active'];

    public function posts()
    {
        return $this->hasMany(TestPostModel::class, 'user_id');
    }

    public function roles()
    {
        return $this->belongsToMany(TestRoleModel::class, 'user_roles', 'user_id', 'role_id');
    }
}

/**
 * Test post model for relationships.
 */
class TestPostModel extends Model
{
    protected static string $table = 'posts';

    protected static array $fillable = ['title', 'content', 'published', 'user_id'];

    public function user()
    {
        return $this->belongsTo(TestUserModel::class, 'user_id');
    }

    public function comments()
    {
        return $this->hasMany(TestCommentModel::class, 'post_id');
    }
}

/**
 * Test comment model for nested relationships.
 */
class TestCommentModel extends Model
{
    protected static string $table = 'comments';

    protected static array $fillable = ['content', 'post_id'];

    public function post()
    {
        return $this->belongsTo(TestPostModel::class, 'post_id');
    }
}

/**
 * Test role model for many-to-many relationships.
 */
class TestRoleModel extends Model
{
    protected static string $table = 'roles';

    protected static array $fillable = ['name'];

    public function users()
    {
        return $this->belongsToMany(TestUserModel::class, 'user_roles', 'role_id', 'user_id');
    }
}

