<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Middleware;

use Toporia\Framework\Auth\AuthManager;
use Toporia\Framework\Auth\Contracts\HasApiTokensInterface;
use Toporia\Framework\Http\Contracts\MiddlewareInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Check Scopes Middleware
 *
 * Ensures the authenticated user's token has ALL specified abilities/scopes.
 *
 * Usage:
 * ```php
 * // Requires BOTH abilities
 * $router->post('/posts', [PostController::class, 'store'])
 *     ->middleware([
 *         EnsureTokenIsValid::class,
 *         CheckScopes::requires('posts:write', 'posts:publish')
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
final class CheckScopes implements MiddlewareInterface
{
    /**
     * Create middleware instance.
     *
     * @param AuthManager $auth Authentication manager
     * @param array<string> $scopes Required scopes (ALL must be present)
     */
    public function __construct(
        private readonly AuthManager $auth,
        private readonly array $scopes = []
    ) {
    }

    /**
     * Create middleware with required scopes.
     *
     * @param string ...$scopes Required scopes
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

        // Check if token has ALL required scopes
        foreach ($this->scopes as $scope) {
            if ($user->tokenCant($scope)) {
                $response->json([
                    'error' => 'Forbidden',
                    'message' => "Missing required scope: {$scope}",
                    'required_scopes' => $this->scopes
                ], 403);

                return null;
            }
        }

        // All scopes present, continue
        return $next($request, $response);
    }
}
