<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Services;

/**
 * Health Checker Interface
 *
 * Contract for health checking services (Kafka, RabbitMQ, Redis, etc.)
 *
 * SOLID Principles:
 * - Interface Segregation: Focused on health checking only
 * - Dependency Inversion: Domain contract for infrastructure services
 *
 * @package App\Domain\Services
 */
interface HealthCheckerInterface
{
    /**
     * Check if service connection is healthy.
     *
     * @return bool True if connection is healthy
     */
    public function checkConnection(): bool;

    /**
     * Check API version compatibility.
     *
     * @return bool True if API version is compatible
     */
    public function checkApiVersion(): bool;

    /**
     * Get health status details.
     *
     * @return array<string, mixed> Health status information
     */
    public function getHealthStatus(): array;
}
