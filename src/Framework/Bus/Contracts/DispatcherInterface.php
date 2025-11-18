<?php

declare(strict_types=1);

namespace Toporia\Framework\Bus\Contracts;

/**
 * Command/Query/Job Dispatcher Interface
 *
 * Central bus for dispatching commands, queries, and jobs to their handlers.
 * Supports sync/async execution, middleware pipeline, and batching.
 *
 * SOLID Principles:
 * - Interface Segregation: Focused contract for dispatching
 * - Dependency Inversion: Depend on abstraction, not concrete implementation
 */
interface DispatcherInterface
{
    /**
     * Dispatch a command/query/job to its handler.
     *
     * @param mixed $command Command/Query/Job instance
     * @return mixed Handler result
     */
    public function dispatch(mixed $command): mixed;

    /**
     * Dispatch a command/query/job synchronously (immediately).
     *
     * @param mixed $command Command/Query/Job instance
     * @return mixed Handler result
     */
    public function dispatchSync(mixed $command): mixed;

    /**
     * Dispatch a command after the current process.
     *
     * @param mixed $command Command/Query/Job instance
     * @return void
     */
    public function dispatchAfterResponse(mixed $command): void;

    /**
     * Map commands to handlers.
     *
     * @param array<string, string> $map Command => Handler mapping
     * @return void
     */
    public function map(array $map): void;

    /**
     * Add middleware to the pipeline.
     *
     * @param array<callable|string> $middleware Middleware classes/closures
     * @return self
     */
    public function pipeThrough(array $middleware): self;

    /**
     * Check if a handler exists for the command.
     *
     * @param mixed $command Command/Query/Job instance
     * @return bool
     */
    public function hasHandler(mixed $command): bool;

    /**
     * Get the handler for a command.
     *
     * @param mixed $command Command/Query/Job instance
     * @return callable Handler
     */
    public function getHandler(mixed $command): callable;
}
