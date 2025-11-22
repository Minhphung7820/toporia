<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\Query\QueryBuilder;

/**
 * Test Model Global Scopes
 *
 * Tests global scope functionality:
 * - Closure-based scopes
 * - Object-based scopes
 * - Scope registration and retrieval
 * - Removing scopes (withoutGlobalScope, withoutGlobalScopes)
 * - Scope isolation between models
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class ModelGlobalScopesTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up global scopes after each test
        TestScopeModel::flushGlobalScopes();
        AnotherTestScopeModel::flushGlobalScopes();
    }

    /**
     * Test adding closure-based global scope
     */
    public function test_add_closure_based_scope(): void
    {
        TestScopeModel::addGlobalScope('published', function($query) {
            $query->where('published', true);
        });

        $this->assertTrue(TestScopeModel::hasGlobalScope('published'));
    }

    /**
     * Test adding object-based global scope
     */
    public function test_add_object_based_scope(): void
    {
        $scope = new PublishedScope();
        TestScopeModel::addGlobalScope($scope);

        $scopeName = PublishedScope::class;
        $this->assertTrue(TestScopeModel::hasGlobalScope($scopeName));
    }

    /**
     * Test hasGlobalScope returns false for non-existent scope
     */
    public function test_has_global_scope_returns_false_for_nonexistent(): void
    {
        $this->assertFalse(TestScopeModel::hasGlobalScope('nonexistent'));
    }

    /**
     * Test getGlobalScope retrieves registered scope
     */
    public function test_get_global_scope(): void
    {
        $callback = function($query) {
            $query->where('active', true);
        };

        TestScopeModel::addGlobalScope('active', $callback);

        $retrieved = TestScopeModel::getGlobalScope('active');
        $this->assertSame($callback, $retrieved);
    }

    /**
     * Test getGlobalScope returns null for non-existent scope
     */
    public function test_get_global_scope_returns_null_for_nonexistent(): void
    {
        $this->assertNull(TestScopeModel::getGlobalScope('nonexistent'));
    }

    /**
     * Test getGlobalScopes returns all registered scopes
     */
    public function test_get_global_scopes(): void
    {
        TestScopeModel::addGlobalScope('published', function($query) {});
        TestScopeModel::addGlobalScope('active', function($query) {});
        TestScopeModel::addGlobalScope('verified', function($query) {});

        $scopes = TestScopeModel::getGlobalScopes();

        $this->assertCount(3, $scopes);
        $this->assertArrayHasKey('published', $scopes);
        $this->assertArrayHasKey('active', $scopes);
        $this->assertArrayHasKey('verified', $scopes);
    }

    /**
     * Test flushGlobalScopes removes all scopes
     */
    public function test_flush_global_scopes(): void
    {
        TestScopeModel::addGlobalScope('published', function($query) {});
        TestScopeModel::addGlobalScope('active', function($query) {});

        $this->assertNotEmpty(TestScopeModel::getGlobalScopes());

        TestScopeModel::flushGlobalScopes();

        $this->assertEmpty(TestScopeModel::getGlobalScopes());
    }

    /**
     * Test withoutGlobalScope marks scope for removal
     */
    public function test_without_global_scope(): void
    {
        TestScopeModel::addGlobalScope('published', function($query) {});

        $model = new TestScopeModel();
        $result = $model->withoutGlobalScope('published');

        // Should return $this for chaining
        $this->assertSame($model, $result);

        // Check removedScopes property
        $reflection = new \ReflectionClass($model);
        $property = $reflection->getProperty('removedScopes');
        $property->setAccessible(true);
        $removedScopes = $property->getValue($model);

        $this->assertContains('published', $removedScopes);
    }

    /**
     * Test withoutGlobalScopes with specific scopes
     */
    public function test_without_global_scopes_specific(): void
    {
        TestScopeModel::addGlobalScope('published', function($query) {});
        TestScopeModel::addGlobalScope('active', function($query) {});

        $model = new TestScopeModel();
        $model->withoutGlobalScopes(['published', 'active']);

        $reflection = new \ReflectionClass($model);
        $property = $reflection->getProperty('removedScopes');
        $property->setAccessible(true);
        $removedScopes = $property->getValue($model);

        $this->assertContains('published', $removedScopes);
        $this->assertContains('active', $removedScopes);
    }

    /**
     * Test withoutGlobalScopes without arguments removes all scopes
     */
    public function test_without_global_scopes_all(): void
    {
        TestScopeModel::addGlobalScope('published', function($query) {});
        TestScopeModel::addGlobalScope('active', function($query) {});
        TestScopeModel::addGlobalScope('verified', function($query) {});

        $model = new TestScopeModel();
        $model->withoutGlobalScopes();

        $reflection = new \ReflectionClass($model);
        $property = $reflection->getProperty('removedScopes');
        $property->setAccessible(true);
        $removedScopes = $property->getValue($model);

        $this->assertCount(3, $removedScopes);
        $this->assertContains('published', $removedScopes);
        $this->assertContains('active', $removedScopes);
        $this->assertContains('verified', $removedScopes);
    }

    /**
     * Test withoutGlobalScopes returns $this for chaining
     */
    public function test_without_global_scopes_returns_this(): void
    {
        TestScopeModel::addGlobalScope('published', function($query) {});

        $model = new TestScopeModel();
        $result = $model->withoutGlobalScopes(['published']);

        $this->assertSame($model, $result);
    }

    /**
     * Test scope isolation between different models
     */
    public function test_scope_isolation_between_models(): void
    {
        TestScopeModel::addGlobalScope('published', function($query) {});
        AnotherTestScopeModel::addGlobalScope('active', function($query) {});

        $scopes1 = TestScopeModel::getGlobalScopes();
        $scopes2 = AnotherTestScopeModel::getGlobalScopes();

        $this->assertCount(1, $scopes1);
        $this->assertArrayHasKey('published', $scopes1);
        $this->assertArrayNotHasKey('active', $scopes1);

        $this->assertCount(1, $scopes2);
        $this->assertArrayHasKey('active', $scopes2);
        $this->assertArrayNotHasKey('published', $scopes2);
    }

    /**
     * Test multiple scopes can be registered
     */
    public function test_multiple_scopes_registration(): void
    {
        TestScopeModel::addGlobalScope('published', function($query) {
            $query->where('published', true);
        });

        TestScopeModel::addGlobalScope('active', function($query) {
            $query->where('active', true);
        });

        TestScopeModel::addGlobalScope('verified', function($query) {
            $query->where('verified', true);
        });

        $scopes = TestScopeModel::getGlobalScopes();
        $this->assertCount(3, $scopes);
    }

    /**
     * Test scope can be overwritten
     */
    public function test_scope_can_be_overwritten(): void
    {
        $callback1 = function($query) {
            $query->where('version', 1);
        };

        $callback2 = function($query) {
            $query->where('version', 2);
        };

        TestScopeModel::addGlobalScope('version', $callback1);
        TestScopeModel::addGlobalScope('version', $callback2);

        $retrieved = TestScopeModel::getGlobalScope('version');
        $this->assertSame($callback2, $retrieved);
    }

    /**
     * Test adding scope without implementation throws exception
     */
    public function test_add_scope_without_implementation_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Global scope implementation required when scope name is string');

        TestScopeModel::addGlobalScope('invalid');
    }

    /**
     * Test object scope uses class name as key
     */
    public function test_object_scope_uses_class_name_as_key(): void
    {
        $scope = new PublishedScope();
        TestScopeModel::addGlobalScope($scope);

        $retrieved = TestScopeModel::getGlobalScope(PublishedScope::class);
        $this->assertSame($scope, $retrieved);
    }

    /**
     * Test withGlobalScopes creates new instance
     */
    public function test_with_global_scopes_creates_new_instance(): void
    {
        $instance = TestScopeModel::withGlobalScopes();

        $this->assertInstanceOf(TestScopeModel::class, $instance);
    }
}

/**
 * Test model for global scopes
 */
class TestScopeModel extends Model
{
    protected static string $table = 'test_scope_models';
    protected static bool $timestamps = false;
}

/**
 * Another test model for isolation testing
 */
class AnotherTestScopeModel extends Model
{
    protected static string $table = 'another_test_scope_models';
    protected static bool $timestamps = false;
}

/**
 * Example global scope class
 */
class PublishedScope
{
    public function apply(QueryBuilder $query, Model $model): void
    {
        $query->where('published', true);
    }
}
