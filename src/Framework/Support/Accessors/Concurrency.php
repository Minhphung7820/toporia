<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Process\Concurrency as ConcurrencyManager;
use Toporia\Framework\Process\Contracts\ConcurrencyDriverInterface;

/**
 * Concurrency Accessor
 *
 * Static facade for concurrent task execution.
 * Laravel-compatible API for running tasks in parallel.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 *
 * @method static array run(array $tasks, float $timeout = 30.0) Run tasks concurrently with timeout
 * @method static void defer(callable $task) Defer task to run after response
 * @method static ConcurrencyDriverInterface driver(string $name) Get specific driver
 * @method static void setDefaultDriver(string $driver) Set default driver
 * @method static void setMaxConcurrent(int $max) Set max concurrent tasks
 * @method static bool isForksSupported() Check if fork is supported
 * @method static array getAvailableDrivers() Get available drivers
 *
 * @example
 * // Run tasks in parallel with named results (default 30s timeout)
 * $results = Concurrency::run([
 *     'users' => fn() => User::all(),
 *     'posts' => fn() => Post::recent(),
 *     'stats' => fn() => Stats::calculate(),
 * ]);
 * // Access: $results['users'], $results['posts'], $results['stats']
 *
 * // Run with custom timeout (60 seconds)
 * $results = Concurrency::run([...], timeout: 60.0);
 *
 * // Defer task to run after response sent
 * Concurrency::defer(fn() => sendWelcomeEmail($user));
 *
 * // Use specific driver with timeout
 * $results = Concurrency::driver('fork')->run([...], timeout: 10.0);
 */
final class Concurrency
{
    /**
     * Forward all static calls to ConcurrencyManager.
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return ConcurrencyManager::$method(...$arguments);
    }
}
