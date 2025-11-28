<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Middleware;

use Toporia\Framework\Http\Contracts\MiddlewareInterface;
use Toporia\Framework\Http\{Request, Response};
use Toporia\Framework\RateLimit\{Contracts\RateLimiterInterface, RateLimiter, Limit};

/**
 * Throttle Requests Middleware
 *
 * Rate limits HTTP requests based on configurable criteria.
 * Supports named limiters (like Laravel) and direct configuration.
 *
 * Usage with named limiter:
 * ```php
 * Route::middleware('throttle:api-per-user')->group(function () {
 *     Route::get('/orders', fn() => 'orders');
 * });
 * ```
 *
 * Usage with direct limits:
 * ```php
 * Route::middleware('throttle:60,1')->group(function () {
 *     Route::get('/api', fn() => 'api');
 * });
 * ```
 */
final class ThrottleRequests implements MiddlewareInterface
{
    /**
     * @param RateLimiterInterface $limiter Base rate limiter instance
     * @param int|null $maxAttempts Maximum attempts (null = use named limiter)
     * @param int|null $decayMinutes Decay time in minutes (null = use named limiter)
     * @param string|null $namedLimiter Named limiter name (e.g., 'api-per-user')
     * @param string|null $prefix Optional prefix for rate limit key
     */
    public function __construct(
        private RateLimiterInterface $limiter,
        private ?int $maxAttempts = null,
        private ?int $decayMinutes = null,
        private ?string $namedLimiter = null,
        private ?string $prefix = null
    ) {}

    public function handle(Request $request, Response $response, callable $next): mixed
    {
        // Resolve limit configuration (named limiter or direct config)
        $limit = $this->resolveLimit($request);

        if ($limit === null) {
            // No limit configured - allow request
            return $next($request, $response);
        }

        $key = $this->resolveRequestSignature($request, $limit);
        $maxAttempts = $limit->getMaxAttempts();
        $decaySeconds = $limit->getDecaySeconds();

        if ($this->limiter->tooManyAttempts($key, $maxAttempts, $decaySeconds)) {
            $this->buildRateLimitResponse($response, $key, $maxAttempts, $decaySeconds);
            return null; // Short-circuit - response already sent
        }

        $this->limiter->attempt($key, $maxAttempts, $decaySeconds);

        $result = $next($request, $response);

        // Add rate limit headers
        $this->addHeaders($response, $key, $maxAttempts);

        return $result;
    }

    /**
     * Resolve limit configuration from named limiter or direct config.
     *
     * @param Request $request
     * @return Limit|null
     */
    private function resolveLimit(Request $request): ?Limit
    {
        // Priority 1: Named limiter
        if ($this->namedLimiter !== null) {
            return RateLimiter::limiter($this->namedLimiter, $request);
        }

        // Priority 2: Direct configuration
        if ($this->maxAttempts !== null && $this->decayMinutes !== null) {
            return new Limit(
                $this->maxAttempts,
                $this->decayMinutes * 60,
                null,
                $this->prefix
            );
        }

        // No limit configured
        return null;
    }

    /**
     * Build and send rate limit exceeded response
     *
     * @param Response $response
     * @param string $key
     * @param int $maxAttempts
     * @param int $decaySeconds
     * @return void
     */
    private function buildRateLimitResponse(Response $response, string $key, int $maxAttempts, int $decaySeconds): void
    {
        // Get the actual retry after time
        $retryAfter = $this->limiter->availableIn($key, $decaySeconds);

        // If retryAfter is 0, rate limit has expired - allow the request
        // Don't set fallback to decaySeconds as that would incorrectly extend the rate limit
        if ($retryAfter <= 0) {
            // Rate limit has expired - this shouldn't happen if tooManyAttempts() was called correctly
            // But if it does, return 0 to indicate no wait time
            $retryAfter = 0;
        }

        $response->setStatus(429);
        $response->header('Retry-After', (string)$retryAfter);
        $response->header('X-RateLimit-Limit', (string)$maxAttempts);
        $response->header('X-RateLimit-Remaining', '0');
        $response->header('X-RateLimit-Reset', (string)(time() + $retryAfter));

        $response->json([
            'error' => 'Too Many Requests',
            'message' => $retryAfter > 0
                ? 'Rate limit exceeded. Please try again in ' . $retryAfter . ' seconds.'
                : 'Rate limit exceeded.',
            'retry_after' => $retryAfter,
        ], 429);
    }

    /**
     * Add rate limit headers to response
     *
     * @param Response $response
     * @param string $key
     * @param int $maxAttempts
     * @return void
     */
    private function addHeaders(Response $response, string $key, int $maxAttempts): void
    {
        $response->header('X-RateLimit-Limit', (string)$maxAttempts);
        $response->header('X-RateLimit-Remaining', (string)$this->limiter->remaining($key, $maxAttempts));

        $resetTime = time() + $this->limiter->availableIn($key);
        $response->header('X-RateLimit-Reset', (string)$resetTime);
    }

    /**
     * Resolve the request signature for rate limiting
     *
     * @param Request $request
     * @param Limit $limit
     * @return string
     */
    private function resolveRequestSignature(Request $request, Limit $limit): string
    {
        $prefix = $limit->getPrefix() ?? $this->prefix ?? 'throttle';

        // Use custom key resolver if provided
        $keyResolver = $limit->getKeyResolver();
        if ($keyResolver !== null) {
            $key = $keyResolver($request);
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Key resolver must return a string');
            }
            return $prefix . ':' . $key;
        }

        // Default: use user ID or IP
        $parts = [
            $prefix,
            $this->getUserIdentifier($request),
            $request->path(),
        ];

        return implode(':', $parts);
    }

    /**
     * Get user identifier for rate limiting
     *
     * Uses authenticated user ID if available, falls back to IP address.
     *
     * @param Request $request
     * @return string
     */
    private function getUserIdentifier(Request $request): string
    {
        // Try to get authenticated user ID
        try {
            $user = auth()->user();
            if ($user && method_exists($user, 'getId')) {
                return 'user:' . $user->getId();
            }
        } catch (\Throwable $e) {
            // Auth not available or user not authenticated
        }

        // Fall back to IP address
        return 'ip:' . $request->ip();
    }

    /**
     * Create a throttle middleware with specific limits
     *
     * @param RateLimiterInterface $limiter
     * @param int $maxAttempts
     * @param int $decayMinutes
     * @param string|null $prefix
     * @return self
     */
    public static function with(
        RateLimiterInterface $limiter,
        int $maxAttempts = 60,
        int $decayMinutes = 1,
        ?string $prefix = null
    ): self {
        return new self($limiter, $maxAttempts, $decayMinutes, null, $prefix);
    }
}
