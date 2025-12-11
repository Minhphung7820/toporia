<?php

declare(strict_types=1);

namespace Toporia\Framework\Process;

use Toporia\Framework\Process\Contracts\ConcurrencyDriverInterface;
use Toporia\Framework\Process\Drivers\ForkDriver;
use Toporia\Framework\Process\Drivers\ProcessDriver;
use Toporia\Framework\Process\Drivers\SyncDriver;

/**
 * Concurrency
 *
 * Laravel-compatible concurrency API for running tasks in parallel.
 *
 * Features:
 * - Multiple drivers (fork, process, sync)
 * - Named results with key preservation
 * - Deferred task execution (after response)
 * - Automatic driver selection based on platform
 *
 * Usage:
 * ```php
 * // Run tasks in parallel
 * $results = Concurrency::run([
 *     'users' => fn() => fetchUsers(),
 *     'posts' => fn() => fetchPosts(),
 * ]);
 * // $results['users'], $results['posts']
 *
 * // Defer task to run after response
 * Concurrency::defer(fn() => sendEmail());
 *
 * // Use specific driver
 * Concurrency::driver('sync')->run([...]);
 * ```
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Process
 * @since       2025-12-11
 */
final class Concurrency
{
    /** @var string Default driver name */
    private static string $defaultDriver = 'fork';

    /** @var int Default max concurrent tasks */
    private static int $maxConcurrent = 4;

    /** @var array<string, ConcurrencyDriverInterface> Driver instances cache */
    private static array $drivers = [];

    /**
     * Run tasks concurrently using the default driver.
     *
     * @param array<string|int, callable> $tasks Tasks to run
     * @param float $timeout Timeout in seconds (0 = no timeout, default: 30)
     * @return array<string|int, mixed> Results (preserves keys)
     */
    public static function run(array $tasks, float $timeout = 30.0): array
    {
        return self::getDriver()->run($tasks, $timeout);
    }

    /**
     * Defer a task to run after the response is sent.
     *
     * @param callable $task Task to defer
     * @return void
     */
    public static function defer(callable $task): void
    {
        self::getDriver()->defer($task);
    }

    /**
     * Get a specific driver instance.
     *
     * @param string $name Driver name (fork, process, sync)
     * @return ConcurrencyDriverInterface
     * @throws \InvalidArgumentException If driver not found
     */
    public static function driver(string $name): ConcurrencyDriverInterface
    {
        if (!isset(self::$drivers[$name])) {
            self::$drivers[$name] = self::createDriver($name);
        }

        return self::$drivers[$name];
    }

    /**
     * Set the default driver.
     *
     * @param string $driver Driver name
     * @return void
     */
    public static function setDefaultDriver(string $driver): void
    {
        self::$defaultDriver = $driver;
    }

    /**
     * Set max concurrent tasks.
     *
     * @param int $max Maximum concurrent tasks
     * @return void
     */
    public static function setMaxConcurrent(int $max): void
    {
        self::$maxConcurrent = max(1, $max);
        // Clear cached drivers to apply new setting
        self::$drivers = [];
    }

    /**
     * Get the default driver instance.
     *
     * @return ConcurrencyDriverInterface
     */
    private static function getDriver(): ConcurrencyDriverInterface
    {
        $driverName = self::$defaultDriver;

        // Auto-fallback if fork not supported
        if ($driverName === 'fork' && !ForkProcess::isSupported()) {
            $driverName = 'sync';
        }

        return self::driver($driverName);
    }

    /**
     * Create a driver instance.
     *
     * @param string $name Driver name
     * @return ConcurrencyDriverInterface
     * @throws \InvalidArgumentException
     */
    private static function createDriver(string $name): ConcurrencyDriverInterface
    {
        return match ($name) {
            'fork' => new ForkDriver(self::$maxConcurrent),
            'process' => new ProcessDriver(self::$maxConcurrent),
            'sync' => new SyncDriver(),
            default => throw new \InvalidArgumentException("Unknown concurrency driver: {$name}"),
        };
    }

    /**
     * Check if fork driver is supported.
     *
     * @return bool
     */
    public static function isForksSupported(): bool
    {
        return ForkProcess::isSupported();
    }

    /**
     * Get available drivers.
     *
     * @return array<string, bool> Driver name => supported
     */
    public static function getAvailableDrivers(): array
    {
        return [
            'fork' => ForkProcess::isSupported(),
            'process' => function_exists('proc_open'),
            'sync' => true,
        ];
    }

    /**
     * Reset all state (for testing).
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$defaultDriver = 'fork';
        self::$maxConcurrent = 4;
        self::$drivers = [];
    }
}
