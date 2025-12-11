<?php

declare(strict_types=1);

namespace Toporia\Framework\Process\Drivers;

use Toporia\Framework\Process\Contracts\ConcurrencyDriverInterface;
use Toporia\Framework\Process\ForkProcess;
use Toporia\Framework\Process\ProcessManager;

/**
 * Fork Driver
 *
 * Uses pcntl_fork() for true parallel execution.
 * Best for CPU-intensive tasks on Unix systems.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 */
final class ForkDriver implements ConcurrencyDriverInterface
{
    /** @var array<callable> Deferred tasks */
    private static array $deferredTasks = [];

    /** @var bool Whether shutdown handler is registered */
    private static bool $shutdownRegistered = false;

    public function __construct(
        private readonly int $maxConcurrent = 4
    ) {}

    /**
     * {@inheritdoc}
     */
    public function run(array $tasks, float $timeout = 0): array
    {
        if (empty($tasks)) {
            return [];
        }

        // Check if tasks have string keys
        $hasStringKeys = array_keys($tasks) !== range(0, count($tasks) - 1);
        $keys = array_keys($tasks);

        // Use runTasks which has timeout support
        $manager = new ProcessManager();
        $effectiveTimeout = $timeout > 0 ? $timeout : 30.0; // Default 30s if not specified
        $results = $manager->runTasks($tasks, $this->maxConcurrent, $effectiveTimeout);

        // runTasks already preserves keys, but ensure order
        if ($hasStringKeys) {
            $keyedResults = [];
            foreach ($keys as $key) {
                $keyedResults[$key] = $results[$key] ?? null;
            }
            return $keyedResults;
        }

        // For indexed arrays, ensure order
        $orderedResults = [];
        foreach ($keys as $index => $key) {
            $orderedResults[] = $results[$key] ?? ($results[$index] ?? null);
        }

        return $orderedResults;
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
     * Called automatically on shutdown.
     *
     * @internal
     */
    public static function executeDeferredTasks(): void
    {
        if (empty(self::$deferredTasks)) {
            return;
        }

        // Flush output to client first
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            // Fallback for non-FPM environments
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }

        // Execute deferred tasks
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
        return ForkProcess::isSupported();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'fork';
    }
}
