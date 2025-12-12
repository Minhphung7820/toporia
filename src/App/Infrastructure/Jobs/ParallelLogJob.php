<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use Toporia\Framework\Bus\Contracts\ShouldQueueInterface;
use Toporia\Framework\Queue\Job;
use Toporia\Framework\Support\Accessors\Concurrency;
use Toporia\Framework\Support\Accessors\Log;

/**
 * Parallel Log Job
 *
 * Queue job that runs 4 functions in parallel, each writing to log.
 * Demonstrates Concurrency::run() for parallel execution in queue context.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 */
final class ParallelLogJob extends Job implements ShouldQueueInterface
{
    public function __construct(
        private readonly string $jobId = ''
    ) {
        parent::__construct();

        $this->tries(1);
        $this->setTimeout(60);
    }

    public function handle(): void
    {
        $jobId = $this->jobId ?: uniqid('job_');

        Log::info("[{$jobId}] with Concurrency Starting ParallelLogJob with 4 parallel tasks");

        $startTime = microtime(true);

        // Define 4 functions to run in parallel
        $tasks = [
            fn() => $this->logTask1($jobId),
            fn() => $this->logTask2($jobId),
            fn() => $this->logTask3($jobId),
            fn() => $this->logTask4($jobId),
        ];

        // Run all 4 tasks in parallel using Concurrency::run (Toporia-style API)
        $results = Concurrency::run($tasks);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::info("[{$jobId}] ParallelLogJob completed", [
            'duration_ms' => $duration,
            'results' => $results,
        ]);
    }

    /**
     * Task 1: Log user activity
     */
    private function logTask1(string $jobId): array
    {
        $start = microtime(true);

        // Simulate some work
        // usleep(100000); // 100ms

        $message = "Task 1: Processing user activity";
        Log::info("[{$jobId}] {$message}", [
            'task' => 1,
            'pid' => getmypid(),
            'timestamp' => date('Y-m-d H:i:s.u'),
        ]);

        return [
            'task' => 1,
            'message' => $message,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            'pid' => getmypid(),
        ];
    }

    /**
     * Task 2: Log system metrics
     */
    private function logTask2(string $jobId): array
    {
        $start = microtime(true);

        // Simulate some work
        // usleep(150000); // 150ms

        $message = "Task 2: Recording system metrics";
        Log::info("[{$jobId}] {$message}", [
            'task' => 2,
            'pid' => getmypid(),
            'memory_usage' => memory_get_usage(true),
            'timestamp' => date('Y-m-d H:i:s.u'),
        ]);

        return [
            'task' => 2,
            'message' => $message,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            'pid' => getmypid(),
        ];
    }

    /**
     * Task 3: Log security events
     */
    private function logTask3(string $jobId): array
    {
        $start = microtime(true);

        // Simulate some work
        // usleep(200000); // 200ms

        $message = "Task 3: Auditing security events";
        Log::info("[{$jobId}] {$message}", [
            'task' => 3,
            'pid' => getmypid(),
            'ip' => '127.0.0.1',
            'timestamp' => date('Y-m-d H:i:s.u'),
        ]);

        return [
            'task' => 3,
            'message' => $message,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            'pid' => getmypid(),
        ];
    }

    /**
     * Task 4: Log performance data
     */
    private function logTask4(string $jobId): array
    {
        $start = microtime(true);

        // Simulate some work
        // usleep(120000); // 120ms

        $message = "Task 4: Capturing performance data";
        Log::info("[{$jobId}] {$message}", [
            'task' => 4,
            'pid' => getmypid(),
            'cpu_cores' => Concurrency::isForksSupported() ? 'yes' : 'no',
            'timestamp' => date('Y-m-d H:i:s.u'),
        ]);

        return [
            'task' => 4,
            'message' => $message,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            'pid' => getmypid(),
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ParallelLogJob FAILED", [
            'job_id' => $this->jobId,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);
    }
}
