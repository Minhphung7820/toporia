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
     * @throws \RuntimeException If called from HTTP context
     */
    public function execute(int $maxConcurrent = 4): array
    {
        // Guard against HTTP context - fork causes serious issues in web requests
        $this->guardAgainstHttpContext();

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
     * Uses non-blocking wait with WNOHANG to prevent blocking issues.
     *
     * @return array
     */
    public function wait(): array
    {
        // Wait for all remaining processes using non-blocking approach
        while (count($this->processes) > 0) {
            // Dispatch pending signals
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            // Check for shutdown
            if ($this->shutdownRequested) {
                $this->killAllChildren();
                break;
            }

            foreach ($this->processes as $pid => $process) {
                // Use pcntl_waitpid with WNOHANG for non-blocking check
                $result = pcntl_waitpid($pid, $status, WNOHANG);

                if ($result === $pid) {
                    // Process finished - set exit code BEFORE calling wait()
                    // This prevents wait() from calling pcntl_waitpid again (double-wait)
                    $process->setExitCode(pcntl_wexitstatus($status));
                    $process->wait(); // Now just collects output
                    $this->results[] = $process->getOutput();
                    unset($this->processes[$pid]);
                } elseif ($result === -1) {
                    // Error or already reaped - set exit code and collect output
                    $process->setExitCode(0);
                    $process->wait();
                    $this->results[] = $process->getOutput();
                    unset($this->processes[$pid]);
                }
                // result === 0 means still running, continue
            }

            // Small delay to prevent busy-waiting
            if (!empty($this->processes)) {
                usleep(5000); // 5ms
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
            // Use pcntl_waitpid with WNOHANG directly to avoid double-reap
            $result = pcntl_waitpid($pid, $status, WNOHANG);

            if ($result === $pid) {
                // Process finished - set exit code and collect output
                $process->setExitCode(pcntl_wexitstatus($status));
                $process->wait(); // Just collects output now
                $this->results[] = $process->getOutput();
                unset($this->processes[$pid]);
            } elseif ($result === -1) {
                // Error or already reaped
                $process->setExitCode(0);
                $process->wait();
                $this->results[] = $process->getOutput();
                unset($this->processes[$pid]);
            }
            // result === 0 means still running
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
            $this->killAllChildren();
        });

        // Handle SIGINT (Ctrl+C) - immediate cleanup
        pcntl_signal(SIGINT, function () {
            $this->shutdownRequested = true;
            $this->killAllChildren();
        });

        // Enable signal dispatching
        pcntl_async_signals(true);
    }

    /**
     * Kill all child processes immediately.
     * Called from signal handler for fast cleanup.
     *
     * @return void
     */
    private function killAllChildren(): void
    {
        foreach ($this->processes as $pid => $process) {
            if ($process->isRunning()) {
                // Send SIGTERM first for graceful shutdown
                posix_kill($pid, SIGTERM);
            }
        }

        // Give children 100ms to cleanup
        usleep(100000);

        // Force kill any remaining
        foreach ($this->processes as $pid => $process) {
            if ($process->isRunning()) {
                posix_kill($pid, SIGKILL);
            }
            // Reap zombie processes
            pcntl_waitpid($pid, $status, WNOHANG);
        }

        $this->processes = [];
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
     * Supports both indexed and associative arrays:
     * - Indexed: Results returned in same order
     * - Associative: Results keyed by original keys
     *
     * @param array<string|int, callable> $tasks Array of callables to execute
     * @param int $maxConcurrent Maximum concurrent processes
     * @param float $timeout Maximum time in seconds (0 = no timeout)
     * @return array<string|int, mixed> Results (preserves keys if associative)
     * @throws \RuntimeException If called from HTTP context or timeout exceeded
     */
    public function runTasks(array $tasks, int $maxConcurrent = 4, float $timeout = 30.0): array
    {
        $this->guardAgainstHttpContext();

        // Check if tasks have string keys (associative array)
        $keys = array_keys($tasks);
        $hasStringKeys = $keys !== range(0, count($tasks) - 1);

        foreach ($tasks as $task) {
            $this->add($task);
        }

        $results = $this->executeWithTimeout($maxConcurrent, $timeout);

        // Restore original keys if associative
        if ($hasStringKeys) {
            $keyedResults = [];
            foreach ($keys as $index => $key) {
                $keyedResults[$key] = $results[$index] ?? null;
            }
            return $keyedResults;
        }

        return $results;
    }

    /**
     * Execute with timeout support.
     *
     * @param int $maxConcurrent
     * @param float $timeout Timeout in seconds (0 = no timeout)
     * @return array
     */
    private function executeWithTimeout(int $maxConcurrent, float $timeout): array
    {
        if (!ForkProcess::isSupported()) {
            return $this->runSynchronous();
        }

        $this->results = [];
        $pendingCount = count($this->pending);
        $processed = 0;
        $startTime = microtime(true);

        // Start all processes up to concurrency limit
        while ($processed < $pendingCount) {
            // Dispatch pending signals (critical for Ctrl+C)
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            // Check for shutdown signal
            if ($this->shutdownRequested) {
                $this->killAllChildren();
                break;
            }

            // Check timeout
            if ($timeout > 0 && (microtime(true) - $startTime) > $timeout) {
                $this->killAllChildren();
                error_log("Process::runTasks timeout after {$timeout}s");
                break;
            }

            // Start new processes up to limit
            while (count($this->processes) < $maxConcurrent && $processed < $pendingCount) {
                $task = $this->pending[$processed];
                $process = $task['process'];

                if (!$process->start()) {
                    $processed++;
                    continue;
                }

                $pid = $process->getPid();
                if ($pid !== null) {
                    $this->processes[$pid] = $process;
                }
                $processed++;
            }

            // If all tasks started, break immediately
            if ($processed >= $pendingCount) {
                break;
            }

            // Check for finished processes and start new ones
            $this->collectFinishedProcesses();

            // Small delay to prevent busy-waiting
            usleep(1000); // 1ms
        }

        // Wait for remaining processes (with timeout)
        $remainingTimeout = $timeout > 0 ? max(0, $timeout - (microtime(true) - $startTime)) : 0;
        $this->waitWithTimeout($remainingTimeout);

        // Clear pending tasks
        $this->pending = [];

        return $this->results;
    }

    /**
     * Wait for processes with timeout.
     *
     * @param float $remainingTimeout Remaining timeout in seconds (0 = no timeout)
     * @return void
     */
    private function waitWithTimeout(float $remainingTimeout): void
    {
        $startTime = microtime(true);

        while (count($this->processes) > 0) {
            // Dispatch pending signals (critical for Ctrl+C to work)
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            // Check timeout
            if ($remainingTimeout > 0 && (microtime(true) - $startTime) > $remainingTimeout) {
                $this->killAllChildren();
                break;
            }

            // Check for shutdown signal
            if ($this->shutdownRequested) {
                $this->killAllChildren();
                break;
            }

            // Use pcntl_waitpid with WNOHANG for non-blocking check
            foreach ($this->processes as $pid => $process) {
                // Non-blocking wait check
                $result = pcntl_waitpid($pid, $status, WNOHANG);

                if ($result === $pid) {
                    // Process finished - set exit code BEFORE calling wait()
                    $process->setExitCode(pcntl_wexitstatus($status));
                    $process->wait(); // Now just collects output
                    $this->results[] = $process->getOutput();
                    unset($this->processes[$pid]);
                } elseif ($result === -1) {
                    // Error or already reaped - set exit code and collect output
                    $process->setExitCode(0);
                    $process->wait();
                    $this->results[] = $process->getOutput();
                    unset($this->processes[$pid]);
                }
                // result === 0 means still running, continue
            }

            // Small delay to prevent busy-waiting, but allow signal dispatch
            if (!empty($this->processes)) {
                usleep(10000); // 10ms - longer delay, less CPU
            }
        }
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
