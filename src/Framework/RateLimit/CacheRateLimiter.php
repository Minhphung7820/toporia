<?php

declare(strict_types=1);

namespace Toporia\Framework\RateLimit;

use Toporia\Framework\RateLimit\Contracts\RateLimiterInterface;
use Toporia\Framework\Cache\Contracts\CacheInterface;

/**
 * Cache-based Rate Limiter
 *
 * Uses cache backend for rate limiting with fixed window algorithm.
 * Works with any cache driver (File, Redis, Memory).
 *
 * Rate Limiting Behavior:
 * - Fixed Window: Reset time is fixed when limit is first reached
 * - No penalty accumulation: Spamming after rate limit doesn't increase wait time
 * - Reset time decreases naturally: "Try again in 60s" → "Try again in 50s" → etc.
 *
 * To enable "sliding window" (reset timer on each violation):
 * - Uncomment the code in tooManyAttempts() method
 * - This will reset/extend reset time each time rate limit is exceeded
 */
final class CacheRateLimiter implements RateLimiterInterface
{
    /**
     * @var bool Whether to reset timer on each violation (sliding window behavior)
     * If true: Each spam request extends reset time
     * If false: Reset time is fixed (current behavior)
     */
    private bool $resetOnViolation = false;

    public function __construct(
        private CacheInterface $cache,
        bool $resetOnViolation = false
    ) {
        $this->resetOnViolation = $resetOnViolation;
    }

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
        $resetTime = $this->cache->get($this->resetTimeKey($key));
        $currentTime = time();

        // If resetTime has expired, reset attempts counter
        // This fixes the bug where attempts still exist after reset time expires
        if ($resetTime !== null && $resetTime < $currentTime) {
            // Reset time expired - clear attempts to allow new requests
            $this->cache->delete($this->attemptsKey($key));
            $this->cache->delete($this->resetTimeKey($key));

            // Now check if attempts still exist (should be 0 after delete)
            // But if cache TTL hasn't expired, attempts might still exist
            // So we check again
        }

        $attempts = $this->attempts($key);
        $exceeded = $attempts >= $maxAttempts;

        if ($exceeded) {
            if ($resetTime === null || $resetTime < $currentTime) {
                // ResetTime not set or expired - set new reset time
                $defaultDecay = 60; // Default 60 seconds
                $this->cache->set($this->resetTimeKey($key), $currentTime + $defaultDecay, $defaultDecay);
            } elseif ($this->resetOnViolation) {
                // SLIDING WINDOW: Reset timer on each violation
                // Each spam request extends the reset time
                $defaultDecay = 60; // Should ideally get from decaySeconds parameter
                $newResetTime = $currentTime + $defaultDecay;
                $this->cache->set($this->resetTimeKey($key), $newResetTime, $defaultDecay);
            }
            // FIXED WINDOW (default): Keep reset time fixed
            // Reset time decreases naturally: 60s → 50s → 40s → ... → 0s
            // Spamming doesn't extend the wait time
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
