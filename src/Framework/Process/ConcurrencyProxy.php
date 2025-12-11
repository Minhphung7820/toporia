<?php

declare(strict_types=1);

namespace Toporia\Framework\Process;

use Toporia\Framework\Process\Contracts\ConcurrencyDriverInterface;

/**
 * ConcurrencyProxy
 *
 * Proxy class for fluent concurrency API via helper function.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 */
final class ConcurrencyProxy
{
    /**
     * Run tasks concurrently.
     *
     * @param array<string|int, callable> $tasks
     * @param float $timeout Timeout in seconds (0 = no timeout, default: 30)
     * @return array<string|int, mixed>
     */
    public function run(array $tasks, float $timeout = 30.0): array
    {
        return Concurrency::run($tasks, $timeout);
    }

    /**
     * Defer a task to run after response.
     *
     * @param callable $task
     * @return void
     */
    public function defer(callable $task): void
    {
        Concurrency::defer($task);
    }

    /**
     * Get specific driver.
     *
     * @param string $name
     * @return ConcurrencyDriverInterface
     */
    public function driver(string $name): ConcurrencyDriverInterface
    {
        return Concurrency::driver($name);
    }

    /**
     * Set default driver.
     *
     * @param string $driver
     * @return void
     */
    public function setDefaultDriver(string $driver): void
    {
        Concurrency::setDefaultDriver($driver);
    }

    /**
     * Set max concurrent tasks.
     *
     * @param int $max
     * @return void
     */
    public function setMaxConcurrent(int $max): void
    {
        Concurrency::setMaxConcurrent($max);
    }
}
