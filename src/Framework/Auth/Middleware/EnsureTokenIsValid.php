<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Middleware;

use Toporia\Framework\Auth\AuthManager;
use Toporia\Framework\Http\Contracts\MiddlewareInterface;
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
        $user = $this->auth->guard('sanctum')->user();

        if ($user === null) {
            $response->json([
                'error' => 'Unauthenticated',
                'message' => 'Valid API token required'
            ], 401);

            return null; // Short-circuit
        }

        // Continue to next middleware
        return $next($request, $response);
    }
}
