<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Middleware;

use Toporia\Framework\Auth\Throttle\LoginThrottle;
use Toporia\Framework\Http\Contracts\MiddlewareInterface;
use Toporia\Framework\Http\{Request, Response};

/**
 * Throttle Logins Middleware
 *
 * Middleware to throttle authentication attempts.
 * Prevents brute force attacks by limiting login attempts.
 *
 * Usage:
 * Apply to login routes:
 * $router->post('/login', [AuthController::class, 'login'])->middleware([ThrottleLogins::class]);
 *
 * Performance: O(1) - Single cache lookup
 *
 * Clean Architecture:
 * - Presentation Layer: HTTP middleware
 * - Single Responsibility: Only handles login throttling
 *
 * SOLID Principles:
 * - S: Only throttles login attempts
 * - D: Depends on LoginThrottle abstraction
 */
final class ThrottleLogins implements MiddlewareInterface
{
    /**
     * @param LoginThrottle $throttle Login throttle instance
     * @param int $maxAttempts Maximum attempts (overrides throttle default)
     * @param int $decayMinutes Decay time in minutes (overrides throttle default)
     */
    public function __construct(
        private LoginThrottle $throttle,
        private ?int $maxAttempts = null,
        private ?int $decayMinutes = null
    ) {}

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, Response $response, callable $next): mixed
    {
        $identifier = $this->getIdentifier($request);

        // Check if locked out
        if ($this->throttle->isLockedOut($identifier)) {
            $seconds = $this->throttle->getSecondsUntilUnlock($identifier);

            return $response->json([
                'error' => 'Too many login attempts',
                'message' => "Please try again in {$seconds} seconds.",
            ], 429); // Too Many Requests
        }

        // Process request
        $result = $next($request, $response);

        // Ensure we have a Response object
        $response = $result instanceof Response ? $result : $response;

        // If login failed, increment attempts
        if ($response->status() === 401 || $response->status() === 422) {
            $attempts = $this->throttle->incrementAttempts($identifier);
            $remaining = $this->throttle->getRemainingAttempts($identifier);

            // Add headers
            $response->header('X-RateLimit-Limit', (string) ($this->maxAttempts ?? 5));
            $response->header('X-RateLimit-Remaining', (string) $remaining);

            if ($remaining === 0) {
                $seconds = $this->throttle->getSecondsUntilUnlock($identifier);
                $response->header('Retry-After', (string) $seconds);
            }
        } else {
            // Login successful - clear attempts
            $this->throttle->clearAttempts($identifier);
        }

        return $response;
    }

    /**
     * Get identifier for throttling (email, username, or IP).
     *
     * @param Request $request HTTP request
     * @return string Identifier
     */
    private function getIdentifier(Request $request): string
    {
        // Try email first
        $email = $request->input('email');
        if ($email !== null) {
            return $email;
        }

        // Try username
        $username = $request->input('username');
        if ($username !== null) {
            return $username;
        }

        // Fallback to IP address
        return $request->ip() ?? 'unknown';
    }
}
