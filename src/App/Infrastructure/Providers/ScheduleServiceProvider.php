<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Application\Services\Jobs\TestProcess;
use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Console\Scheduling\Scheduler;

/**
 * Schedule Service Provider
 *
 * Define all scheduled tasks in one place.
 * This provider is loaded automatically and tasks are registered during boot phase.
 */
final class ScheduleServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Nothing to register
    }

    public function boot(ContainerInterface $container): void
    {
        $scheduler = $container->get(Scheduler::class);

        $this->defineSchedule($scheduler, $container);
    }

    /**
     * Define the application's scheduled tasks
     *
     * Add your scheduled tasks here.
     *
     * @param Scheduler $scheduler
     * @param ContainerInterface $container
     * @return void
     */
    private function defineSchedule(Scheduler $scheduler, ContainerInterface $container): void
    {


        // ========================================================================
        // COMPREHENSIVE SCHEDULE TESTS - Testing all features
        // ========================================================================

        // Create log directory for schedule tests
        // Get absolute path to storage/logs/schedule
        $basePath = $container->has('app') && method_exists($container->get('app'), 'getBasePath')
            ? $container->get('app')->getBasePath()
            : dirname(__DIR__, 3);
        $logDir = $basePath . '/storage/logs/schedule';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/test_' . date('Y-m-d') . '.log';

        $logTask = function (string $taskName, array $extra = []) use ($logFile) {
            $timestamp = date('Y-m-d H:i:s');
            $data = array_merge(['task' => $taskName, 'time' => $timestamp], $extra);
            $line = json_encode($data) . PHP_EOL;
            file_put_contents($logFile, $line, FILE_APPEND);
            echo "[{$timestamp}] {$taskName} executed\n";
        };

        // Test 1: Every minute
        $scheduler->call(function () use ($logTask) {
            $logTask('every_minute');
        })->everyMinute()->description('Test: Every minute');

        // Test 2: Every 5 minutes
        $scheduler->call(function () use ($logTask) {
            $logTask('every_5_minutes');
        })->everyMinutes(5)->description('Test: Every 5 minutes');

        // Test 3: Hourly
        $scheduler->call(function () use ($logTask) {
            $logTask('hourly');
        })->hourly()->description('Test: Hourly');

        // Test 4: Daily
        $scheduler->call(function () use ($logTask) {
            $logTask('daily');
        })->daily()->description('Test: Daily at midnight');

        // Test 5: Weekdays only
        $scheduler->call(function () use ($logTask) {
            $logTask('weekdays', ['day' => date('l')]);
        })->weekdays()->everyMinute()->description('Test: Weekdays only');

        // Test 6: Weekends only
        $scheduler->call(function () use ($logTask) {
            $logTask('weekends', ['day' => date('l')]);
        })->weekends()->everyMinute()->description('Test: Weekends only');

        // Test 7: Conditional when()
        $scheduler->call(function () use ($logTask) {
            $logTask('when_condition', ['hour' => date('H')]);
        })->everyMinute()
            ->when(fn() => (int)date('H') >= 9 && (int)date('H') < 18)
            ->description('Test: When condition (9-18)');

        // Test 8: Skip condition
        $scheduler->call(function () use ($logTask) {
            $logTask('skip_condition', ['hour' => date('H')]);
        })->everyMinute()
            ->skip(fn() => (int)date('H') >= 23 || (int)date('H') < 6)
            ->description('Test: Skip late night (23-06)');

        // Test 9: Between time range
        $scheduler->call(function () use ($logTask) {
            $logTask('between_time');
        })->everyMinute()
            ->between('09:00', '17:00')
            ->description('Test: Between 9-17');

        // Test 10: Without overlapping
        $scheduler->call(function () use ($logTask) {
            $logTask('no_overlap_start');
            sleep(90);
            $logTask('no_overlap_end');
        })->everyMinute()
            ->withoutOverlapping(120)
            ->description('Test: Without overlapping');

        // Test 11: Run in background
        $scheduler->call(function () use ($logTask) {
            $logTask('background');
            sleep(5);
        })->everyMinute()
            ->runInBackground()
            ->description('Test: Background execution');

        // Test 12: Before/after callbacks
        $scheduler->call(function () use ($logTask) {
            $logTask('main');
        })->everyMinute()
            ->before(fn() => $logTask('before'))
            ->after(fn() => $logTask('after'))
            ->description('Test: Before/after callbacks');

        // Test 13: Success callback
        $scheduler->call(function () use ($logTask) {
            $logTask('success_task');
            return true;
        })->everyMinute()
            ->onSuccess(fn() => $logTask('on_success'))
            ->description('Test: Success callback');

        // Test 14: Failure callback
        $scheduler->call(function () use ($logTask) {
            $logTask('failure_attempt');
            throw new \Exception('Test failure');
        })->everyMinute()
            ->onFailure(fn($e) => $logTask('on_failure', ['error' => $e->getMessage()]))
            ->description('Test: Failure callback');

        // Test 15: Output to file
        $scheduler->call(function () {
            echo "Output line 1\n";
            echo "Time: " . date('H:i:s') . "\n";
        })->everyMinute()
            ->sendOutputTo($logDir . '/output.log')
            ->description('Test: Output to file');

        // Test 16: Append output
        $scheduler->call(function () {
            echo "[" . date('H:i:s') . "] Appended\n";
        })->everyMinute()
            ->appendOutputTo($logDir . '/append.log')
            ->description('Test: Append output');

        // Test 17: Retry on failure
        $scheduler->call(function () use ($logTask) {
            static $attempts = 0;
            $attempts++;
            $logTask('retry', ['attempt' => $attempts]);
            if ($attempts < 3) throw new \Exception("Attempt {$attempts}");
        })->everyMinute()
            ->retry(3, 5, false)
            ->description('Test: Retry 3 times');

        // Test 18: Priority
        $scheduler->call(function () use ($logTask) {
            $logTask('high_priority', ['p' => 100]);
        })->everyMinute()->priority(100)->description('Test: High priority');

        $scheduler->call(function () use ($logTask) {
            $logTask('low_priority', ['p' => 1]);
        })->everyMinute()->priority(1)->description('Test: Low priority');

        // Test 19: Timezone
        $scheduler->call(function () use ($logTask) {
            $logTask('timezone', ['tz' => 'Asia/Ho_Chi_Minh']);
        })->dailyAt('14:00')
            ->timezone('Asia/Ho_Chi_Minh')
            ->description('Test: Custom timezone');

        // Test 20: Complex combined features
        $scheduler->call(function () use ($logTask) {
            $logTask('complex', ['features' => 'multiple']);
        })->everyMinute()
            ->between('08:00', '20:00')
            ->withoutOverlapping()
            ->before(fn() => $logTask('complex_before'))
            ->onSuccess(fn() => $logTask('complex_success'))
            ->priority(50)
            ->description('Test: Complex multi-feature task');

        // ========================================================================
        // PRODUCTION SCHEDULED TASKS
        // ========================================================================

        // Order tracking consumer - runs every minute
        $scheduler->command('order:track')
            ->everyMinute()
            ->withoutOverlapping(120) // Prevent overlapping (expires after 2 hours)
            ->runInBackground() // Run in background to not block other tasks
            ->description('Consume order tracking events from Kafka');
    }
}
