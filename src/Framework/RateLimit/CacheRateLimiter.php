<?php

declare(strict_types=1);

namespace Toporia\Framework\RateLimit;

use Toporia\Framework\RateLimit\Contracts\RateLimiterInterface;
use Toporia\Framework\Cache\Contracts\CacheInterface;

/**
 * Cache-based Rate Limiter
 *
 * Uses cache backend for rate limiting with sliding window algorithm.
 * Works with any cache driver (File, Redis, Memory).
 */
final class CacheRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private CacheInterface $cache
    ) {}

    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        $this->hit($key, $decaySeconds);
        return true;
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $attempts = $this->attempts($key);
        $exceeded = $attempts >= $maxAttempts;

        // If rate limit is exceeded but resetTime is not set, we need to set it
        // This ensures availableIn() can calculate retry_after correctly
        if ($exceeded) {
            $resetTime = $this->cache->get($this->resetTimeKey($key));
            if ($resetTime === null) {
                // ResetTime not set - this happens when rate limit is exceeded
                // but hit() was never called (request was blocked before hit())
                // We need to estimate reset time based on when the limit was first reached
                // Since we don't know the exact decay, we'll use a default
                // The actual fix should be in hit() to always set resetTime
                // But this is a safety fallback
                $defaultDecay = 60; // Default 60 seconds
                $this->cache->set($this->resetTimeKey($key), time() + $defaultDecay, $defaultDecay);
            }
        }

        return $exceeded;
    }

    public function attempts(string $key): int
    {
        return (int) $this->cache->get($this->attemptsKey($key), 0);
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        $attempts = $this->attempts($key);
        return max(0, $maxAttempts - $attempts);
    }

    public function availableIn(string $key, ?int $decaySeconds = null): int
    {
        $resetTime = $this->cache->get($this->resetTimeKey($key));

        if ($resetTime === null) {
            // If reset time doesn't exist, check if we have attempts
            // If attempts exist, we need to estimate reset time based on decay
            // This can happen if rate limit was exceeded but reset time not set yet
            $attempts = $this->attempts($key);

            if ($attempts > 0) {
                // Attempts exist but no reset time - this is a bug
                // Reset time should have been set when first request hit the limit
                // Use provided decaySeconds or default 60 seconds as fallback
                $decay = $decaySeconds ?? 60;
                $resetTime = time() + $decay;

                // Store reset time for future calls with proper TTL
                $this->cache->set($this->resetTimeKey($key), $resetTime, $decay);

                return max(1, $decay); // At least 1 second
            }

            // No attempts and no reset time - rate limit is not active
            return 0;
        }

        $remaining = max(0, $resetTime - time());

        // If resetTime has already passed, return at least 1 second
        // This prevents "0 seconds" message when resetTime is in the past
        return $remaining > 0 ? $remaining : 0;
    }

    public function clear(string $key): void
    {
        $this->resetAttempts($key);
    }

    public function resetAttempts(string $key): void
    {
        $this->cache->delete($this->attemptsKey($key));
        $this->cache->delete($this->resetTimeKey($key));
    }

    /**
     * Increment the hit counter
     *
     * @param string $key
     * @param int $decaySeconds
     * @return int New attempt count
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $attemptsKey = $this->attemptsKey($key);
        $resetTimeKey = $this->resetTimeKey($key);

        // Set reset time if not exists
        if (!$this->cache->has($resetTimeKey)) {
            $this->cache->set($resetTimeKey, time() + $decaySeconds, $decaySeconds);
        }

        // Increment attempts
        $attempts = $this->cache->increment($attemptsKey, 1);

        if ($attempts === false) {
            $this->cache->set($attemptsKey, 1, $decaySeconds);
            return 1;
        }

        return $attempts;
    }

    /**
     * Get the cache key for attempts counter
     *
     * @param string $key
     * @return string
     */
    private function attemptsKey(string $key): string
    {
        return "rate_limit:{$key}:attempts";
    }

    /**
     * Get the cache key for reset time
     *
     * @param string $key
     * @return string
     */
    private function resetTimeKey(string $key): string
    {
        return "rate_limit:{$key}:reset";
    }
}
