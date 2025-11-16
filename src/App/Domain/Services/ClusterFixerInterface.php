<?php

declare(strict_types=1);

namespace App\Domain\Services;

/**
 * Cluster Fixer Interface
 *
 * Contract for fixing cluster mismatches (Kafka, etc.)
 *
 * SOLID Principles:
 * - Interface Segregation: Focused on cluster fixing only
 * - Dependency Inversion: Domain contract for infrastructure services
 *
 * @package App\Domain\Services
 */
interface ClusterFixerInterface
{
    /**
     * Check if cluster needs fixing.
     *
     * @return bool True if cluster needs fixing
     */
    public function needsFix(): bool;

    /**
     * Fix cluster mismatch.
     *
     * @return bool True if fix was successful
     */
    public function fix(): bool;

    /**
     * Get fix status details.
     *
     * @return array<string, mixed> Fix status information
     */
    public function getFixStatus(): array;
}
