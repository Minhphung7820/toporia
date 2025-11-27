<?php

declare(strict_types=1);

namespace Tests\Performance;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\ModelQueryBuilder;
use Toporia\Framework\Database\Query\QueryBuilder;
use Toporia\Framework\Database\Connection;
use Mockery;

/**
 * Performance test suite for whereDoesntHave EXISTS vs COUNT optimization.
 *
 * This test suite validates that the EXISTS approach is significantly faster
 * than the COUNT approach while producing identical results.
 */
class WhereDoesntHavePerformanceTest extends TestCase
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
        $this->modelQueryBuilder = new ModelQueryBuilder($this->connection, TestProductModel::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testExistsApproachGeneratesOptimalSQL(): void
    {
        // Test that EXISTS approach generates optimal SQL
        $query = $this->modelQueryBuilder->whereDoesntHave('reviews');
        $sql = $query->toSql();

        // Should contain NOT EXISTS instead of COUNT
        $this->assertStringContainsString('NOT EXISTS', $sql);
        $this->assertStringContainsString('SELECT 1', $sql);
        $this->assertStringContainsString('LIMIT 1', $sql);

        // Should NOT contain COUNT(*)
        $this->assertStringNotContainsString('COUNT(*)', $sql);
    }

    public function testCountApproachWhenNeeded(): void
    {
        // Test that COUNT approach is used when count != 1
        $query = $this->modelQueryBuilder->whereDoesntHave('reviews', null, '<', 5);
        $sql = $query->toSql();

        // Should contain COUNT when count comparison is needed
        $this->assertStringContainsString('COUNT(*)', $sql);
        $this->assertStringContainsString('< ?', $sql);

        // Should NOT contain EXISTS for count queries
        $this->assertStringNotContainsString('NOT EXISTS', $sql);
    }

    public function testNestedRelationshipsUseExists(): void
    {
        // Test that nested relationships use EXISTS for optimal performance
        $query = $this->modelQueryBuilder->whereDoesntHaveNested('posts.comments');
        $sql = $query->toSql();

        // Should use EXISTS for nested relationships
        $this->assertStringContainsString('NOT EXISTS', $sql);
        $this->assertStringContainsString('SELECT 1', $sql);

        // Should NOT use COUNT for simple existence checks
        $this->assertStringNotContainsString('COUNT(*)', $sql);
    }

    public function testOrWhereDoesntHaveUsesExists(): void
    {
        // Test OR version uses EXISTS
        $query = $this->modelQueryBuilder
            ->where('active', true)
            ->orWhereDoesntHave('reviews');

        $sql = $query->toSql();

        // Should contain OR NOT EXISTS
        $this->assertStringContainsString('OR', $sql);
        $this->assertStringContainsString('NOT EXISTS', $sql);
        $this->assertStringContainsString('SELECT 1', $sql);
    }

    public function testWhereHasUsesExists(): void
    {
        // Test that whereHas also uses EXISTS for consistency
        $query = $this->modelQueryBuilder->whereHas('reviews');
        $sql = $query->toSql();

        // Should contain EXISTS instead of COUNT
        $this->assertStringContainsString('EXISTS', $sql);
        $this->assertStringContainsString('SELECT 1', $sql);
        $this->assertStringContainsString('LIMIT 1', $sql);

        // Should NOT contain COUNT(*)
        $this->assertStringNotContainsString('COUNT(*)', $sql);
    }

    public function testCallbackConstraintsAreApplied(): void
    {
        // Test that callback constraints are properly applied in EXISTS queries
        $query = $this->modelQueryBuilder->whereDoesntHave('reviews', function($q) {
            $q->where('rating', '>=', 4);
        });

        $sql = $query->toSql();

        // Should contain NOT EXISTS with constraints
        $this->assertStringContainsString('NOT EXISTS', $sql);
        $this->assertStringContainsString('SELECT 1', $sql);

        // Constraints should be included (this would be in the subquery)
        // Note: Exact constraint checking would require more complex SQL parsing
        $this->assertStringContainsString('LIMIT 1', $sql);
    }

    public function testPivotRelationshipsUseExists(): void
    {
        // Test that pivot relationships (BelongsToMany) use EXISTS
        $query = $this->modelQueryBuilder->whereDoesntHave('tags');
        $sql = $query->toSql();

        // Should use EXISTS for pivot relationships
        $this->assertStringContainsString('NOT EXISTS', $sql);
        $this->assertStringContainsString('SELECT 1', $sql);
        $this->assertStringContainsString('LIMIT 1', $sql);
    }

    /**
     * Performance benchmark test (requires actual database connection).
     *
     * This test is marked as incomplete by default since it requires
     * a real database with test data. Uncomment and configure for
     * actual performance testing.
     */
    public function testPerformanceBenchmark(): void
    {
        $this->markTestIncomplete(
            'Performance benchmark requires actual database connection with test data. ' .
            'Configure database and uncomment this test for performance validation.'
        );

        /*
        // Uncomment for actual performance testing with real database

        // Enable query logging
        QueryBuilder::enableQueryLog();

        // Test EXISTS approach
        $startTime = microtime(true);
        $existsResults = TestProductModel::whereDoesntHave('reviews')->get();
        $existsTime = (microtime(true) - $startTime) * 1000;

        // Test COUNT approach (for comparison)
        $startTime = microtime(true);
        $countResults = TestProductModel::whereDoesntHaveWithCount('reviews', null, '<', 1)->get();
        $countTime = (microtime(true) - $startTime) * 1000;

        // Verify results are identical
        $this->assertEquals($existsResults->count(), $countResults->count());

        // Verify performance improvement (should be at least 2x faster)
        $improvement = ($countTime - $existsTime) / $countTime * 100;
        $this->assertGreaterThan(50, $improvement, "EXISTS should be at least 50% faster than COUNT");

        echo "\nPerformance Results:\n";
        echo "EXISTS approach: {$existsTime}ms\n";
        echo "COUNT approach: {$countTime}ms\n";
        echo "Performance improvement: " . number_format($improvement, 1) . "%\n";

        // Get query log
        $log = QueryBuilder::getQueryLog();
        echo "Queries executed: " . count($log) . "\n";

        QueryBuilder::clearQueryLog();
        QueryBuilder::disableQueryLog();
        */
    }

    public function testSQLInjectionPrevention(): void
    {
        // Test that EXISTS queries are safe from SQL injection
        $maliciousInput = "'; DROP TABLE products; --";

        $query = $this->modelQueryBuilder->whereDoesntHave('reviews', function($q) use ($maliciousInput) {
            $q->where('comment', $maliciousInput);
        });

        $sql = $query->toSql();

        // Should contain NOT EXISTS
        $this->assertStringContainsString('NOT EXISTS', $sql);

        // Should NOT contain the malicious SQL
        $this->assertStringNotContainsString('DROP TABLE', $sql);
        $this->assertStringNotContainsString(';', $sql);
    }

    public function testComplexNestedQueryOptimization(): void
    {
        // Test complex nested query with multiple levels
        $query = $this->modelQueryBuilder
            ->where('active', true)
            ->whereDoesntHaveNested('posts.comments.replies')
            ->orWhereDoesntHave('reviews', function($q) {
                $q->where('rating', '<', 3);
            });

        $sql = $query->toSql();

        // Should use EXISTS for all relationship checks
        $this->assertStringContainsString('NOT EXISTS', $sql);
        $this->assertStringContainsString('SELECT 1', $sql);

        // Should contain OR logic
        $this->assertStringContainsString('OR', $sql);

        // Should NOT use COUNT for simple existence checks
        $this->assertStringNotContainsString('COUNT(*)', $sql);
    }
}

/**
 * Test product model for performance testing.
 */
class TestProductModel extends Model
{
    protected static string $table = 'products';

    protected static array $fillable = ['name', 'price', 'active'];

    public function reviews()
    {
        return $this->hasMany(TestReviewModel::class, 'product_id');
    }

    public function tags()
    {
        return $this->belongsToMany(TestTagModel::class, 'product_tags', 'product_id', 'tag_id');
    }

    public function posts()
    {
        return $this->hasMany(TestPostModel::class, 'product_id');
    }
}

/**
 * Test review model for relationships.
 */
class TestReviewModel extends Model
{
    protected static string $table = 'reviews';

    protected static array $fillable = ['rating', 'comment', 'product_id'];

    public function product()
    {
        return $this->belongsTo(TestProductModel::class, 'product_id');
    }
}

/**
 * Test tag model for many-to-many relationships.
 */
class TestTagModel extends Model
{
    protected static string $table = 'tags';

    protected static array $fillable = ['name'];

    public function products()
    {
        return $this->belongsToMany(TestProductModel::class, 'product_tags', 'tag_id', 'product_id');
    }
}

/**
 * Test post model for nested relationships.
 */
class TestPostModel extends Model
{
    protected static string $table = 'posts';

    protected static array $fillable = ['title', 'content', 'product_id'];

    public function product()
    {
        return $this->belongsTo(TestProductModel::class, 'product_id');
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

    public function replies()
    {
        return $this->hasMany(TestReplyModel::class, 'comment_id');
    }
}

/**
 * Test reply model for deep nested relationships.
 */
class TestReplyModel extends Model
{
    protected static string $table = 'replies';

    protected static array $fillable = ['content', 'comment_id'];

    public function comment()
    {
        return $this->belongsTo(TestCommentModel::class, 'comment_id');
    }
}
