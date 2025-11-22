<?php

declare(strict_types=1);

namespace Database\Examples;

use Toporia\Framework\Database\Seeder;
use Database\Examples\UserFactoryExample;

/**
 * Example User Seeder
 *
 * Demonstrates seeder usage with:
 * - Factory integration
 * - Dependency management
 * - Batch operations
 * - Progress tracking
 *
 * Usage:
 * php console db:seed --class=UserSeederExample
 */
class UserSeederExample extends Seeder
{
    /**
     * Get seeder dependencies.
     *
     * @return array<string>
     */
    public function dependencies(): array
    {
        return [
            // Example: RoleSeederExample::class,
            // Run these seeders first
        ];
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    protected function seed(): void
    {
        $this->info('Seeding users...');

        // Seed regular users
        $this->factory(UserFactoryExample::new(), 10);

        // Seed admin users
        $this->factory(UserFactoryExample::new()->state('admin'), 5);

        // Seed verified users
        $this->factory(UserFactoryExample::new()->state('verified'), 20);

        // Seed large batch with progress
        $this->factoryWithProgress(UserFactoryExample::new(), 100);

        $this->info('Users seeded successfully!');
    }

    /**
     * Whether to use transaction for this seeder.
     *
     * @return bool
     */
    public function useTransaction(): bool
    {
        return true; // Wrap in transaction for safety
    }

    /**
     * Output info message (helper method).
     *
     * @param string $message
     * @return void
     */
    /**
     * Output info message (helper method).
     *
     * @param string $message
     * @return void
     */
    protected function info(string $message): void
    {
        // Use global info function (from helpers.php)
        \info($message);
    }
}
