<?php

declare(strict_types=1);

namespace Toporia\Framework\Process;

use Toporia\Framework\Process\Contracts\WorkerInterface;


/**
 * Abstract Class AbstractWorker
 *
 * Abstract base class for AbstractWorker implementations in the
 * Multi-process execution layer providing common functionality and
 * contracts.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Process
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
abstract class AbstractWorker implements WorkerInterface
{
    /**
     * Process a single job.
     *
     * Override this method with your processing logic.
     *
     * @param mixed $job
     * @return mixed
     */
    abstract public function process(mixed $job): mixed;

    /**
     * Initialize worker (called once when process starts).
     *
     * Override to setup resources (database connections, file handles, etc.)
     *
     * @return void
     */
    public function initialize(): void
    {
        // Default: no initialization
    }

    /**
     * Shutdown worker (called before process exits).
     *
     * Override to cleanup resources.
     *
     * @return void
     */
    public function shutdown(): void
    {
        // Default: no cleanup
    }

    /**
     * Handle errors during processing.
     *
     * Override to customize error handling (logging, retries, etc.)
     *
     * @param \Throwable $e
     * @param mixed $job
     * @return void
     */
    public function handleError(\Throwable $e, mixed $job): void
    {
        // Default: log to error_log
        error_log(sprintf(
            "Worker error: %s\nJob: %s\nTrace: %s",
            $e->getMessage(),
            json_encode($job),
            $e->getTraceAsString()
        ));
    }
}
