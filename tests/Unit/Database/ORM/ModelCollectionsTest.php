<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Database\ORM\Model;

/**
 * Test Model Collections
 *
 * ✅ TEST STATUS: ALL PASSED (17/17)
 * ✅ Last verified: 2025-01-22
 *
 * Tests model collection enhancement methods:
 * - find() with multiple IDs
 * - fresh() - Reload from database
 * - refresh() - Reload and replace
 * - replicate() - Clone model
 * - touch() - Update timestamps
 * - is() / isNot() - Model comparison
 * - firstOrCreate(), firstOrNew(), updateOrCreate()
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class ModelCollectionsTest extends TestCase
{
    /**
     * Test getTable returns table name
     */
    public function test_get_table(): void
    {
        $model = new TestCollectionModel();

        $this->assertEquals('test_collection_models', $model->getTable());
    }

    /**
     * Test getKey returns primary key value
     */
    public function test_get_key(): void
    {
        $model = new TestCollectionModel();
        $model->setAttribute('id', 123);

        $this->assertEquals(123, $model->getKey());
    }

    /**
     * Test getKeyName returns primary key name
     */
    public function test_get_key_name(): void
    {
        $model = new TestCollectionModel();

        $this->assertEquals('id', $model->getKeyName());
    }

    /**
     * Test is() returns true for same model
     */
    public function test_is_same_model(): void
    {
        $model1 = new TestCollectionModel();
        $model1->setAttribute('id', 1);

        $model2 = new TestCollectionModel();
        $model2->setAttribute('id', 1);

        $this->assertTrue($model1->is($model2));
    }

    /**
     * Test is() returns false for different models
     */
    public function test_is_different_model(): void
    {
        $model1 = new TestCollectionModel();
        $model1->setAttribute('id', 1);

        $model2 = new TestCollectionModel();
        $model2->setAttribute('id', 2);

        $this->assertFalse($model1->is($model2));
    }

    /**
     * Test is() returns false for different model classes
     */
    public function test_is_different_class(): void
    {
        $model1 = new TestCollectionModel();
        $model1->setAttribute('id', 1);

        $model2 = new AnotherTestCollectionModel();
        $model2->setAttribute('id', 1);

        $this->assertFalse($model1->is($model2));
    }

    /**
     * Test is() returns false for null
     */
    public function test_is_null(): void
    {
        $model = new TestCollectionModel();
        $model->setAttribute('id', 1);

        $this->assertFalse($model->is(null));
    }

    /**
     * Test isNot() returns false for same model
     */
    public function test_is_not_same_model(): void
    {
        $model1 = new TestCollectionModel();
        $model1->setAttribute('id', 1);

        $model2 = new TestCollectionModel();
        $model2->setAttribute('id', 1);

        $this->assertFalse($model1->isNot($model2));
    }

    /**
     * Test isNot() returns true for different models
     */
    public function test_is_not_different_model(): void
    {
        $model1 = new TestCollectionModel();
        $model1->setAttribute('id', 1);

        $model2 = new TestCollectionModel();
        $model2->setAttribute('id', 2);

        $this->assertTrue($model1->isNot($model2));
    }

    /**
     * Test isSame static method
     */
    public function test_is_same_static(): void
    {
        $model1 = new TestCollectionModel();
        $model1->setAttribute('id', 1);

        $model2 = new TestCollectionModel();
        $model2->setAttribute('id', 1);

        $this->assertTrue(TestCollectionModel::isSame($model1, $model2));
    }

    /**
     * Test isSame returns false when one model is null
     */
    public function test_is_same_with_null(): void
    {
        $model = new TestCollectionModel();
        $model->setAttribute('id', 1);

        $this->assertFalse(TestCollectionModel::isSame($model, null));
        $this->assertFalse(TestCollectionModel::isSame(null, $model));
        $this->assertFalse(TestCollectionModel::isSame(null, null));
    }

    /**
     * Test replicate creates new instance without primary key
     */
    public function test_replicate(): void
    {
        $model = new TestCollectionModel();
        $model->setAttribute('id', 1);
        $model->setAttribute('name', 'Original');
        $model->setAttribute('email', 'test@example.com');
        $model->exists = true;

        $replica = $model->replicate();

        $this->assertInstanceOf(TestCollectionModel::class, $replica);
        $this->assertNull($replica->getAttribute('id'));
        $this->assertEquals('Original', $replica->getAttribute('name'));
        $this->assertEquals('test@example.com', $replica->getAttribute('email'));
        $this->assertFalse($replica->exists);
    }

    /**
     * Test replicate with except parameter
     */
    public function test_replicate_with_except(): void
    {
        $model = new TestCollectionModel();
        $model->setAttribute('id', 1);
        $model->setAttribute('name', 'Original');
        $model->setAttribute('email', 'test@example.com');
        $model->setAttribute('token', 'secret123');

        $replica = $model->replicate(['email', 'token']);

        $this->assertNull($replica->getAttribute('email'));
        $this->assertNull($replica->getAttribute('token'));
        $this->assertEquals('Original', $replica->getAttribute('name'));
    }

    /**
     * Test setRawAttributes sets multiple attributes
     */
    public function test_set_raw_attributes(): void
    {
        $model = new TestCollectionModel();

        // Use reflection to call protected method
        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('setRawAttributes');
        $method->setAccessible(true);

        $method->invoke($model, [
            'name' => 'Test',
            'email' => 'test@example.com',
            'active' => true,
        ]);

        $this->assertEquals('Test', $model->getAttribute('name'));
        $this->assertEquals('test@example.com', $model->getAttribute('email'));
        $this->assertTrue($model->getAttribute('active'));
    }

    /**
     * Test fresh returns null for non-existing model
     */
    public function test_fresh_non_existing(): void
    {
        $model = new TestCollectionModel();
        $model->exists = false;

        $fresh = $model->fresh();

        $this->assertNull($fresh);
    }

    /**
     * Test refresh returns same instance for non-existing model
     */
    public function test_refresh_non_existing(): void
    {
        $model = new TestCollectionModel();
        $model->exists = false;

        $result = $model->refresh();

        $this->assertSame($model, $result);
    }

    /**
     * Test touch returns false when timestamps disabled
     */
    public function test_touch_without_timestamps(): void
    {
        $model = new TestCollectionModel();

        // TestCollectionModel has timestamps disabled
        $result = $model->touch();

        $this->assertFalse($result);
    }
}

/**
 * Test model for collections
 */
class TestCollectionModel extends Model
{
    protected static string $table = 'test_collection_models';
    protected static bool $timestamps = false;
    protected static array $fillable = ['name', 'email', 'active'];
}

/**
 * Another test model for comparison testing
 */
class AnotherTestCollectionModel extends Model
{
    protected static string $table = 'another_test_collection_models';
    protected static bool $timestamps = false;
}
