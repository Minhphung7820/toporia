<?php

declare(strict_types=1);

namespace Toporia\Framework\Process\Contracts;


/**
 * Interface WorkerInterface
 *
 * Contract defining the interface for WorkerInterface implementations in
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
interface WorkerInterface
{
    /**
     * Process a single job.
     *
     * @param mixed $job
     * @return mixed Result
     */
    public function process(mixed $job): mixed;

    /**
     * Handle worker initialization (in child process).
     *
     * @return void
     */
    public function initialize(): void;

    /**
     * Handle worker shutdown (before process exits).
     *
     * @return void
     */
    public function shutdown(): void;

    /**
     * Handle errors during processing.
     *
     * @param \Throwable $e
     * @param mixed $job
     * @return void
     */
    public function handleError(\Throwable $e, mixed $job): void;
}
