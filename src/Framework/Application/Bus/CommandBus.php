<?php

declare(strict_types=1);

namespace Toporia\Framework\Application\Bus;

use Toporia\Framework\Application\Contracts\CommandInterface;
use Toporia\Framework\Application\Contracts\HandlerInterface;
use Toporia\Framework\Application\Handler\HandlerResolver;
use Toporia\Framework\Application\Middleware\MiddlewarePipeline;
use Toporia\Framework\Container\Contracts\ContainerInterface;

/**
 * Command Bus
 *
 * Dispatches commands through middleware pipeline to their handlers.
 * Implements CQRS pattern for write operations.
 *
 * Architecture:
 * - Separates command dispatch from handler execution
 * - Supports middleware for cross-cutting concerns
 * - Automatic handler resolution via HandlerResolver
 *
 * Flow:
 * Command → Middleware Pipeline → Handler → Result
 *
 * SOLID Principles:
 * - Single Responsibility: Dispatches commands only
 * - Open/Closed: Extensible via middleware
 * - Dependency Inversion: Depends on abstractions
 *
 * @package Toporia\Framework\Application\Bus
 */
final class CommandBus
{
    /**
     * @param ContainerInterface $container DI container
     * @param HandlerResolver $resolver Handler resolver
     * @param array<int, string|callable> $middleware Global middleware stack
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly HandlerResolver $resolver,
        private readonly array $middleware = []
    ) {}

    /**
     * Dispatch a command through the middleware pipeline.
     *
     * @param CommandInterface $command The command to dispatch
     * @return mixed Result from the handler
     * @throws \Exception If handler execution fails
     */
    public function dispatch(CommandInterface $command): mixed
    {
        // Validate command
        $command->validate();

        // Resolve handler
        $handler = $this->resolver->resolve($command);

        // Build middleware pipeline
        $pipeline = new MiddlewarePipeline($this->container);

        // Execute through pipeline
        return $pipeline->send($command)
            ->through($this->middleware)
            ->then(fn(CommandInterface $cmd) => $handler($cmd));
    }
}
