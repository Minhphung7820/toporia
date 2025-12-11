<?php

declare(strict_types=1);

namespace Toporia\Framework\Process\Contracts;

/**
 * Interface ConcurrencyDriverInterface
 *
 * Contract for concurrency drivers (fork, process, sync).
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Process\Contracts
 * @since       2025-12-11
 */
interface ConcurrencyDriverInterface
{
    /**
     * Run multiple tasks concurrently.
     *
     * @param array<string|int, callable> $tasks Tasks to run (can be keyed)
     * @param float $timeout Timeout in seconds (0 = no timeout)
     * @return array<string|int, mixed> Results (preserves keys)
     */
    public function run(array $tasks, float $timeout = 0): array;

    /**
     * Defer a task to run after response is sent.
     *
     * @param callable $task Task to defer
     * @return void
     */
    public function defer(callable $task): void;

    /**
     * Check if this driver is supported on current platform.
     *
     * @return bool
     */
    public function isSupported(): bool;

    /**
     * Get driver name.
     *
     * @return string
     */
    public function getName(): string;
}
