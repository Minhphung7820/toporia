<?php

declare(strict_types=1);

namespace Toporia\Framework\Application\Middleware;

use Closure;
use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Pipeline\Pipeline;

/**
 * Middleware Pipeline
 *
 * Wraps the general Pipeline for application-specific middleware.
 * Handles middleware for commands and queries.
 *
 * @package Toporia\Framework\Application\Middleware
 */
final class MiddlewarePipeline
{
    private Pipeline $pipeline;

    /**
     * @param ContainerInterface $container DI container
     */
    public function __construct(
        private readonly ContainerInterface $container
    ) {
        $this->pipeline = Pipeline::make($container);
    }

    /**
     * Set the object being sent through the pipeline.
     *
     * @param object $passable The command or query to process
     * @return self
     */
    public function send(object $passable): self
    {
        $this->pipeline->send($passable);
        return $this;
    }

    /**
     * Set the array of middleware.
     *
     * @param array<int, string|callable> $middleware Array of middleware
     * @return self
     */
    public function through(array $middleware): self
    {
        $this->pipeline->through($middleware);
        return $this;
    }

    /**
     * Run the pipeline with a final destination callback.
     *
     * @param Closure $destination Final callback to execute after all middleware
     * @return mixed Result from destination callback
     */
    public function then(Closure $destination): mixed
    {
        return $this->pipeline->then($destination);
    }
}
