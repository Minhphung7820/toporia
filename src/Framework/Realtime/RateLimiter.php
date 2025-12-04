<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime;

use Toporia\Framework\Realtime\Exceptions\RateLimitException;

/**
 * Class RateLimiter
 *
 * Rate limiter for realtime messages. Uses sliding window algorithm for accurate rate limiting.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Realtime
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class RateLimiter
{
    /**
     * @var array<string, array{count: int, window_start: int}> Rate limit state
     */
    private array $limits = [];

    /**
     * @param int $maxMessages Maximum messages per window
     * @param int $windowSeconds Window size in seconds
     * @param bool $enabled Whether rate limiting is enabled
     */
    public function __construct(
        private readonly int $maxMessages = 60,
        private readonly int $windowSeconds = 60,
        private readonly bool $enabled = true
    ) {
    }

    /**
     * Check if action is allowed and record it.
     *
     * @param string $identifier Rate limit identifier (channel, connection, user)
     * @return bool True if allowed
     */
    public function attempt(string $identifier): bool
    {
        if (!$this->enabled) {
            return true;
        }

        $now = time();
        $state = $this->limits[$identifier] ?? null;

        // Start new window if needed
        if ($state === null || ($now - $state['window_start']) >= $this->windowSeconds) {
            $this->limits[$identifier] = [
                'count' => 1,
                'window_start' => $now,
            ];
            return true;
        }

        // Check if limit exceeded
        if ($state['count'] >= $this->maxMessages) {
            return false;
        }

        // Increment count
        $this->limits[$identifier]['count']++;
        return true;
    }

    /**
     * Check if action is allowed, throw exception if not.
     *
     * @param string $identifier Rate limit identifier
     * @throws RateLimitException If rate limit exceeded
     */
    public function check(string $identifier): void
    {
        if (!$this->attempt($identifier)) {
            $state = $this->limits[$identifier];
            $retryAfter = $this->windowSeconds - (time() - $state['window_start']);

            throw new RateLimitException(
                $identifier,
                $this->maxMessages,
                $state['count'],
                max(1, $retryAfter)
            );
        }
    }

    /**
     * Get remaining attempts for an identifier.
     *
     * @param string $identifier Rate limit identifier
     * @return int Remaining attempts
     */
    public function remaining(string $identifier): int
    {
        if (!$this->enabled) {
            return PHP_INT_MAX;
        }

        $now = time();
        $state = $this->limits[$identifier] ?? null;

        if ($state === null || ($now - $state['window_start']) >= $this->windowSeconds) {
            return $this->maxMessages;
        }

        return max(0, $this->maxMessages - $state['count']);
    }

    /**
     * Get seconds until rate limit resets.
     *
     * @param string $identifier Rate limit identifier
     * @return int Seconds until reset
     */
    public function retryAfter(string $identifier): int
    {
        $state = $this->limits[$identifier] ?? null;

        if ($state === null) {
            return 0;
        }

        $elapsed = time() - $state['window_start'];
        return max(0, $this->windowSeconds - $elapsed);
    }

    /**
     * Reset rate limit for an identifier.
     *
     * @param string $identifier Rate limit identifier
     */
    public function reset(string $identifier): void
    {
        unset($this->limits[$identifier]);
    }

    /**
     * Clear all rate limit state.
     */
    public function clear(): void
    {
        $this->limits = [];
    }

    /**
     * Clean up expired entries to prevent memory leaks.
     */
    public function cleanup(): void
    {
        $now = time();
        $expiry = $this->windowSeconds * 2; // Keep for 2 windows

        foreach ($this->limits as $identifier => $state) {
            if (($now - $state['window_start']) >= $expiry) {
                unset($this->limits[$identifier]);
            }
        }
    }
}
