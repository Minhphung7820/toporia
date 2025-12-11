<?php

declare(strict_types=1);

namespace Toporia\Framework\Process\Drivers;

use Toporia\Framework\Process\Contracts\ConcurrencyDriverInterface;

/**
 * Sync Driver
 *
 * Executes tasks sequentially (no parallelism).
 * Useful for testing, debugging, or platforms without fork support.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 */
final class SyncDriver implements ConcurrencyDriverInterface
{
    /** @var array<callable> Deferred tasks */
    private static array $deferredTasks = [];

    /** @var bool Whether shutdown handler is registered */
    private static bool $shutdownRegistered = false;

    /**
     * {@inheritdoc}
     */
    public function run(array $tasks, float $timeout = 0): array
    {
        $results = [];
        $startTime = microtime(true);

        foreach ($tasks as $key => $task) {
            // Check timeout (only if > 0)
            if ($timeout > 0 && (microtime(true) - $startTime) > $timeout) {
                error_log("Sync driver timeout after {$timeout}s");
                $results[$key] = ['error' => 'Timeout exceeded'];
                continue;
            }

            try {
                $results[$key] = $task();
            } catch (\Throwable $e) {
                error_log("Sync task failed: " . $e->getMessage());
                $results[$key] = ['error' => $e->getMessage()];
            }
        }

        return $results;
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

        // Flush output first
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
        return true; // Always supported
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'sync';
    }
}
