<?php

declare(strict_types=1);

namespace Toporia\Framework\Process;

use Toporia\Framework\Process\Contracts\ProcessInterface;

/**
 * Class ForkProcess
 *
 * Fork-based process using PCNTL for true parallel execution.
 * Each process runs in its own memory space - zero memory sharing.
 *
 * Features:
 * - True multiprocessing with fork()
 * - Memory isolated (each process has own memory)
 * - Shared-nothing architecture
 * - Inter-process communication via serialization
 * - Signal handling for graceful shutdown
 *
 * Performance:
 * - O(1) fork operation
 * - Zero memory overhead between processes
 * - CPU-bound tasks scale linearly with cores
 *
 * Requirements:
 * - PHP PCNTL extension
 * - Unix-like OS (Linux, macOS)
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
final class ForkProcess implements ProcessInterface
{
    /**
     * Track which PIDs have been collected to prevent double-collection.
     * Limited to last 1000 PIDs to prevent memory leak.
     * @var array<string, bool>
     */
    private static array $collected = [];

    private ?int $pid = null;
    private ?int $exitCode = null;
    private mixed $output = null;
    private bool $started = false;
    private $callback; // Cannot use 'callable' type in PHP 8.1 properties
    private array $args;
    private array $pipes = [];

    public function __construct(
        callable $callback,
        array $args = []
    ) {
        $this->callback = $callback;
        $this->args = $args;
    }

    /**
     * Start the process by forking.
     *
     * @return bool
     */
    public function start(): bool
    {
        if ($this->started) {
            return false;
        }

        if (!function_exists('pcntl_fork')) {
            throw new \RuntimeException('PCNTL extension is required for multiprocessing');
        }

        // Create pipe for IPC (Inter-Process Communication)
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pipes === false) {
            throw new \RuntimeException('Failed to create IPC pipe');
        }

        [$parentPipe, $childPipe] = $pipes;

        $pid = pcntl_fork();

        if ($pid === -1) {
            fclose($parentPipe);
            fclose($childPipe);
            throw new \RuntimeException('Failed to fork process');
        }

        if ($pid === 0) {
            // Child process
            fclose($parentPipe);
            $this->runChild($childPipe);
            fclose($childPipe);
            exit(0);
        }

        // Parent process
        fclose($childPipe);
        $this->pid = $pid;
        $this->pipes[$pid] = $parentPipe;
        $this->started = true;

        return true;
    }

    /**
     * Check if process is running.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        if ($this->pid === null) {
            return false;
        }

        if ($this->exitCode !== null) {
            return false;
        }

        // Check if process is still alive (non-blocking)
        $result = pcntl_waitpid($this->pid, $status, WNOHANG);

        if ($result === 0) {
            // Still running
            return true;
        }

        if ($result === $this->pid) {
            // Process finished - store exit code but DON'T collect output yet
            // Output collection happens in wait() after blocking wait ensures child is fully terminated
            $this->exitCode = pcntl_wexitstatus($status);
            return false;
        }

        return false;
    }

    /**
     * Wait for process to finish.
     * If process was already reaped externally, just collect output.
     *
     * @return int Exit code
     */
    public function wait(): int
    {
        if ($this->pid === null) {
            return -1;
        }

        if ($this->exitCode === null) {
            // Try non-blocking wait first to check if already reaped
            $status = 0;
            $result = pcntl_waitpid($this->pid, $status, WNOHANG);

            if ($result === $this->pid) {
                // Process finished, got status
                $this->exitCode = pcntl_wexitstatus($status);
            } elseif ($result === 0) {
                // Still running - do blocking wait
                pcntl_waitpid($this->pid, $status, 0);
                $this->exitCode = pcntl_wexitstatus($status);
            } else {
                // result === -1: Already reaped or error
                // Set exit code to 0 (assume success) since we can't get real status
                $this->exitCode = 0;
            }
        }

        // Always collect output (whether we just waited or it was already reaped)
        $this->collectOutput();

        return $this->exitCode;
    }

    /**
     * Get process ID.
     *
     * @return int|null
     */
    public function getPid(): ?int
    {
        return $this->pid;
    }

    /**
     * Get exit code.
     *
     * @return int|null
     */
    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    /**
     * Set exit code (used when process is reaped externally).
     *
     * @param int $exitCode
     * @return void
     */
    public function setExitCode(int $exitCode): void
    {
        $this->exitCode = $exitCode;
    }

    /**
     * Kill the process.
     *
     * @param int $signal
     * @return bool
     */
    public function kill(int $signal = SIGTERM): bool
    {
        if ($this->pid === null) {
            return false;
        }

        $result = posix_kill($this->pid, $signal);

        // Cleanup pipe if exists
        if (isset($this->pipes[$this->pid])) {
            fclose($this->pipes[$this->pid]);
            unset($this->pipes[$this->pid]);
        }

        return $result;
    }

    /**
     * Get process output.
     *
     * @return mixed
     */
    public function getOutput(): mixed
    {
        return $this->output;
    }

    /**
     * Run child process logic.
     *
     * @param resource $pipe
     * @return void
     */
    private function runChild($pipe): void
    {
        try {
            // Execute callback in child process
            $result = ($this->callback)(...$this->args);

            // Serialize result for parent
            $serialized = serialize($result);

            // Write to pipe for parent to read
            fwrite($pipe, $serialized);

            // CRITICAL: Flush pipe buffer to ensure data is available for parent
            fflush($pipe);
        } catch (\Throwable $e) {
            // Send error to parent
            $error = ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
            fwrite($pipe, serialize($error));
            fflush($pipe);
            exit(1);
        }
    }

    /**
     * Collect output from child process via pipe.
     * Performance: O(1) - single read operation with timeout
     *
     * @return void
     */
    private function collectOutput(): void
    {
        $pidKey = (string) $this->pid;

        // CRITICAL: Only PARENT process should collect output from CHILD
        // If current process == child PID, we're IN the child and should NOT collect
        if (getmypid() === $this->pid) {
            return;
        }

        // Prevent double collection using static PID tracking
        if (isset(self::$collected[$pidKey])) {
            return;
        }

        // Mark as collected IMMEDIATELY
        self::$collected[$pidKey] = true;

        // Prevent memory leak: keep only last 1000 PIDs
        if (count(self::$collected) > 1000) {
            self::$collected = array_slice(self::$collected, -500, null, true);
        }

        if (!isset($this->pipes[$this->pid])) {
            return;
        }

        $pipe = $this->pipes[$this->pid];

        // Read output - use blocking mode for simplicity and speed
        // Child process has already exited, so data is ready
        stream_set_blocking($pipe, true);

        // Set read timeout (100ms should be enough for buffered data)
        stream_set_timeout($pipe, 0, 100000);

        // Read all available data at once
        $serialized = stream_get_contents($pipe);

        // Check for timeout (shouldn't happen with exited process)
        $meta = stream_get_meta_data($pipe);
        if ($meta['timed_out']) {
            error_log("Process output read timed out for PID: {$this->pid}");
        }

        // Close and cleanup
        fclose($pipe);
        unset($this->pipes[$this->pid]);

        // Deserialize output
        // SECURITY: Restrict unserialize to prevent PHP Object Injection attacks
        if ($serialized !== '') {
            try {
                $this->output = unserialize($serialized, ['allowed_classes' => false]);
            } catch (\Throwable $e) {
                $this->output = null;
                error_log("Failed to deserialize process output: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if PCNTL is available.
     *
     * @return bool
     */
    public static function isSupported(): bool
    {
        return function_exists('pcntl_fork')
            && function_exists('pcntl_waitpid')
            && function_exists('posix_kill');
    }
}
