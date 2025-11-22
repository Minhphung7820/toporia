<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\Contracts;

/**
 * Seeder Interface
 *
 * Defines contract for database seeders.
 *
 * SOLID Principles:
 * - Interface Segregation: Specific interface for seeder operations
 * - Single Responsibility: Each seeder has one responsibility (seeding specific data)
 *
 * Clean Architecture:
 * - Application Layer: Defines contract for infrastructure seeders
 */
interface SeederInterface
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void;

    /**
     * Get seeder dependencies (other seeders that must run first).
     *
     * @return array<string> Array of seeder class names
     */
    public function dependencies(): array;

    /**
     * Whether to run this seeder inside a transaction.
     *
     * @return bool
     */
    public function useTransaction(): bool;
}

