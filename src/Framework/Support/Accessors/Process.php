<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Process\{ProcessManager, ProcessPool};
use Toporia\Framework\Process\Contracts\ProcessInterface;

/**
 * Class Process
 *
 * Process Service Accessor - Provides static-like access to the process manager.
 * All methods are automatically delegated to the underlying service via __callStatic().
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Support\Accessors
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 *
 * @method static ProcessManager manager() Get ProcessManager instance
 * @method static ProcessPool pool(?int $workerCount = null) Get ProcessPool instance
 * @method static array runTasks(array $tasks, int $maxConcurrent = 4) Run tasks in parallel
 * @method static array run(int $maxConcurrent = 4) Execute all pending processes
 * @method static array map(array $items, callable $callback, ?int $workerCount = null) Map array in parallel
 * @method static array filter(array $items, callable $callback, ?int $workerCount = null) Filter array in parallel
 * @method static mixed reduce(array $items, callable $callback, mixed $initial = null, ?int $workerCount = null) Reduce array in parallel
 * @method static ProcessInterface fork(callable $callback, array $args = []) Create single fork process
 * @method static bool isSupported() Check if PCNTL fork is supported
 * @method static int getCpuCores() Get number of CPU cores
 *
 * @see ProcessManager
 *
 * @example
 * // Run tasks in parallel
 * $results = Process::runTasks([
 *     fn() => heavyTask1(),
 *     fn() => heavyTask2(),
 * ], maxConcurrent: 4);
 *
 * // Or use manager directly
 * Process::manager()->add(fn() => task1());
 * Process::manager()->add(fn() => task2());
 * $results = Process::run(maxConcurrent: 4);
 *
 * // Process pool operations
 * $results = Process::map([1, 2, 3], fn($n) => $n * 2);
 * $evens = Process::filter([1, 2, 3, 4], fn($n) => $n % 2 === 0);
 * $sum = Process::reduce([1, 2, 3, 4], fn($acc, $n) => $acc + $n, 0);
 */
final class Process extends ServiceAccessor
{
    /**
     * Get the service name for this accessor.
     *
     * This is the only method needed - all other methods are automatically
     * delegated to the underlying service via __callStatic().
     *
     * @return string Service name in container
     */
    protected static function getServiceName(): string
    {
        return 'process.manager';
    }
}
