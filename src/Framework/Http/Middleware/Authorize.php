<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Middleware;

use Toporia\Framework\Http\Contracts\MiddlewareInterface;
use Toporia\Framework\Auth\AuthorizationException;
use Toporia\Framework\Auth\Contracts\GateContract;
use Toporia\Framework\Http\Exceptions\AccessDeniedHttpException;
use Toporia\Framework\Http\{Request, Response};

/**
 * Authorization Middleware
 *
 * Checks if the authenticated user is authorized to perform an action.
 * Uses Gate for authorization checks.
 */
final class Authorize implements MiddlewareInterface
{
    public function __construct(
        private GateContract $gate,
        private string $ability,
        private array $arguments = []
    ) {}

    public function handle(Request $request, Response $response, callable $next): mixed
    {
        try {
            $this->gate->authorize($this->ability, ...$this->arguments);
        } catch (AuthorizationException $e) {
            // Throw AccessDeniedHttpException - will be caught by error handler
            throw new AccessDeniedHttpException($e->getMessage(), $e);
        }

        return $next($request, $response);
    }

    /**
     * Create middleware for a specific ability
     *
     * @param GateContract $gate
     * @param string $ability
     * @param mixed ...$arguments
     * @return self
     */
    public static function can(GateContract $gate, string $ability, mixed ...$arguments): self
    {
        return new self($gate, $ability, $arguments);
    }
}
