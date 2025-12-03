<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Middleware;

use Toporia\Framework\Auth\AuthManager;
use Toporia\Framework\Http\Contracts\MiddlewareInterface;
use Toporia\Framework\Http\Exceptions\UnauthorizedHttpException;
use Toporia\Framework\Http\{Request, Response};

/**
 * Ensure Token Is Valid Middleware
 *
 * Validates that the request contains a valid, non-expired API token.
 *
 * Usage:
 * ```php
 * $router->get('/api/data', [ApiController::class, 'index'])
 *     ->middleware([EnsureTokenIsValid::class]);
 * ```
 *
 * Clean Architecture:
 * - Framework layer middleware
 * - Depends on AuthManager abstraction
 *
 * SOLID Principles:
 * - Single Responsibility: Token validation only
 * - Dependency Inversion: Depends on AuthManager interface
 *
 * @package Toporia\Framework\Auth\Middleware
 */
final class EnsureTokenIsValid implements MiddlewareInterface
{
    /**
     * Create middleware instance.
     *
     * @param AuthManager $auth Authentication manager
     */
    public function __construct(
        private readonly AuthManager $auth
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param callable $next Next middleware
     * @return mixed Response or null to short-circuit
     */
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        // Authenticate user via token
        $user = $this->auth->guard('personal-token')->user();

        if ($user === null) {
            // Throw UnauthorizedHttpException - will be caught by error handler
            throw new UnauthorizedHttpException('Bearer', 'Valid API token required');
        }

        // Continue to next middleware
        return $next($request, $response);
    }
}
