<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\Concerns\HasQueryScopes;
use Toporia\Framework\Database\Query\QueryBuilder;

/**
 * Test HasQueryScopes
 *
 * Comprehensive tests for query scopes functionality:
 * - Local scopes (discovered automatically)
 * - Dynamic scopes (with parameters)
 * - Global scopes (applied automatically)
 * - Scope chaining
 * - Scope removal
 * - Multiple scopes
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class HasQueryScopesTest extends DatabaseTestCase
{
    protected function createTables(): void
    {
        // Create users table
        $this->createTable('users', "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                active INT DEFAULT 1,
                published INT DEFAULT 0,
                age INT,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        ");
    }

    protected function dropTables(): void
    {
        $this->dropTable('users');
    }

    protected function tearDown(): void
    {
        // Clean up scopes
        ScopeTestUser::removeGlobalScope('active');
        ScopeTestUser::removeGlobalScope('published');
        parent::tearDown();
    }

    /**
     * Test local scope is discovered automatically
     */
    public function test_local_scope_discovered_automatically(): void
    {
        $this->assertTrue(ScopeTestUser::hasLocalScope('active'));
        $this->assertTrue(ScopeTestUser::hasLocalScope('published'));
        $this->assertTrue(ScopeTestUser::hasLocalScope('olderThan'));
    }

    /**
     * Test local scope applies correctly
     */
    public function test_local_scope_applies(): void
    {
        // Create test data
        $this->executeQuery("INSERT INTO users (name, email, active) VALUES (?, ?, ?)", ['Active User', 'active@example.com', 1]);
        $this->executeQuery("INSERT INTO users (name, email, active) VALUES (?, ?, ?)", ['Inactive User', 'inactive@example.com', 0]);

        // Apply scope
        $users = ScopeTestUser::query()->where(function($query) {
            ScopeTestUser::applyLocalScope($query, 'active');
        })->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Active User', $users->first()->name);
    }

    /**
     * Test local scope can be called directly
     */
    public function test_local_scope_can_be_called_directly(): void
    {
        // Create test data
        $this->executeQuery("INSERT INTO users (name, email, published) VALUES (?, ?, ?)", ['Published User', 'pub@example.com', 1]);
        $this->executeQuery("INSERT INTO users (name, email, published) VALUES (?, ?, ?)", ['Unpublished User', 'unpub@example.com', 0]);

        // Note: In real usage, scopes are called via query builder magic methods
        // Here we test the scope application directly
        $query = ScopeTestUser::query();
        ScopeTestUser::applyLocalScope($query, 'published');
        $users = $query->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Published User', $users->first()->name);
    }

    /**
     * Test dynamic scope with parameters
     */
    public function test_dynamic_scope_with_parameters(): void
    {
        // Create test data
        $this->executeQuery("INSERT INTO users (name, email, age) VALUES (?, ?, ?)", ['Young User', 'young@example.com', 20]);
        $this->executeQuery("INSERT INTO users (name, email, age) VALUES (?, ?, ?)", ['Old User', 'old@example.com', 50]);

        // Apply dynamic scope
        $query = ScopeTestUser::query();
        ScopeTestUser::applyLocalScope($query, 'olderThan', 30);
        $users = $query->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Old User', $users->first()->name);
    }

    /**
     * Test dynamic scope with multiple parameters
     */
    public function test_dynamic_scope_with_multiple_parameters(): void
    {
        // Create test data
        $this->executeQuery("INSERT INTO users (name, email, age) VALUES (?, ?, ?)", ['User1', 'user1@example.com', 25]);
        $this->executeQuery("INSERT INTO users (name, email, age) VALUES (?, ?, ?)", ['User2', 'user2@example.com', 35]);
        $this->executeQuery("INSERT INTO users (name, email, age) VALUES (?, ?, ?)", ['User3', 'user3@example.com', 45]);

        // Apply dynamic scope with age range
        $query = ScopeTestUser::query();
        ScopeTestUser::applyLocalScope($query, 'ageBetween', 30, 40);
        $users = $query->get();

        $this->assertCount(1, $users);
        $this->assertEquals('User2', $users->first()->name);
    }

    /**
     * Test global scope applies automatically
     */
    public function test_global_scope_applies_automatically(): void
    {
        // Add global scope
        ScopeTestUser::addGlobalScope('active', function(QueryBuilder $query) {
            $query->where('active', 1);
        });

        // Create test data
        $this->executeQuery("INSERT INTO users (name, email, active) VALUES (?, ?, ?)", ['Active User', 'active@example.com', 1]);
        $this->executeQuery("INSERT INTO users (name, email, active) VALUES (?, ?, ?)", ['Inactive User', 'inactive@example.com', 0]);

        // Query should automatically apply global scope
        $users = ScopeTestUser::query()->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Active User', $users->first()->name);
    }

    /**
     * Test global scope can be removed
     */
    public function test_global_scope_can_be_removed(): void
    {
        // Add global scope
        ScopeTestUser::addGlobalScope('active', function(QueryBuilder $query) {
            $query->where('active', 1);
        });

        // Create test data
        $this->executeQuery("INSERT INTO users (name, email, active) VALUES (?, ?, ?)", ['Active User', 'active@example.com', 1]);
        $this->executeQuery("INSERT INTO users (name, email, active) VALUES (?, ?, ?)", ['Inactive User', 'inactive@example.com', 0]);

        // Remove global scope
        ScopeTestUser::removeGlobalScope('active');

        // Query should return all users
        $users = ScopeTestUser::query()->get();

        $this->assertCount(2, $users);
    }

    /**
     * Test global scope exists check
     */
    public function test_global_scope_exists_check(): void
    {
        $this->assertFalse(ScopeTestUser::hasGlobalScope('active'));

        ScopeTestUser::addGlobalScope('active', function(QueryBuilder $query) {
            $query->where('active', 1);
        });

        $this->assertTrue(ScopeTestUser::hasGlobalScope('active'));

        ScopeTestUser::removeGlobalScope('active');

        $this->assertFalse(ScopeTestUser::hasGlobalScope('active'));
    }

    /**
     * Test multiple global scopes
     */
    public function test_multiple_global_scopes(): void
    {
        // Add multiple global scopes
        ScopeTestUser::addGlobalScope('active', function(QueryBuilder $query) {
            $query->where('active', 1);
        });
        ScopeTestUser::addGlobalScope('published', function(QueryBuilder $query) {
            $query->where('published', 1);
        });

        // Create test data
        $this->executeQuery("INSERT INTO users (name, email, active, published) VALUES (?, ?, ?, ?)",
            ['User1', 'user1@example.com', 1, 1]);
        $this->executeQuery("INSERT INTO users (name, email, active, published) VALUES (?, ?, ?, ?)",
            ['User2', 'user2@example.com', 1, 0]);
        $this->executeQuery("INSERT INTO users (name, email, active, published) VALUES (?, ?, ?, ?)",
            ['User3', 'user3@example.com', 0, 1]);

        // Query should apply both scopes
        $users = ScopeTestUser::query()->get();

        $this->assertCount(1, $users);
        $this->assertEquals('User1', $users->first()->name);
    }

    /**
     * Test local scope chaining
     */
    public function test_local_scope_chaining(): void
    {
        // Create test data
        $this->executeQuery("INSERT INTO users (name, email, active, published) VALUES (?, ?, ?, ?)",
            ['User1', 'user1@example.com', 1, 1]);
        $this->executeQuery("INSERT INTO users (name, email, active, published) VALUES (?, ?, ?, ?)",
            ['User2', 'user2@example.com', 1, 0]);

        // Chain scopes
        $query = ScopeTestUser::query();
        ScopeTestUser::applyLocalScope($query, 'active');
        ScopeTestUser::applyLocalScope($query, 'published');
        $users = $query->get();

        $this->assertCount(1, $users);
        $this->assertEquals('User1', $users->first()->name);
    }

    /**
     * Test scope with local scope and global scope
     */
    public function test_scope_with_local_and_global(): void
    {
        // Add global scope
        ScopeTestUser::addGlobalScope('active', function(QueryBuilder $query) {
            $query->where('active', 1);
        });

        // Create test data
        $this->executeQuery("INSERT INTO users (name, email, active, published) VALUES (?, ?, ?, ?)",
            ['User1', 'user1@example.com', 1, 1]);
        $this->executeQuery("INSERT INTO users (name, email, active, published) VALUES (?, ?, ?, ?)",
            ['User2', 'user2@example.com', 1, 0]);

        // Apply local scope on top of global scope
        $query = ScopeTestUser::query();
        ScopeTestUser::applyLocalScope($query, 'published');
        $users = $query->get();

        $this->assertCount(1, $users);
        $this->assertEquals('User1', $users->first()->name);
    }

    /**
     * Test addLocalScope manually
     */
    public function test_add_local_scope_manually(): void
    {
        // Add local scope manually
        ScopeTestUser::addLocalScope('testScope', function(QueryBuilder $query) {
            $query->where('name', 'LIKE', '%Test%');
        });

        $this->assertTrue(ScopeTestUser::hasLocalScope('testScope'));

        // Create test data
        $this->executeQuery("INSERT INTO users (name, email) VALUES (?, ?)", ['Test User', 'test@example.com']);
        $this->executeQuery("INSERT INTO users (name, email) VALUES (?, ?)", ['Other User', 'other@example.com']);

        // Apply scope
        $query = ScopeTestUser::query();
        ScopeTestUser::applyLocalScope($query, 'testScope');
        $users = $query->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Test User', $users->first()->name);
    }

    /**
     * Test applyLocalScope throws exception for non-existent scope
     */
    public function test_apply_local_scope_throws_for_non_existent(): void
    {
        $query = ScopeTestUser::query();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Local scope 'nonExistent' does not exist");

        ScopeTestUser::applyLocalScope($query, 'nonExistent');
    }

    /**
     * Test getLocalScopes returns all scopes
     */
    public function test_get_local_scopes_returns_all(): void
    {
        $scopes = ScopeTestUser::getLocalScopes();

        $this->assertArrayHasKey('active', $scopes);
        $this->assertArrayHasKey('published', $scopes);
        $this->assertArrayHasKey('olderThan', $scopes);
        $this->assertArrayHasKey('ageBetween', $scopes);
    }

    /**
     * Test getGlobalScopes returns all scopes
     */
    public function test_get_global_scopes_returns_all(): void
    {
        ScopeTestUser::addGlobalScope('test1', function(QueryBuilder $query) {});
        ScopeTestUser::addGlobalScope('test2', function(QueryBuilder $query) {});

        $scopes = ScopeTestUser::getGlobalScopes();

        $this->assertArrayHasKey('test1', $scopes);
        $this->assertArrayHasKey('test2', $scopes);

        // Clean up
        ScopeTestUser::removeGlobalScope('test1');
        ScopeTestUser::removeGlobalScope('test2');
    }

    /**
     * Test scope isolation between models
     */
    public function test_scope_isolation_between_models(): void
    {
        // Add global scope to first model
        ScopeTestUser::addGlobalScope('active', function(QueryBuilder $query) {
            $query->where('active', 1);
        });

        // Second model should not have this scope
        $this->assertFalse(AnotherScopeTestModel::hasGlobalScope('active'));

        // Clean up
        ScopeTestUser::removeGlobalScope('active');
    }

    /**
     * Test scope with complex conditions
     */
    public function test_scope_with_complex_conditions(): void
    {
        // Create test data
        $this->executeQuery("INSERT INTO users (name, email, age, active) VALUES (?, ?, ?, ?)",
            ['User1', 'user1@example.com', 25, 1]);
        $this->executeQuery("INSERT INTO users (name, email, age, active) VALUES (?, ?, ?, ?)",
            ['User2', 'user2@example.com', 35, 1]);
        $this->executeQuery("INSERT INTO users (name, email, age, active) VALUES (?, ?, ?, ?)",
            ['User3', 'user3@example.com', 45, 0]);

        // Apply scope with complex condition
        $query = ScopeTestUser::query();
        ScopeTestUser::applyLocalScope($query, 'olderThan', 30);
        ScopeTestUser::applyLocalScope($query, 'active');
        $users = $query->get();

        $this->assertCount(1, $users);
        $this->assertEquals('User2', $users->first()->name);
    }

    /**
     * Test scope with ordering
     */
    public function test_scope_with_ordering(): void
    {
        // Create test data
        $this->executeQuery("INSERT INTO users (name, email, age) VALUES (?, ?, ?)", ['User1', 'user1@example.com', 25]);
        $this->executeQuery("INSERT INTO users (name, email, age) VALUES (?, ?, ?)", ['User2', 'user2@example.com', 35]);
        $this->executeQuery("INSERT INTO users (name, email, age) VALUES (?, ?, ?)", ['User3', 'user3@example.com', 45]);

        // Apply scope with ordering
        $query = ScopeTestUser::query();
        ScopeTestUser::applyLocalScope($query, 'olderThan', 20);
        $query->orderBy('age', 'ASC');
        $users = $query->get();

        $this->assertCount(3, $users);
        $this->assertEquals('User1', $users->first()->name);
        $this->assertEquals('User3', $users->last()->name);
    }

    /**
     * Test applyGlobalScopes method
     */
    public function test_apply_global_scopes_method(): void
    {
        // Add global scopes
        ScopeTestUser::addGlobalScope('active', function(QueryBuilder $query) {
            $query->where('active', 1);
        });
        ScopeTestUser::addGlobalScope('published', function(QueryBuilder $query) {
            $query->where('published', 1);
        });

        // Create test data
        $this->executeQuery("INSERT INTO users (name, email, active, published) VALUES (?, ?, ?, ?)",
            ['User1', 'user1@example.com', 1, 1]);
        $this->executeQuery("INSERT INTO users (name, email, active, published) VALUES (?, ?, ?, ?)",
            ['User2', 'user2@example.com', 1, 0]);

        // Apply global scopes manually
        $query = ScopeTestUser::query();
        ScopeTestUser::applyGlobalScopes($query);
        $users = $query->get();

        $this->assertCount(1, $users);
        $this->assertEquals('User1', $users->first()->name);
    }
}

/**
 * Test model with HasQueryScopes trait
 */
class ScopeTestUser extends Model
{
    use HasQueryScopes;
use Toporia\Framework\Database\ORM\ModelQueryBuilder;

    protected static string $table = 'users';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['name', 'email', 'active', 'published', 'age'];

    /**
     * Local scope: active users
     */
    protected static function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('active', 1);
    }

    /**
     * Local scope: published users
     */
    protected static function scopePublished(QueryBuilder $query): QueryBuilder
    {
        return $query->where('published', 1);
    }

    /**
     * Dynamic scope: older than age
     */
    protected static function scopeOlderThan(QueryBuilder $query, int $age): QueryBuilder
    {
        return $query->where('age', '>', $age);
    }

    /**
     * Dynamic scope: age between min and max
     */
    protected static function scopeAgeBetween(QueryBuilder $query, int $min, int $max): QueryBuilder
    {
        return $query->where('age', '>=', $min)->where('age', '<=', $max);
    }

    public static function query(): ModelQueryBuilder
    {
        $query = parent::query();
        // Apply global scopes
        static::applyGlobalScopes($query);
        return $query;
    }

    protected static function getConnection(): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        return parent::getConnection();
    }
}

/**
 * Another test model for isolation testing
 */
class AnotherScopeTestModel extends Model
{
    use HasQueryScopes;
use Toporia\Framework\Database\ORM\ModelQueryBuilder;

    protected static string $table = 'another_scope_models';
    protected static bool $timestamps = false;
}


