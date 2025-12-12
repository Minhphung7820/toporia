<?php

declare(strict_types=1);

namespace Tests\Unit\Concurrency;

use Toporia\Framework\Concurrency\Concurrency;
use Toporia\Framework\Concurrency\ConcurrencyManager;
use Toporia\Framework\Concurrency\Drivers\ForkConcurrencyDriver;
use Toporia\Framework\Concurrency\Drivers\ProcessConcurrencyDriver;
use Toporia\Framework\Concurrency\Drivers\SyncConcurrencyDriver;
use Toporia\Framework\Concurrency\Process\ProcessFactory;
use Toporia\Framework\Concurrency\Serialization\SerializableClosureSerializer;
use Toporia\Framework\Testing\TestCase;

/**
 * Concurrency Manager Tests
 */
class ConcurrencyManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Concurrency::reset();
    }

    protected function tearDown(): void
    {
        Concurrency::reset();
        parent::tearDown();
    }

    // =========================================================================
    // SyncConcurrencyDriver Tests
    // =========================================================================

    public function test_sync_driver_executes_tasks_sequentially(): void
    {
        $driver = new SyncConcurrencyDriver();

        $results = $driver->run([
            fn() => 1,
            fn() => 2,
            fn() => 3,
        ]);

        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $results);
    }

    public function test_sync_driver_preserves_string_keys(): void
    {
        $driver = new SyncConcurrencyDriver();

        $results = $driver->run([
            'first' => fn() => 'a',
            'second' => fn() => 'b',
        ]);

        $this->assertEquals(['first' => 'a', 'second' => 'b'], $results);
    }

    public function test_sync_driver_handles_exceptions(): void
    {
        $driver = new SyncConcurrencyDriver();

        $results = $driver->run([
            'success' => fn() => 'ok',
            'fail' => fn() => throw new \Exception('Test error'),
        ]);

        $this->assertEquals('ok', $results['success']);
        $this->assertIsArray($results['fail']);
        $this->assertEquals('Test error', $results['fail']['error']);
    }

    public function test_sync_driver_is_always_supported(): void
    {
        $driver = new SyncConcurrencyDriver();
        $this->assertTrue($driver->isSupported());
        $this->assertEquals('sync', $driver->getName());
    }

    // =========================================================================
    // ForkConcurrencyDriver Tests
    // =========================================================================

    public function test_fork_driver_support_check(): void
    {
        $driver = new ForkConcurrencyDriver();

        if (function_exists('pcntl_fork')) {
            $this->assertTrue($driver->isSupported());
        } else {
            $this->assertFalse($driver->isSupported());
        }

        $this->assertEquals('fork', $driver->getName());
    }

    /**
     * @requires extension pcntl
     */
    public function test_fork_driver_executes_tasks_in_parallel(): void
    {
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            $this->markTestSkipped('Fork driver requires CLI context');
        }

        $driver = new ForkConcurrencyDriver(maxConcurrent: 4);

        $start = microtime(true);
        $results = $driver->run([
            fn() => usleep(50000) ?? 'task1',
            fn() => usleep(50000) ?? 'task2',
            fn() => usleep(50000) ?? 'task3',
            fn() => usleep(50000) ?? 'task4',
        ]);

        $elapsed = microtime(true) - $start;

        // If truly parallel, should take ~50ms not 200ms
        $this->assertLessThan(0.5, $elapsed, 'Tasks should run in parallel');
        $this->assertCount(4, $results);
    }

    /**
     * @requires extension pcntl
     */
    public function test_fork_driver_preserves_keys(): void
    {
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            $this->markTestSkipped('Fork driver requires CLI context');
        }

        $driver = new ForkConcurrencyDriver(maxConcurrent: 2);

        $results = $driver->run([
            'users' => fn() => ['count' => 100],
            'posts' => fn() => ['count' => 200],
        ]);

        $this->assertArrayHasKey('users', $results);
        $this->assertArrayHasKey('posts', $results);
        $this->assertEquals(['count' => 100], $results['users']);
        $this->assertEquals(['count' => 200], $results['posts']);
    }

    /**
     * @requires extension pcntl
     */
    public function test_fork_driver_handles_task_exceptions(): void
    {
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            $this->markTestSkipped('Fork driver requires CLI context');
        }

        $driver = new ForkConcurrencyDriver();

        $results = $driver->run([
            'success' => fn() => 'ok',
            'fail' => fn() => throw new \RuntimeException('Task failed'),
        ]);

        $this->assertEquals('ok', $results['success']);
        $this->assertIsArray($results['fail']);
        $this->assertStringContainsString('Task failed', $results['fail']['error']);
    }

    // =========================================================================
    // ConcurrencyManager Tests
    // =========================================================================

    public function test_manager_basic_usage(): void
    {
        $drivers = [
            'sync' => new SyncConcurrencyDriver(),
        ];

        $manager = new ConcurrencyManager($drivers, 'sync');

        $results = $manager->run([
            fn() => 'result1',
            fn() => 'result2',
        ]);

        $this->assertEquals(['result1', 'result2'], array_values($results));
    }

    public function test_manager_driver_selection(): void
    {
        $syncDriver = new SyncConcurrencyDriver();

        $drivers = [
            'sync' => $syncDriver,
        ];

        $manager = new ConcurrencyManager($drivers, 'sync');

        $this->assertSame($syncDriver, $manager->driver('sync'));
        $this->assertSame($syncDriver, $manager->driver());
    }

    public function test_manager_unknown_driver_throws(): void
    {
        $drivers = ['sync' => new SyncConcurrencyDriver()];
        $manager = new ConcurrencyManager($drivers, 'sync');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown concurrency driver');

        $manager->driver('unknown');
    }

    public function test_manager_extend(): void
    {
        $drivers = ['sync' => new SyncConcurrencyDriver()];
        $manager = new ConcurrencyManager($drivers, 'sync');

        $customDriver = new SyncConcurrencyDriver();
        $manager->extend('custom', $customDriver);

        $this->assertTrue($manager->hasDriver('custom'));
        $this->assertSame($customDriver, $manager->driver('custom'));
    }

    public function test_manager_set_default_driver(): void
    {
        $sync1 = new SyncConcurrencyDriver();
        $sync2 = new SyncConcurrencyDriver();

        $drivers = [
            'driver1' => $sync1,
            'driver2' => $sync2,
        ];

        $manager = new ConcurrencyManager($drivers, 'driver1');

        $this->assertEquals('driver1', $manager->getDefaultDriver());

        $manager->setDefaultDriver('driver2');
        $this->assertEquals('driver2', $manager->getDefaultDriver());
    }

    public function test_manager_get_available_drivers(): void
    {
        $drivers = [
            'sync' => new SyncConcurrencyDriver(),
            'fork' => new ForkConcurrencyDriver(),
        ];

        $manager = new ConcurrencyManager($drivers, 'sync');

        $available = $manager->getAvailableDrivers();
        $this->assertContains('sync', $available);
        $this->assertContains('fork', $available);
    }

    // =========================================================================
    // Static Facade Tests
    // =========================================================================

    public function test_facade_basic_usage(): void
    {
        Concurrency::setDefaultDriver('sync');

        $results = Concurrency::run([
            fn() => 'result1',
            fn() => 'result2',
        ]);

        $this->assertEquals(['result1', 'result2'], array_values($results));
    }

    public function test_facade_with_named_keys(): void
    {
        Concurrency::setDefaultDriver('sync');

        $results = Concurrency::run([
            'userCount' => fn() => 42,
            'orderCount' => fn() => 100,
        ]);

        $this->assertEquals(42, $results['userCount']);
        $this->assertEquals(100, $results['orderCount']);
    }

    public function test_facade_driver_selection(): void
    {
        $syncDriver = Concurrency::driver('sync');
        $this->assertInstanceOf(SyncConcurrencyDriver::class, $syncDriver);

        $forkDriver = Concurrency::driver('fork');
        $this->assertInstanceOf(ForkConcurrencyDriver::class, $forkDriver);
    }

    public function test_facade_max_concurrent_setting(): void
    {
        Concurrency::setMaxConcurrent(8);
        $this->assertEquals(8, Concurrency::getMaxConcurrent());

        Concurrency::setMaxConcurrent(0);
        $this->assertEquals(1, Concurrency::getMaxConcurrent());
    }

    public function test_facade_timeout_setting(): void
    {
        Concurrency::setTimeout(120.0);
        $this->assertEquals(120.0, Concurrency::getTimeout());
    }

    public function test_facade_reset(): void
    {
        Concurrency::setDefaultDriver('sync');
        Concurrency::setMaxConcurrent(16);
        Concurrency::setTimeout(30.0);

        Concurrency::reset();

        $this->assertEquals('process', Concurrency::getDefaultDriver());
        $this->assertEquals(4, Concurrency::getMaxConcurrent());
        $this->assertEquals(60.0, Concurrency::getTimeout());
    }

    public function test_facade_available_drivers(): void
    {
        $drivers = Concurrency::getAvailableDrivers();

        $this->assertArrayHasKey('fork', $drivers);
        $this->assertArrayHasKey('process', $drivers);
        $this->assertArrayHasKey('sync', $drivers);
        $this->assertTrue($drivers['sync']);
    }

    // =========================================================================
    // Empty Tasks Tests
    // =========================================================================

    public function test_empty_tasks_return_empty_array(): void
    {
        $syncDriver = new SyncConcurrencyDriver();
        $this->assertEquals([], $syncDriver->run([]));

        Concurrency::setDefaultDriver('sync');
        $this->assertEquals([], Concurrency::run([]));
    }

    // =========================================================================
    // Result Order Tests
    // =========================================================================

    public function test_results_maintain_input_order(): void
    {
        Concurrency::setDefaultDriver('sync');

        $results = Concurrency::run([
            'z' => fn() => 'last',
            'a' => fn() => 'first',
            'm' => fn() => 'middle',
        ]);

        $keys = array_keys($results);
        $this->assertEquals(['z', 'a', 'm'], $keys);
    }
}
