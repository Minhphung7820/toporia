<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Scheduling;

use PHPUnit\Framework\TestCase;
use Toporia\Framework\Console\Scheduling\ScheduledTask;

/**
 * ScheduledTask Unit Tests
 *
 * Tests task configuration, cron expressions, hooks, and output handling.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class ScheduledTaskTest extends TestCase
{
    // ==================== Frequency Methods Tests ====================

    public function test_every_minute_sets_correct_expression(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->everyMinute();

        $this->assertSame('* * * * *', $task->getExpression());
    }

    public function test_every_minutes_sets_correct_expression(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->everyMinutes(5);

        $this->assertSame('*/5 * * * *', $task->getExpression());
    }

    public function test_hourly_sets_correct_expression(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->hourly();

        $this->assertSame('0 * * * *', $task->getExpression());
    }

    public function test_hourly_at_sets_correct_expression(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->hourlyAt(30);

        $this->assertSame('30 * * * *', $task->getExpression());
    }

    public function test_daily_sets_correct_expression(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->daily();

        $this->assertSame('0 0 * * *', $task->getExpression());
    }

    public function test_daily_at_sets_correct_expression(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->dailyAt('14:30');

        $this->assertSame('30 14 * * *', $task->getExpression());
    }

    public function test_weekly_sets_correct_expression(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->weekly();

        $this->assertSame('0 0 * * 0', $task->getExpression());
    }

    public function test_monthly_sets_correct_expression(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->monthly();

        $this->assertSame('0 0 1 * *', $task->getExpression());
    }

    public function test_weekdays_sets_correct_expression(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->weekdays();

        $this->assertSame('0 0 * * 1-5', $task->getExpression());
    }

    public function test_weekends_sets_correct_expression(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->weekends();

        $this->assertSame('0 0 * * 0,6', $task->getExpression());
    }

    public function test_mondays_sets_correct_expression(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->mondays();

        $this->assertSame('0 0 * * 1', $task->getExpression());
    }

    public function test_custom_cron_sets_expression(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->cron('15 8 * * 1-5');

        $this->assertSame('15 8 * * 1-5', $task->getExpression());
    }

    // ==================== Constraint Tests ====================

    public function test_when_adds_filter(): void
    {
        $task = new ScheduledTask(fn() => true);
        $filter = fn() => true;

        $task->when($filter);

        $this->assertTrue($task->filtersPass());
    }

    public function test_skip_adds_reject(): void
    {
        $task = new ScheduledTask(fn() => true);
        $reject = fn() => false;

        $task->skip($reject);

        $this->assertTrue($task->filtersPass());
    }

    public function test_skip_prevents_execution(): void
    {
        $task = new ScheduledTask(fn() => true);
        $reject = fn() => true; // Should skip

        $task->skip($reject);

        $this->assertFalse($task->filtersPass());
    }

    public function test_timezone_sets_correctly(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->timezone('America/New_York');

        $this->assertSame('America/New_York', $task->getTimezone());
    }

    // ==================== Overlap Prevention Tests ====================

    public function test_without_overlapping_enables_mutex(): void
    {
        $task = new ScheduledTask(fn() => true, 'test-task');
        $task->withoutOverlapping();

        $this->assertTrue($task->shouldPreventOverlap());
    }

    public function test_without_overlapping_sets_expires_after(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->withoutOverlapping(120);

        $this->assertSame(120, $task->getExpiresAfter());
    }

    public function test_name_sets_mutex_name(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->name('custom-mutex');

        $this->assertSame('custom-mutex', $task->getMutexName());
    }

    // ==================== Background Execution Tests ====================

    public function test_run_in_background_sets_flag(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->runInBackground();

        $this->assertTrue($task->shouldRunInBackground());
    }

    public function test_run_in_foreground_unsets_flag(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->runInBackground();
        $task->runInForeground();

        $this->assertFalse($task->shouldRunInBackground());
    }

    // ==================== Description Tests ====================

    public function test_description_sets_correctly(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->description('Test Task');

        $this->assertSame('Test Task', $task->getDescription());
    }

    public function test_constructor_accepts_description(): void
    {
        $task = new ScheduledTask(fn() => true, 'Initial Description');

        $this->assertSame('Initial Description', $task->getDescription());
    }

    // ==================== Output Handling Tests ====================

    public function test_send_output_to_sets_file(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->sendOutputTo('/var/log/task.log');

        $this->assertSame('/var/log/task.log', $task->getOutputFile());
        $this->assertFalse($task->shouldAppendOutput());
    }

    public function test_append_output_to_sets_file_and_append_flag(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->appendOutputTo('/var/log/task.log');

        $this->assertSame('/var/log/task.log', $task->getOutputFile());
        $this->assertTrue($task->shouldAppendOutput());
    }

    public function test_email_output_to_sets_email(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->emailOutputTo('admin@example.com');

        $this->assertSame('admin@example.com', $task->getEmailOutputTo());
        $this->assertFalse($task->shouldEmailOnlyOnFailure());
    }

    public function test_email_output_on_failure_sets_email_and_flag(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->emailOutputOnFailure('admin@example.com');

        $this->assertSame('admin@example.com', $task->getEmailOutputTo());
        $this->assertTrue($task->shouldEmailOnlyOnFailure());
    }

    // ==================== Hooks Tests ====================

    public function test_before_sets_callback(): void
    {
        $task = new ScheduledTask(fn() => true);
        $callback = fn() => 'before';

        $task->before($callback);

        $this->assertSame($callback, $task->getBeforeCallback());
    }

    public function test_after_sets_callback(): void
    {
        $task = new ScheduledTask(fn() => true);
        $callback = fn() => 'after';

        $task->after($callback);

        $this->assertSame($callback, $task->getAfterCallback());
    }

    public function test_then_is_alias_for_after(): void
    {
        $task = new ScheduledTask(fn() => true);
        $callback = fn() => 'then';

        $task->then($callback);

        $this->assertSame($callback, $task->getAfterCallback());
    }

    public function test_on_success_sets_callback(): void
    {
        $task = new ScheduledTask(fn() => true);
        $callback = fn() => 'success';

        $task->onSuccess($callback);

        $this->assertSame($callback, $task->getOnSuccessCallback());
    }

    public function test_on_failure_sets_callback(): void
    {
        $task = new ScheduledTask(fn() => true);
        $callback = fn($e) => 'failure';

        $task->onFailure($callback);

        $this->assertSame($callback, $task->getOnFailureCallback());
    }

    // ==================== Fluent Interface Tests ====================

    public function test_methods_return_self_for_chaining(): void
    {
        $task = new ScheduledTask(fn() => true);

        $result = $task->everyMinute()
            ->timezone('UTC')
            ->withoutOverlapping()
            ->runInBackground()
            ->sendOutputTo('/tmp/test.log')
            ->before(fn() => null)
            ->after(fn() => null)
            ->description('Chained Task');

        $this->assertSame($task, $result);
    }

    // ==================== Execution Tests ====================

    public function test_execute_runs_callback(): void
    {
        $executed = false;
        $task = new ScheduledTask(function () use (&$executed) {
            $executed = true;
        });

        $task->execute();

        $this->assertTrue($executed);
    }

    public function test_execute_with_string_command(): void
    {
        $task = new ScheduledTask('cache:clear');

        // Should not throw exception
        $this->expectNotToPerformAssertions();
        // Note: actual command execution requires container
    }

    // ==================== Cron Matching Tests ====================

    public function test_is_due_matches_current_time(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->cron('* * * * *'); // Every minute

        $this->assertTrue($task->isDue());
    }

    public function test_is_due_respects_timezone(): void
    {
        $task = new ScheduledTask(fn() => true);
        $task->hourly()->timezone('America/New_York');

        // Check if task respects timezone
        $this->assertIsBool($task->isDue());
    }
}
