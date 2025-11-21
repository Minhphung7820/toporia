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
     * Default decay time in seconds (used as fallback when decay unknown).
     */
    private const DEFAULT_DECAY_SECONDS = 60;

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
        // Validate input
        if ($maxAttempts <= 0) {
            return false; // No limit if maxAttempts <= 0
        }

        $currentTime = time();
        $resetTime = $this->cache->get($this->resetTimeKey($key));

        // CRITICAL FIX: If resetTime has expired or doesn't exist, attempts should be 0
        // This ensures rate limit resets properly when resetTime expires
        // Check BOTH conditions: null (cache expired/deleted) OR timestamp expired
        if ($resetTime === null) {
            // ResetTime doesn't exist - check if attempts still exist
            // If attempts exist but resetTime doesn't, this is an inconsistent state
            // Reset attempts to be safe
            $attempts = $this->attempts($key);
            if ($attempts > 0) {
                // Attempts exist without resetTime - reset to be safe
                $this->resetAttempts($key);
            }
            return false; // No rate limit active
        }

        // Check if resetTime timestamp has expired (regardless of cache TTL)
        if ($resetTime < $currentTime) {
            // ResetTime timestamp expired - reset everything
            $this->resetAttempts($key);
            return false; // No rate limit active
        }

        // resetTime is valid - check attempts
        $attempts = $this->attempts($key);
        $exceeded = $attempts >= $maxAttempts;

        // If rate limit exceeded, ensure resetTime is set
        if ($exceeded) {
            $this->ensureResetTime($key, $resetTime, $currentTime);
        }

        return $exceeded;
    }

    /**
     * Ensure reset time is set when rate limit is exceeded.
     *
     * @param string $key
     * @param int|null $existingResetTime
     * @param int $currentTime
     * @return void
     */
    private function ensureResetTime(string $key, ?int $existingResetTime, int $currentTime): void
    {
        // If resetTime doesn't exist or expired, set new one
        if ($existingResetTime === null || $existingResetTime < $currentTime) {
            $decay = self::DEFAULT_DECAY_SECONDS;
            $this->cache->set(
                $this->resetTimeKey($key),
                $currentTime + $decay,
                $decay
            );
            return;
        }

        // Sliding window: Reset timer on each violation
        if ($this->resetOnViolation) {
            $decay = self::DEFAULT_DECAY_SECONDS;
            $this->cache->set(
                $this->resetTimeKey($key),
                $currentTime + $decay,
                $decay
            );
        }
        // Fixed window (default): Keep reset time unchanged
    }

    public function attempts(string $key): int
    {
        // CRITICAL: Check resetTime first - if expired, attempts should be 0
        $resetTime = $this->cache->get($this->resetTimeKey($key));
        $currentTime = time();

        // If resetTime has expired, attempts should be 0 regardless of cache value
        if ($resetTime !== null && $resetTime < $currentTime) {
            // Reset time expired - clear attempts and return 0
            $this->cache->delete($this->attemptsKey($key));
            return 0;
        }

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
                // Attempts exist but no reset time - estimate based on decay
                // Use provided decaySeconds or default as fallback
                $decay = $decaySeconds ?? self::DEFAULT_DECAY_SECONDS;
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
    public function hit(string $key, int $decaySeconds = self::DEFAULT_DECAY_SECONDS): int
    {
        $attemptsKey = $this->attemptsKey($key);
        $resetTimeKey = $this->resetTimeKey($key);
        $currentTime = time();

        // Check if resetTime has expired - if so, reset attempts FIRST
        $resetTime = $this->cache->get($resetTimeKey);
        $needsReset = ($resetTime !== null && $resetTime < $currentTime);

        if ($needsReset) {
            // Reset time expired - reset everything and start fresh
            // CRITICAL: Delete attempts BEFORE setting new resetTime
            $this->cache->delete($attemptsKey);
            $this->cache->delete($resetTimeKey);
        }

        // Set new reset time if not exists or expired
        if ($resetTime === null || $needsReset) {
            $newResetTime = $currentTime + $decaySeconds;
            // CRITICAL: Set resetTime with TTL matching decaySeconds
            // This ensures both resetTime and attempts expire at the same time
            $this->cache->set($resetTimeKey, $newResetTime, $decaySeconds);
        }

        // If reset was needed, start fresh at 1 (don't increment from old value)
        if ($needsReset) {
            // CRITICAL: Set attempts to 1 directly with SAME TTL as resetTime
            // This ensures attempts and resetTime expire together
            // If resetTime expires, attempts should also expire (via TTL)
            $this->cache->set($attemptsKey, 1, $decaySeconds);
            return 1;
        }

        // Increment attempts (only if resetTime is still valid)
        $attempts = $this->cache->increment($attemptsKey, 1);

        if ($attempts === false) {
            // Cache key doesn't exist - start fresh at 1
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
