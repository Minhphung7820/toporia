<?php

declare(strict_types=1);

namespace Toporia\Framework\Application\Middleware;

use Closure;

/**
 * Middleware Interface
 *
 * Middleware for commands and queries.
 * Handles cross-cutting concerns like validation, logging, transactions, etc.
 *
 * @package Toporia\Framework\Application\Middleware
 */
interface MiddlewareInterface
{
    /**
     * Handle the middleware logic.
     *
     * @param object $message Command or Query object
     * @param Closure $next Next middleware or handler
     * @return mixed Result from next middleware or handler
     */
    public function handle(object $message, Closure $next): mixed;
}
