<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Contracts;

/**
 * Interface for components that support health checks.
 *
 * @package Toporia\Framework\Realtime\Contracts
 */
interface HealthCheckableInterface
{
    /**
     * Perform a health check.
     *
     * @return HealthCheckResult
     */
    public function healthCheck(): HealthCheckResult;

    /**
     * Get the component name for health reporting.
     *
     * @return string
     */
    public function getHealthCheckName(): string;
}
