<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Middleware;

use Toporia\Framework\Auth\AuthManager;
use Toporia\Framework\Auth\Contracts\HasApiTokensInterface;
use Toporia\Framework\Http\Contracts\MiddlewareInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Check For Any Scope Middleware
 *
 * Ensures the authenticated user's token has AT LEAST ONE of the specified abilities/scopes.
 *
 * Usage:
 * ```php
 * // Requires ANY of these abilities
 * $router->get('/posts', [PostController::class, 'index'])
 *     ->middleware([
 *         EnsureTokenIsValid::class,
 *         CheckForAnyScope::requires('posts:read', 'posts:write', 'admin')
 *     ]);
 * ```
 *
 * Clean Architecture:
 * - Framework layer middleware
 * - Uses HasApiTokensInterface contract
 *
 * SOLID Principles:
 * - Single Responsibility: Scope validation
 * - Open/Closed: Extensible via static factory
 *
 * @package Toporia\Framework\Auth\Middleware
 */
final class CheckForAnyScope implements MiddlewareInterface
{
    /**
     * Create middleware instance.
     *
     * @param AuthManager $auth Authentication manager
     * @param array<string> $scopes Required scopes (ANY must be present)
     */
    public function __construct(
        private readonly AuthManager $auth,
        private readonly array $scopes = []
    ) {
    }

    /**
     * Create middleware with required scopes.
     *
     * @param string ...$scopes Required scopes (ANY)
     * @return self Middleware instance
     */
    public static function requires(string ...$scopes): self
    {
        return new self(app('auth'), $scopes);
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
        // Get authenticated user
        $user = $this->auth->guard('sanctum')->user();

        if ($user === null || !$user instanceof HasApiTokensInterface) {
            $response->json([
                'error' => 'Unauthenticated',
                'message' => 'Valid API token required'
            ], 401);

            return null;
        }

        // Check if token has ANY of the required scopes
        $hasAnyScope = false;

        foreach ($this->scopes as $scope) {
            if ($user->tokenCan($scope)) {
                $hasAnyScope = true;
                break;
            }
        }

        if (!$hasAnyScope) {
            $response->json([
                'error' => 'Forbidden',
                'message' => 'Token lacks required permissions',
                'required_any_of' => $this->scopes
            ], 403);

            return null;
        }

        // Has at least one scope, continue
        return $next($request, $response);
    }
}
