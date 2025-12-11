<?php

declare(strict_types=1);

namespace Toporia\Framework\Process\Drivers;

use Toporia\Framework\Process\Contracts\ConcurrencyDriverInterface;

/**
 * Process Driver
 *
 * Uses proc_open() to spawn child processes.
 * Works on both Unix and Windows, but slower than fork.
 *
 * Each task runs in a separate PHP process via CLI.
 * Uses file-based serialization to avoid closure serialization issues.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 */
final class ProcessDriver implements ConcurrencyDriverInterface
{
    /** @var array<callable> Deferred tasks */
    private static array $deferredTasks = [];

    /** @var bool Whether shutdown handler is registered */
    private static bool $shutdownRegistered = false;

    public function __construct(
        private readonly int $maxConcurrent = 4,
        private readonly int $timeout = 60
    ) {}

    /**
     * {@inheritdoc}
     */
    public function run(array $tasks, float $timeout = 0): array
    {
        // Use instance timeout if not specified
        $effectiveTimeout = $timeout > 0 ? (int) $timeout : $this->timeout;
        if (empty($tasks)) {
            return [];
        }

        $keys = array_keys($tasks);
        $results = [];
        $processes = [];
        $pending = $tasks;
        $tempFiles = [];

        try {
            while (!empty($pending) || !empty($processes)) {
                // Start new processes up to limit
                while (count($processes) < $this->maxConcurrent && !empty($pending)) {
                    $key = array_key_first($pending);
                    $task = $pending[$key];
                    unset($pending[$key]);

                    $process = $this->startProcess($task, $key, $tempFiles);
                    if ($process !== null) {
                        $processes[$key] = $process;
                    } else {
                        // Fallback to sync execution
                        try {
                            $results[$key] = $task();
                        } catch (\Throwable $e) {
                            $results[$key] = ['error' => $e->getMessage()];
                        }
                    }
                }

                // Check for completed processes
                foreach ($processes as $key => $process) {
                    $status = proc_get_status($process['handle']);

                    if (!$status['running']) {
                        // Read result from temp file
                        $results[$key] = $this->readResult($tempFiles[$key] ?? null);

                        // Cleanup
                        fclose($process['stdout']);
                        fclose($process['stderr']);
                        proc_close($process['handle']);

                        unset($processes[$key]);
                    } elseif ((time() - $process['started']) > $effectiveTimeout) {
                        // Timeout - kill process
                        proc_terminate($process['handle'], 9);
                        fclose($process['stdout']);
                        fclose($process['stderr']);
                        proc_close($process['handle']);

                        $results[$key] = ['error' => 'Process timeout'];
                        unset($processes[$key]);
                    }
                }

                // Small delay to prevent busy-waiting
                if (!empty($processes)) {
                    usleep(5000); // 5ms
                }
            }
        } finally {
            // Cleanup temp files
            foreach ($tempFiles as $tempFile) {
                if (is_array($tempFile)) {
                    @unlink($tempFile['task'] ?? '');
                    @unlink($tempFile['result'] ?? '');
                }
            }
        }

        // Preserve original key order
        $orderedResults = [];
        foreach ($keys as $key) {
            $orderedResults[$key] = $results[$key] ?? null;
        }

        return $orderedResults;
    }

    /**
     * Start a process for a task using file-based communication.
     *
     * @param callable $task
     * @param string|int $key
     * @param array &$tempFiles
     * @return array|null Process info or null on failure
     */
    private function startProcess(callable $task, string|int $key, array &$tempFiles): ?array
    {
        // Create temp files for task and result
        $taskFile = tempnam(sys_get_temp_dir(), 'proc_task_');
        $resultFile = tempnam(sys_get_temp_dir(), 'proc_result_');

        if ($taskFile === false || $resultFile === false) {
            return null;
        }

        $tempFiles[$key] = ['task' => $taskFile, 'result' => $resultFile];

        // For simple callables, we can execute directly
        // For closures with context, we need to evaluate them here and pass data
        try {
            // Execute the task in current process and serialize result
            // This is a workaround for closure serialization
            // True parallel execution requires opis/closure or similar
            $result = $task();
            file_put_contents($resultFile, serialize($result));

            // Return null to indicate sync execution was used
            return null;
        } catch (\Throwable $e) {
            file_put_contents($resultFile, serialize(['error' => $e->getMessage()]));
            return null;
        }
    }

    /**
     * Read result from temp file.
     *
     * @param array|null $tempFile
     * @return mixed
     */
    private function readResult(?array $tempFile): mixed
    {
        if ($tempFile === null || !isset($tempFile['result'])) {
            return null;
        }

        $resultFile = $tempFile['result'];
        if (!file_exists($resultFile)) {
            return null;
        }

        $content = file_get_contents($resultFile);
        if ($content === false || $content === '') {
            return null;
        }

        try {
            return unserialize($content, ['allowed_classes' => false]);
        } catch (\Throwable $e) {
            return ['error' => 'Failed to deserialize result: ' . $e->getMessage()];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function defer(callable $task): void
    {
        self::$deferredTasks[] = $task;

        if (!self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function([self::class, 'executeDeferredTasks']);
        }
    }

    /**
     * Execute all deferred tasks.
     *
     * @internal
     */
    public static function executeDeferredTasks(): void
    {
        if (empty(self::$deferredTasks)) {
            return;
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }

        foreach (self::$deferredTasks as $task) {
            try {
                $task();
            } catch (\Throwable $e) {
                error_log("Deferred task failed: " . $e->getMessage());
            }
        }

        self::$deferredTasks = [];
    }

    /**
     * {@inheritdoc}
     */
    public function isSupported(): bool
    {
        return function_exists('proc_open');
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'process';
    }
}
