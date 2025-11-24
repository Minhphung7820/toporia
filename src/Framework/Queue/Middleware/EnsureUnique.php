<?php

declare(strict_types=1);

namespace Toporia\Framework\Queue\Middleware;

use Toporia\Framework\Queue\Contracts\JobInterface;
use Toporia\Framework\Cache\Contracts\CacheInterface;
use Toporia\Framework\Queue\Exceptions\JobAlreadyRunningException;

/**
 * Ensure Unique Middleware
 *
 * Ensures only one job with the same unique ID is queued at a time.
 * Prevents duplicate jobs from being processed.
 *
 * Performance: O(1) - Cache lock check
 *
 * Clean Architecture:
 * - Single Responsibility: Unique job enforcement only
 * - Dependency Inversion: Uses CacheInterface
 *
 * @package Toporia\Framework\Queue\Middleware
 */
final class EnsureUnique implements JobMiddleware
{
    public function __construct(
        private CacheInterface $cache
    ) {}

    /**
     * {@inheritdoc}
     */
    public function handle(JobInterface $job, callable $next): mixed
    {
        $uniqueId = $job->getUniqueId();

        if ($uniqueId === null) {
            // No unique constraint, proceed normally
            return $next($job);
        }

        $lockKey = "job_unique:{$uniqueId}";
        $uniqueFor = $job->getUniqueFor();

        // Check if job with this unique ID is already queued
        if ($this->cache->has($lockKey)) {
            throw new JobAlreadyRunningException(
                "Job with unique ID '{$uniqueId}' is already queued"
            );
        }

        // Acquire lock
        $this->cache->set($lockKey, time(), $uniqueFor);

        try {
            $result = $next($job);
            return $result;
        } finally {
            // Release lock after job completes
            $this->cache->delete($lockKey);
        }
    }
}
