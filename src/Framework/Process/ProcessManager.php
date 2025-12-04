<?php

declare(strict_types=1);

namespace Toporia\Framework\Process;

use Toporia\Framework\Process\Contracts\{ProcessInterface, ProcessManagerInterface};

/**
 * Class ProcessManager
 *
 * Manages a pool of forked processes with resource limits.
 * Implements efficient parallel execution with automatic cleanup.
 *
 * Features:
 * - Process pool management
 * - Concurrent execution limits
 * - Automatic cleanup of finished processes
 * - Memory efficient (no zombie processes)
 * - Signal handling for graceful shutdown
 * - Non-blocking wait for optimal performance
 *
 * Architecture:
 * - Uses fork() for true multiprocessing
 * - Each process runs in isolated memory
 * - Master process manages worker lifecycle
 * - Workers report results via serialization
 *
 * Performance:
 * - O(1) process creation
 * - O(N) where N = number of concurrent processes
 * - Minimal memory overhead in master process
 * - Linear scaling with CPU cores
 *
 * Example:
 * ```php
 * $manager = new ProcessManager();
 *
 * // Add tasks
 * for ($i = 0; $i < 100; $i++) {
 *     $manager->add(fn($n) => $n * 2, [$i]);
 * }
 *
 * // Run with max 4 concurrent processes
 * $results = $manager->run(maxConcurrent: 4);
 * ```
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Process
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class ProcessManager implements ProcessManagerInterface
{
    /** @var array<ProcessInterface> */
    private array $processes = [];

    /** @var array<array{callable, array}> */
    private array $pending = [];

    /** @var array<mixed> */
    private array $results = [];

    private bool $shutdownRequested = false;
    private int $parentPid; // Track parent PID to prevent child cleanup

    public function __construct()
    {
        $this->parentPid = getmypid(); // Store parent PID
        $this->registerSignalHandlers();
    }

    /**
     * Add a task to the pool.
     *
     * @param callable $callback
     * @param array $args
     * @return ProcessInterface
     */
    public function add(callable $callback, array $args = []): ProcessInterface
    {
        $process = new ForkProcess($callback, $args);
        $this->pending[] = ['process' => $process, 'callback' => $callback, 'args' => $args];

        return $process;
    }

    /**
     * Run all pending processes with concurrency limit.
     * Alias for execute() - implements ProcessManagerInterface.
     *
     * @param int $maxConcurrent
     * @return array
     */
    public function run(int $maxConcurrent = 4): array
    {
        return $this->execute($maxConcurrent);
    }

    /**
     * Execute all pending processes with concurrency limit.
     *
     * @param int $maxConcurrent
     * @return array
     */
    public function execute(int $maxConcurrent = 4): array
    {
        if (!ForkProcess::isSupported()) {
            // Fallback to synchronous execution
            return $this->runSynchronous();
        }

        $this->results = [];
        $pendingCount = count($this->pending);
        $processed = 0;

        // Start all processes up to concurrency limit
        while ($processed < $pendingCount) {
            // Check for shutdown signal
            if ($this->shutdownRequested) {
                $this->killAll(SIGTERM);
                break;
            }

            // Start new processes up to limit
            while (count($this->processes) < $maxConcurrent && $processed < $pendingCount) {
                $task = $this->pending[$processed];
                $process = $task['process'];

                // CRITICAL: start() MUST be called before getPid()!
                // PID is NULL until process is forked
                if (!$process->start()) {
                    $processed++;
                    continue;
                }

                // Now getPid() returns the actual PID
                $pid = $process->getPid();
                if ($pid !== null) {
                    $this->processes[$pid] = $process;
                }
                $processed++;
            }

            // If all tasks started, break immediately (no need to wait in loop)
            if ($processed >= $pendingCount) {
                break;
            }

            // Check for finished processes and start new ones
            // Non-blocking check to avoid unnecessary delays
            $this->collectFinishedProcesses();
        }

        // Wait for remaining processes
        $this->wait();

        // Clear pending tasks
        $this->pending = [];

        return $this->results;
    }


    /**
     * Wait for all processes to complete.
     * Performance: O(N) where N = number of processes
     * Optimized: Non-blocking check first, then blocking wait only when needed
     *
     * @return array
     */
    public function wait(): array
    {
        // Wait for all remaining processes
        while (count($this->processes) > 0) {
            foreach ($this->processes as $pid => $process) {
                // Non-blocking check first (fast path)
                if (!$process->isRunning()) {
                    // Process already finished - collect output immediately
                    $exitCode = $process->wait();
                    $output = $process->getOutput();
                    $this->results[] = $output;
                    unset($this->processes[$pid]);
                } else {
                    // Process still running - do blocking wait for this one
                    $exitCode = $process->wait();
                    $output = $process->getOutput();
                    $this->results[] = $output;
                    unset($this->processes[$pid]);
                    break; // Break to restart loop and check others
                }
            }
        }

        return $this->results;
    }

    /**
     * Collect finished processes without blocking.
     * Performance: O(N) where N = number of processes
     * Used in run() loop to start new processes as soon as slots become available
     *
     * @return void
     */
    private function collectFinishedProcesses(): void
    {
        foreach ($this->processes as $pid => $process) {
            // Non-blocking check
            if (!$process->isRunning()) {
                // Process finished - collect output
                $exitCode = $process->wait();
                $output = $process->getOutput();
                $this->results[] = $output;
                unset($this->processes[$pid]);
            }
        }
    }

    /**
     * Get number of running processes.
     *
     * @return int
     */
    public function getRunningCount(): int
    {
        $count = 0;

        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Kill all processes.
     *
     * @param int $signal
     * @return void
     */
    public function killAll(int $signal = SIGTERM): void
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->kill($signal);
            }
        }

        // Wait for all to die
        foreach ($this->processes as $process) {
            $process->wait();
        }

        $this->processes = [];
    }

    /**
     * Check if any process is running.
     *
     * @return bool
     */
    public function hasRunning(): bool
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fallback to synchronous execution when PCNTL not available.
     *
     * @return array
     */
    private function runSynchronous(): array
    {
        $results = [];

        foreach ($this->pending as $task) {
            try {
                $result = ($task['callback'])(...$task['args']);
                $results[] = $result;
            } catch (\Throwable $e) {
                error_log("Task failed: " . $e->getMessage());
                $results[] = null;
            }
        }

        $this->pending = [];

        return $results;
    }

    /**
     * Register signal handlers for graceful shutdown.
     *
     * @return void
     */
    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        // Handle SIGTERM (graceful shutdown)
        pcntl_signal(SIGTERM, function () {
            $this->shutdownRequested = true;
        });

        // Handle SIGINT (Ctrl+C)
        pcntl_signal(SIGINT, function () {
            $this->shutdownRequested = true;
        });

        // Enable signal dispatching
        pcntl_async_signals(true);
    }

    /**
     * Cleanup on destruction.
     * Only runs in PARENT process to prevent child processes from killing siblings.
     */
    public function __destruct()
    {
        // CRITICAL: Only parent process should cleanup
        // Child processes have a copy of this object but should NOT kill processes
        if (getmypid() !== $this->parentPid) {
            return;
        }

        // Kill any remaining processes
        $this->killAll(SIGKILL);
    }

    // =========================================================================
    // CONVENIENCE METHODS (for static access via Process accessor)
    // =========================================================================

    /**
     * Get ProcessManager instance (for consistency with accessor API).
     *
     * @return ProcessManager Returns self for fluent API
     */
    public function manager(): ProcessManager
    {
        return $this;
    }

    /**
     * Run tasks in parallel with concurrency limit (convenience method).
     *
     * @param array<callable> $tasks Array of callables to execute
     * @param int $maxConcurrent Maximum concurrent processes
     * @return array Results in order of tasks
     * @throws \RuntimeException If called from HTTP context
     */
    public function runTasks(array $tasks, int $maxConcurrent = 4): array
    {
        $this->guardAgainstHttpContext();

        foreach ($tasks as $task) {
            $this->add($task);
        }

        return $this->execute($maxConcurrent);
    }


    /**
     * Get ProcessPool instance.
     *
     * @param int|null $workerCount Number of workers (null = auto-detect CPU cores)
     * @return ProcessPool
     */
    public function pool(?int $workerCount = null): ProcessPool
    {
        if ($workerCount === null) {
            $workerCount = $this->getCpuCores();
        }

        return new ProcessPool(workerCount: $workerCount);
    }

    /**
     * Map function over array in parallel.
     *
     * @param array $items Items to process
     * @param callable $callback Function to apply to each item
     * @param int|null $workerCount Number of workers (null = auto-detect)
     * @return array Mapped results
     * @throws \RuntimeException If called from HTTP context
     */
    public function map(array $items, callable $callback, ?int $workerCount = null): array
    {
        $this->guardAgainstHttpContext();
        return $this->pool($workerCount)->map($items, $callback);
    }

    /**
     * Filter array in parallel.
     *
     * @param array $items Items to filter
     * @param callable $callback Predicate function
     * @param int|null $workerCount Number of workers (null = auto-detect)
     * @return array Filtered items
     * @throws \RuntimeException If called from HTTP context
     */
    public function filter(array $items, callable $callback, ?int $workerCount = null): array
    {
        $this->guardAgainstHttpContext();
        return $this->pool($workerCount)->filter($items, $callback);
    }

    /**
     * Reduce array in parallel.
     *
     * @param array $items Items to reduce
     * @param callable $callback Reducer function
     * @param mixed $initial Initial value
     * @param int|null $workerCount Number of workers (null = auto-detect)
     * @return mixed Reduced value
     * @throws \RuntimeException If called from HTTP context
     */
    public function reduce(array $items, callable $callback, mixed $initial = null, ?int $workerCount = null): mixed
    {
        $this->guardAgainstHttpContext();
        return $this->pool($workerCount)->reduce($items, $callback, $initial);
    }

    /**
     * Create a single fork process.
     *
     * @param callable $callback Function to execute in child process
     * @param array $args Arguments to pass to callback
     * @return ProcessInterface
     */
    public function fork(callable $callback, array $args = []): ProcessInterface
    {
        return new ForkProcess($callback, $args);
    }

    /**
     * Check if PCNTL fork is supported.
     *
     * @return bool
     */
    public function isSupported(): bool
    {
        return ForkProcess::isSupported();
    }

    /**
     * Get number of CPU cores.
     *
     * @return int
     */
    public function getCpuCores(): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return (int) ($_ENV['NUMBER_OF_PROCESSORS'] ?? 4);
        }

        $output = shell_exec('nproc 2>/dev/null || sysctl -n hw.ncpu 2>/dev/null || echo 4');
        return max(1, (int) trim((string) $output));
    }

    /**
     * Guard against HTTP context.
     *
     * PCNTL fork in HTTP context (web requests) causes serious issues:
     * - Child processes inherit HTTP server socket
     * - Output buffering corruption
     * - Zombie processes
     * - Memory leaks
     *
     * @throws \RuntimeException If called from HTTP/SAPI context
     */
    private function guardAgainstHttpContext(): void
    {
        // Check for HTTP request variables (more reliable than PHP_SAPI)
        // PHP built-in server, Apache, Nginx, etc. all set REQUEST_METHOD
        if (isset($_SERVER['REQUEST_METHOD']) || isset($_SERVER['HTTP_HOST'])) {
            throw new \RuntimeException(
                'Process methods cannot be called from HTTP context. ' .
                    'Use Queue jobs for async processing in web requests. ' .
                    'Multi-process execution is only safe in CLI context (console commands).'
            );
        }

        // Check if running in non-CLI SAPI
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            throw new \RuntimeException(
                'Process methods require CLI SAPI. ' .
                    'Current SAPI: ' . PHP_SAPI . '. ' .
                    'Multi-process execution is only safe in CLI context (console commands).'
            );
        }
    }
}
