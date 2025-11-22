<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Database\ORM\Model;

/**
 * Test Model Mass Assignment Protection
 *
 * Tests mass assignment protection features:
 * - forceFill() bypasses protection
 * - unguard() / reguard() global controls
 * - isUnguarded() status check
 * - unguarded() callback
 * - preventAccessingMissingAttributes()
 * - preventSilentlyDiscardingAttributes()
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class ModelMassAssignmentTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset protection state after each test
        TestMassAssignmentModel::reguard();
        TestMassAssignmentModel::preventAccessingMissingAttributes(false);
        TestMassAssignmentModel::preventSilentlyDiscardingAttributes(false);
    }

    /**
     * Test forceFill bypasses mass assignment protection
     */
    public function test_force_fill_bypasses_protection(): void
    {
        $model = new TestMassAssignmentModel();
        $model->forceFill([
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'secret' // Not fillable normally
        ]);

        $this->assertEquals('John', $model->getAttribute('name'));
        $this->assertEquals('john@example.com', $model->getAttribute('email'));
        $this->assertEquals('secret', $model->getAttribute('password'));
    }

    /**
     * Test fill respects fillable rules
     */
    public function test_fill_respects_fillable(): void
    {
        $model = new TestMassAssignmentModel();
        $model->fill([
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'secret' // Not fillable
        ]);

        $this->assertEquals('John', $model->getAttribute('name'));
        $this->assertEquals('john@example.com', $model->getAttribute('email'));
        $this->assertNull($model->getAttribute('password')); // Should be null
    }

    /**
     * Test unguard disables protection globally
     */
    public function test_unguard_disables_protection(): void
    {
        TestMassAssignmentModel::unguard();

        $model = new TestMassAssignmentModel();
        $model->fill([
            'name' => 'John',
            'password' => 'secret'
        ]);

        $this->assertEquals('John', $model->getAttribute('name'));
        $this->assertEquals('secret', $model->getAttribute('password'));

        TestMassAssignmentModel::reguard();
    }

    /**
     * Test reguard enables protection
     */
    public function test_reguard_enables_protection(): void
    {
        TestMassAssignmentModel::unguard();
        $this->assertTrue(TestMassAssignmentModel::isUnguarded());

        TestMassAssignmentModel::reguard();
        $this->assertFalse(TestMassAssignmentModel::isUnguarded());
    }

    /**
     * Test isUnguarded returns correct state
     */
    public function test_is_unguarded(): void
    {
        $this->assertFalse(TestMassAssignmentModel::isUnguarded());

        TestMassAssignmentModel::unguard();
        $this->assertTrue(TestMassAssignmentModel::isUnguarded());

        TestMassAssignmentModel::reguard();
        $this->assertFalse(TestMassAssignmentModel::isUnguarded());
    }

    /**
     * Test unguarded callback temporarily disables protection
     */
    public function test_unguarded_callback(): void
    {
        $this->assertFalse(TestMassAssignmentModel::isUnguarded());

        $result = TestMassAssignmentModel::unguarded(function () {
            $this->assertTrue(TestMassAssignmentModel::isUnguarded());

            $model = new TestMassAssignmentModel();
            $model->fill(['password' => 'secret']);
            return $model->getAttribute('password');
        });

        $this->assertEquals('secret', $result);
        $this->assertFalse(TestMassAssignmentModel::isUnguarded());
    }

    /**
     * Test unguarded callback restores previous state
     */
    public function test_unguarded_callback_restores_state(): void
    {
        TestMassAssignmentModel::unguard();

        TestMassAssignmentModel::unguarded(function () {
            $this->assertTrue(TestMassAssignmentModel::isUnguarded());
        });

        $this->assertTrue(TestMassAssignmentModel::isUnguarded());

        TestMassAssignmentModel::reguard();
    }

    /**
     * Test preventSilentlyDiscardingAttributes throws on non-fillable
     */
    public function test_prevent_silently_discarding_attributes(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Add [password] to fillable property');

        TestMassAssignmentModel::preventSilentlyDiscardingAttributes(true);

        $model = new TestMassAssignmentModel();
        $model->fill(['password' => 'secret']);
    }

    /**
     * Test preventAccessingMissingAttributes throws on missing attribute
     */
    public function test_prevent_accessing_missing_attributes(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Attribute [nonexistent] does not exist');

        TestMassAssignmentModel::preventAccessingMissingAttributes(true);

        $model = new TestMassAssignmentModel();
        $model->getAttribute('nonexistent');
    }

    /**
     * Test accessing existing attribute works with prevention enabled
     */
    public function test_accessing_existing_attribute_with_prevention(): void
    {
        TestMassAssignmentModel::preventAccessingMissingAttributes(true);

        $model = new TestMassAssignmentModel();
        $model->setAttribute('name', 'John');

        $this->assertEquals('John', $model->getAttribute('name'));
    }

    /**
     * Test fillable attributes work with prevention
     */
    public function test_fillable_with_prevention(): void
    {
        TestMassAssignmentModel::preventSilentlyDiscardingAttributes(true);

        $model = new TestMassAssignmentModel();
        $model->fill(['name' => 'John', 'email' => 'john@example.com']);

        $this->assertEquals('John', $model->getAttribute('name'));
        $this->assertEquals('john@example.com', $model->getAttribute('email'));
    }

    /**
     * Test forceFill works with prevention enabled
     */
    public function test_force_fill_with_prevention(): void
    {
        TestMassAssignmentModel::preventSilentlyDiscardingAttributes(true);

        $model = new TestMassAssignmentModel();
        $model->forceFill(['password' => 'secret']);

        $this->assertEquals('secret', $model->getAttribute('password'));
    }

    /**
     * Test unguard with bool parameter
     */
    public function test_unguard_with_bool_parameter(): void
    {
        TestMassAssignmentModel::unguard(true);
        $this->assertTrue(TestMassAssignmentModel::isUnguarded());

        TestMassAssignmentModel::unguard(false);
        $this->assertFalse(TestMassAssignmentModel::isUnguarded());
    }

    /**
     * Test forceFill returns this for chaining
     */
    public function test_force_fill_returns_this(): void
    {
        $model = new TestMassAssignmentModel();
        $result = $model->forceFill(['name' => 'John']);

        $this->assertSame($model, $result);
    }
}

/**
 * Test model for mass assignment
 */
class TestMassAssignmentModel extends Model
{
    protected static string $table = 'test_mass_assignment_models';
    protected static bool $timestamps = false;
    protected static array $fillable = ['name', 'email'];
}
