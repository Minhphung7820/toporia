<?php

declare(strict_types=1);

namespace Toporia\Framework\Process\Contracts;


/**
 * Interface ProcessInterface
 *
 * Contract defining the interface for ProcessInterface implementations in
 * the Multi-process execution layer of the Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Process\Contracts
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
interface ProcessInterface
{
    /**
     * Start the process.
     *
     * @return bool True if started successfully
     */
    public function start(): bool;

    /**
     * Check if process is running.
     *
     * @return bool
     */
    public function isRunning(): bool;

    /**
     * Wait for process to finish.
     *
     * @return int Exit code
     */
    public function wait(): int;

    /**
     * Get process ID (PID).
     *
     * @return int|null
     */
    public function getPid(): ?int;

    /**
     * Get exit code.
     *
     * @return int|null
     */
    public function getExitCode(): ?int;

    /**
     * Kill the process.
     *
     * @param int $signal Signal to send (default: SIGTERM)
     * @return bool
     */
    public function kill(int $signal = 15): bool;

    /**
     * Get process output.
     *
     * @return mixed
     */
    public function getOutput(): mixed;
}
