<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\Concerns\HasObservers;

/**
 * Test HasObservers
 *
 * ✅ TEST STATUS: ALL PASSED (22/22)
 * ✅ Last verified: 2025-01-22
 * ✅ Fixed: Static/non-static method conflict with Observable trait
 *
 * Comprehensive tests for observer functionality:
 * - Observer registration
 * - Observer events firing
 * - Observer isolation between models
 * - Observer methods (creating, created, updating, updated, etc.)
 * - Observer event cancellation
 * - Multiple observers per model
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class HasObserversTest extends DatabaseTestCase
{
    protected function createTables(): void
    {
        // Create users table
        $this->createTable('users', "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
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
        // Clean up observers
        ObserverTestUser::flushObservers();
        AnotherObserverTestModel::flushObservers();
        parent::tearDown();
    }

    /**
     * Test observe registers observer
     */
    public function test_observe_registers_observer(): void
    {
        $observer = new TestUserObserver();

        ObserverTestUser::observe($observer);

        $observers = ObserverTestUser::getModelObservers();

        $this->assertCount(1, $observers);
        $this->assertSame($observer, $observers[0]);
    }

    /**
     * Test observe with class name resolves observer
     */
    public function test_observe_with_class_name_resolves_observer(): void
    {
        ObserverTestUser::observe(TestUserObserver::class);

        $observers = ObserverTestUser::getModelObservers();

        $this->assertCount(1, $observers);
        $this->assertInstanceOf(TestUserObserver::class, $observers[0]);
    }

    /**
     * Test multiple observers can be registered
     */
    public function test_multiple_observers_can_be_registered(): void
    {
        $observer1 = new TestUserObserver();
        $observer2 = new AnotherTestUserObserver();

        ObserverTestUser::observe($observer1);
        ObserverTestUser::observe($observer2);

        $observers = ObserverTestUser::getModelObservers();

        $this->assertCount(2, $observers);
        $this->assertSame($observer1, $observers[0]);
        $this->assertSame($observer2, $observers[1]);
    }

    /**
     * Test flushObservers removes all observers
     */
    public function test_flush_observers_removes_all_observers(): void
    {
        $observer1 = new TestUserObserver();
        $observer2 = new AnotherTestUserObserver();

        ObserverTestUser::observe($observer1);
        ObserverTestUser::observe($observer2);

        $this->assertCount(2, ObserverTestUser::getModelObservers());

        ObserverTestUser::flushObservers();

        $this->assertEmpty(ObserverTestUser::getModelObservers());
    }

    /**
     * Test observer creating event fires
     */
    public function test_observer_creating_event_fires(): void
    {
        $observer = new TestUserObserver();
        $observer->creatingFired = false;

        ObserverTestUser::observe($observer);

        $user = new ObserverTestUser(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Manually trigger creating event
        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('fireModelEvent');
        $method->setAccessible(true);
        $result = $method->invoke($user, 'creating');

        // Observer should have been called
        $this->assertTrue($observer->creatingFired);
    }

    /**
     * Test observer created event fires
     */
    public function test_observer_created_event_fires(): void
    {
        $observer = new TestUserObserver();
        $observer->createdFired = false;

        ObserverTestUser::observe($observer);

        $user = new ObserverTestUser(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Manually trigger created event
        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('fireModelEvent');
        $method->setAccessible(true);
        $result = $method->invoke($user, 'created');

        $this->assertTrue($observer->createdFired);
    }

    /**
     * Test observer updating event fires
     */
    public function test_observer_updating_event_fires(): void
    {
        $observer = new TestUserObserver();
        $observer->updatingFired = false;

        ObserverTestUser::observe($observer);

        $user = new ObserverTestUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->exists = true; // Mark as existing

        // Manually trigger updating event
        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('fireModelEvent');
        $method->setAccessible(true);
        $result = $method->invoke($user, 'updating');

        $this->assertTrue($observer->updatingFired);
    }

    /**
     * Test observer updated event fires
     */
    public function test_observer_updated_event_fires(): void
    {
        $observer = new TestUserObserver();
        $observer->updatedFired = false;

        ObserverTestUser::observe($observer);

        $user = new ObserverTestUser(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Manually trigger updated event
        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('fireModelEvent');
        $method->setAccessible(true);
        $result = $method->invoke($user, 'updated');

        $this->assertTrue($observer->updatedFired);
    }

    /**
     * Test observer deleting event fires
     */
    public function test_observer_deleting_event_fires(): void
    {
        $observer = new TestUserObserver();
        $observer->deletingFired = false;

        ObserverTestUser::observe($observer);

        $user = new ObserverTestUser(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->exists = true; // Mark as existing

        // Manually trigger deleting event
        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('fireModelEvent');
        $method->setAccessible(true);
        $result = $method->invoke($user, 'deleting');

        $this->assertTrue($observer->deletingFired);
    }

    /**
     * Test observer deleted event fires
     */
    public function test_observer_deleted_event_fires(): void
    {
        $observer = new TestUserObserver();
        $observer->deletedFired = false;

        ObserverTestUser::observe($observer);

        $user = new ObserverTestUser(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Manually trigger deleted event
        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('fireModelEvent');
        $method->setAccessible(true);
        $result = $method->invoke($user, 'deleted');

        $this->assertTrue($observer->deletedFired);
    }

    /**
     * Test observer can cancel event by returning false
     */
    public function test_observer_can_cancel_event_by_returning_false(): void
    {
        $observer = new CancellingTestObserver();

        ObserverTestUser::observe($observer);

        $user = new ObserverTestUser(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Manually trigger creating event
        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('fireModelEvent');
        $method->setAccessible(true);
        $result = $method->invoke($user, 'creating');

        // Event should be cancelled (return false)
        $this->assertFalse($result);
    }

    /**
     * Test observer receives model instance
     */
    public function test_observer_receives_model_instance(): void
    {
        $observer = new TestUserObserver();
        $observer->receivedModel = null;

        ObserverTestUser::observe($observer);

        $user = new ObserverTestUser(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Manually trigger creating event
        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('fireModelEvent');
        $method->setAccessible(true);
        $method->invoke($user, 'creating');

        $this->assertSame($user, $observer->receivedModel);
    }

    /**
     * Test observer can modify model
     */
    public function test_observer_can_modify_model(): void
    {
        $observer = new ModifyingTestObserver();

        ObserverTestUser::observe($observer);

        $user = new ObserverTestUser(['name' => 'Original', 'email' => 'original@example.com']);

        // Manually trigger creating event
        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('fireModelEvent');
        $method->setAccessible(true);
        $method->invoke($user, 'creating');

        // Observer should have modified the model
        $this->assertEquals('Modified', $user->name);
    }

    /**
     * Test observer isolation between models
     */
    public function test_observer_isolation_between_models(): void
    {
        $observer1 = new TestUserObserver();
        $observer2 = new AnotherTestUserObserver();

        ObserverTestUser::observe($observer1);
        AnotherObserverTestModel::observe($observer2);

        $observers1 = ObserverTestUser::getModelObservers();
        $observers2 = AnotherObserverTestModel::getModelObservers();

        $this->assertCount(1, $observers1);
        $this->assertCount(1, $observers2);
        $this->assertSame($observer1, $observers1[0]);
        $this->assertSame($observer2, $observers2[0]);
    }

    /**
     * Test getObservers returns all registered observers
     */
    public function test_get_observers_returns_all_registered_observers(): void
    {
        $observer1 = new TestUserObserver();
        $observer2 = new AnotherTestUserObserver();

        ObserverTestUser::observe($observer1);
        ObserverTestUser::observe($observer2);

        $observers = ObserverTestUser::getModelObservers();

        $this->assertCount(2, $observers);
        $this->assertContains($observer1, $observers);
        $this->assertContains($observer2, $observers);
    }

    /**
     * Test observer with multiple events
     */
    public function test_observer_with_multiple_events(): void
    {
        $observer = new MultiEventTestObserver();

        ObserverTestUser::observe($observer);

        $user = new ObserverTestUser(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Trigger multiple events
        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('fireModelEvent');
        $method->setAccessible(true);

        $method->invoke($user, 'creating');
        $method->invoke($user, 'created');
        $method->invoke($user, 'updating');

        $this->assertTrue($observer->creatingFired);
        $this->assertTrue($observer->createdFired);
        $this->assertTrue($observer->updatingFired);
        $this->assertFalse($observer->updatedFired);
    }

    /**
     * Test observer does not fire for non-existent method
     */
    public function test_observer_does_not_fire_for_non_existent_method(): void
    {
        $observer = new TestUserObserver();

        ObserverTestUser::observe($observer);

        $user = new ObserverTestUser(['name' => 'John Doe', 'email' => 'john@example.com']);

        // Trigger event that observer doesn't handle
        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('fireModelEvent');
        $method->setAccessible(true);
        $result = $method->invoke($user, 'nonExistentEvent');

        // Should return true (no observer to cancel)
        $this->assertTrue($result);
    }

    /**
     * Test observer receives model in all events
     */
    public function test_observer_receives_model_in_all_events(): void
    {
        $observer = new TestUserObserver();
        $observer->receivedModels = [];

        ObserverTestUser::observe($observer);

        $user = new ObserverTestUser(['name' => 'John Doe', 'email' => 'john@example.com']);

        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('fireModelEvent');
        $method->setAccessible(true);

        // Trigger multiple events
        $events = ['creating', 'created', 'updating', 'updated', 'deleting', 'deleted'];
        foreach ($events as $event) {
            $method->invoke($user, $event);
        }

        // Observer should receive model in all events it handles
        $this->assertNotEmpty($observer->receivedModels);
        foreach ($observer->receivedModels as $receivedModel) {
            $this->assertSame($user, $receivedModel);
        }
    }

    /**
     * Test observer with same instance registered multiple times
     */
    public function test_observer_with_same_instance_registered_multiple_times(): void
    {
        $observer = new TestUserObserver();

        ObserverTestUser::observe($observer);
        ObserverTestUser::observe($observer);
        ObserverTestUser::observe($observer);

        $observers = ObserverTestUser::getModelObservers();

        // Same instance should be registered multiple times
        $this->assertCount(3, $observers);
        foreach ($observers as $obs) {
            $this->assertSame($observer, $obs);
        }
    }

    /**
     * Test flushObservers after registration
     */
    public function test_flush_observers_after_registration(): void
    {
        $observer1 = new TestUserObserver();
        $observer2 = new AnotherTestUserObserver();

        ObserverTestUser::observe($observer1);
        ObserverTestUser::observe($observer2);

        $this->assertCount(2, ObserverTestUser::getModelObservers());

        ObserverTestUser::flushObservers();

        $this->assertEmpty(ObserverTestUser::getModelObservers());

        // Should be able to register again
        ObserverTestUser::observe($observer1);
        $this->assertCount(1, ObserverTestUser::getModelObservers());
    }

    /**
     * Test observer with class name string
     */
    public function test_observer_with_class_name_string(): void
    {
        ObserverTestUser::observe(TestUserObserver::class);

        $observers = ObserverTestUser::getModelObservers();

        $this->assertCount(1, $observers);
        $this->assertInstanceOf(TestUserObserver::class, $observers[0]);
    }

    /**
     * Test observer methods are called in order
     */
    public function test_observer_methods_are_called_in_order(): void
    {
        $observer = new OrderTrackingObserver();

        ObserverTestUser::observe($observer);

        $user = new ObserverTestUser(['name' => 'John Doe', 'email' => 'john@example.com']);

        $reflection = new \ReflectionClass($user);
        $method = $reflection->getMethod('fireModelEvent');
        $method->setAccessible(true);

        // Trigger events in order
        $method->invoke($user, 'creating');
        $method->invoke($user, 'created');

        // Verify order
        $this->assertEquals(['creating', 'created'], $observer->callOrder);
    }
}

/**
 * Test model with HasObservers trait
 */
class ObserverTestUser extends Model
{
    use HasObservers;

    protected static string $table = 'users';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['name', 'email'];

    public function save(): bool
    {
        // Simple save implementation for testing
        return true;
    }

    protected static function getConnection(): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        return parent::getConnection();
    }
}

/**
 * Another test model for isolation testing
 */
class AnotherObserverTestModel extends Model
{
    use HasObservers;

    protected static string $table = 'another_users';
    protected static bool $timestamps = false;
}

/**
 * Test observer class
 */
class TestUserObserver
{
    public bool $creatingFired = false;
    public bool $createdFired = false;
    public bool $updatingFired = false;
    public bool $updatedFired = false;
    public bool $deletingFired = false;
    public bool $deletedFired = false;
    public ?Model $receivedModel = null;
    public array $receivedModels = [];

    public function creating(Model $model): void
    {
        $this->creatingFired = true;
        $this->receivedModel = $model;
        $this->receivedModels[] = $model;
    }

    public function created(Model $model): void
    {
        $this->createdFired = true;
        $this->receivedModel = $model;
        $this->receivedModels[] = $model;
    }

    public function updating(Model $model): void
    {
        $this->updatingFired = true;
        $this->receivedModel = $model;
        $this->receivedModels[] = $model;
    }

    public function updated(Model $model): void
    {
        $this->updatedFired = true;
        $this->receivedModel = $model;
        $this->receivedModels[] = $model;
    }

    public function deleting(Model $model): void
    {
        $this->deletingFired = true;
        $this->receivedModel = $model;
        $this->receivedModels[] = $model;
    }

    public function deleted(Model $model): void
    {
        $this->deletedFired = true;
        $this->receivedModel = $model;
        $this->receivedModels[] = $model;
    }
}

/**
 * Another test observer
 */
class AnotherTestUserObserver
{
    public bool $fired = false;

    public function creating(Model $model): void
    {
        $this->fired = true;
    }
}

/**
 * Cancelling observer (returns false)
 */
class CancellingTestObserver
{
    public function creating(Model $model): bool
    {
        return false; // Cancel event
    }
}

/**
 * Modifying observer
 */
class ModifyingTestObserver
{
    public function creating(Model $model): void
    {
        $model->setAttribute('name', 'Modified');
    }
}

/**
 * Multi-event observer
 */
class MultiEventTestObserver
{
    public bool $creatingFired = false;
    public bool $createdFired = false;
    public bool $updatingFired = false;
    public bool $updatedFired = false;

    public function creating(Model $model): void
    {
        $this->creatingFired = true;
    }

    public function created(Model $model): void
    {
        $this->createdFired = true;
    }

    public function updating(Model $model): void
    {
        $this->updatingFired = true;
    }

    public function updated(Model $model): void
    {
        $this->updatedFired = true;
    }
}

/**
 * Order tracking observer
 */
class OrderTrackingObserver
{
    public array $callOrder = [];

    public function creating(Model $model): void
    {
        $this->callOrder[] = 'creating';
    }

    public function created(Model $model): void
    {
        $this->callOrder[] = 'created';
    }
}
