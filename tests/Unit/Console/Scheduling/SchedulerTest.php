<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Scheduling;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Console\Scheduling\Scheduler;
use Toporia\Framework\Console\Scheduling\ScheduledTask;
use Toporia\Framework\Console\Scheduling\Contracts\MutexInterface;
use Toporia\Framework\Container\Contracts\ContainerInterface;

/**
 * Scheduler Unit Tests
 *
 * Tests task registration, execution, and mutex handling.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class SchedulerTest extends TestCase
{
    private Scheduler $scheduler;
    private MutexInterface $mutex;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->scheduler = new Scheduler();
        $this->mutex = $this->createMock(MutexInterface::class);
        $this->container = $this->createMock(ContainerInterface::class);

        $this->scheduler->setMutex($this->mutex);
        $this->scheduler->setContainer($this->container);
    }

    // ==================== Task Registration Tests ====================

    public function test_call_registers_callback_task(): void
    {
        $callback = fn() => 'test';

        $task = $this->scheduler->call($callback, 'Test Task');

        $this->assertInstanceOf(ScheduledTask::class, $task);
        $this->assertSame('Test Task', $task->getDescription());
    }

    public function test_exec_registers_shell_command(): void
    {
        $task = $this->scheduler->exec('echo "hello"', 'Echo Command');

        $this->assertInstanceOf(ScheduledTask::class, $task);
        $this->assertSame('Echo Command', $task->getDescription());
    }

    public function test_command_registers_console_command(): void
    {
        $task = $this->scheduler->command('cache:clear', [], 'Clear Cache');

        $this->assertInstanceOf(ScheduledTask::class, $task);
        $this->assertSame('Clear Cache', $task->getDescription());
    }

    public function test_job_registers_queue_job(): void
    {
        $task = $this->scheduler->job(\stdClass::class, 'Queue Job');

        $this->assertInstanceOf(ScheduledTask::class, $task);
        $this->assertSame('Queue Job', $task->getDescription());
    }

    // ==================== Task Retrieval Tests ====================

    public function test_get_tasks_returns_all_registered_tasks(): void
    {
        $this->scheduler->call(fn() => 1);
        $this->scheduler->call(fn() => 2);
        $this->scheduler->call(fn() => 3);

        $tasks = $this->scheduler->getTasks();

        $this->assertCount(3, $tasks);
    }

    public function test_get_due_tasks_filters_by_cron_expression(): void
    {
        // Task that runs every minute (always due)
        $this->scheduler->call(fn() => 1)->everyMinute();

        // Task that runs at specific time (may not be due)
        $this->scheduler->call(fn() => 2)->dailyAt('03:00');

        $dueTasks = $this->scheduler->getDueTasks();

        $this->assertGreaterThanOrEqual(1, count($dueTasks));
    }

    public function test_list_tasks_returns_task_information(): void
    {
        $this->scheduler->call(fn() => 1, 'Task 1')->everyMinute();
        $this->scheduler->call(fn() => 2, 'Task 2')->hourly();

        $taskList = $this->scheduler->listTasks();

        $this->assertCount(2, $taskList);
        $this->assertArrayHasKey('expression', $taskList[0]);
        $this->assertArrayHasKey('description', $taskList[0]);
    }

    // ==================== Task Execution Tests ====================

    public function test_run_due_tasks_executes_due_tasks(): void
    {
        $executed = false;

        $this->scheduler->call(function () use (&$executed) {
            $executed = true;
        })->everyMinute();

        $count = $this->scheduler->runDueTasks();

        $this->assertTrue($executed);
        $this->assertSame(1, $count);
    }

    public function test_run_due_tasks_skips_tasks_failing_filters(): void
    {
        $executed = false;

        $this->scheduler->call(function () use (&$executed) {
            $executed = true;
        })
            ->everyMinute()
            ->skip(fn() => true); // Always skip

        $count = $this->scheduler->runDueTasks();

        $this->assertFalse($executed);
        $this->assertSame(0, $count);
    }

    public function test_run_due_tasks_respects_when_constraint(): void
    {
        $executed = false;

        $this->scheduler->call(function () use (&$executed) {
            $executed = true;
        })
            ->everyMinute()
            ->when(fn() => false); // Never run

        $count = $this->scheduler->runDueTasks();

        $this->assertFalse($executed);
        $this->assertSame(0, $count);
    }

    // ==================== Mutex Tests ====================

    public function test_overlap_prevention_acquires_mutex(): void
    {
        $this->mutex->expects($this->once())
            ->method('create')
            ->willReturn(true);

        $this->mutex->expects($this->once())
            ->method('forget');

        $this->scheduler->call(fn() => 'test')
            ->everyMinute()
            ->withoutOverlapping();

        $this->scheduler->runDueTasks();
    }

    public function test_overlap_prevention_skips_if_mutex_exists(): void
    {
        $executed = false;

        $this->mutex->method('exists')
            ->willReturn(true); // Mutex already exists

        $this->scheduler->call(function () use (&$executed) {
            $executed = true;
        })
            ->everyMinute()
            ->withoutOverlapping();

        $count = $this->scheduler->runDueTasks();

        $this->assertFalse($executed);
        $this->assertSame(0, $count);
    }

    public function test_mutex_is_released_on_task_failure(): void
    {
        $this->mutex->expects($this->once())
            ->method('create')
            ->willReturn(true);

        $this->mutex->expects($this->once())
            ->method('forget'); // Should release even on failure

        $this->scheduler->call(function () {
            throw new \Exception('Task failed');
        })
            ->everyMinute()
            ->withoutOverlapping();

        $this->scheduler->runDueTasks();
    }

    // ==================== Background Execution Tests ====================

    public function test_background_execution_with_pcntl(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl_fork not available');
        }

        $this->scheduler->call(fn() => sleep(1))
            ->everyMinute()
            ->runInBackground();

        $start = microtime(true);
        $this->scheduler->runDueTasks();
        $duration = microtime(true) - $start;

        // Background task should not block
        $this->assertLessThan(0.5, $duration);
    }

    // ==================== Container Integration Tests ====================

    public function test_command_task_uses_container(): void
    {
        $this->container->expects($this->once())
            ->method('has')
            ->with('console')
            ->willReturn(true);

        $console = $this->createMock(\Toporia\Framework\Console\Console::class);
        $console->expects($this->once())
            ->method('call')
            ->with('test:command');

        $this->container->expects($this->once())
            ->method('get')
            ->with('console')
            ->willReturn($console);

        $this->scheduler->command('test:command')->everyMinute();
        $this->scheduler->runDueTasks();
    }

    // ==================== Error Handling Tests ====================

    public function test_task_failure_does_not_stop_other_tasks(): void
    {
        $secondTaskExecuted = false;

        // First task fails
        $this->scheduler->call(function () {
            throw new \Exception('First task failed');
        })->everyMinute();

        // Second task should still run
        $this->scheduler->call(function () use (&$secondTaskExecuted) {
            $secondTaskExecuted = true;
        })->everyMinute();

        $this->scheduler->runDueTasks();

        $this->assertTrue($secondTaskExecuted);
    }

    // ==================== Edge Cases ====================

    public function test_empty_scheduler_returns_zero_count(): void
    {
        $count = $this->scheduler->runDueTasks();

        $this->assertSame(0, $count);
    }

    public function test_task_with_no_description_has_default(): void
    {
        $task = $this->scheduler->call(fn() => 'test');

        $this->assertStringContainsString('Closure', $task->getDescription());
    }
}
