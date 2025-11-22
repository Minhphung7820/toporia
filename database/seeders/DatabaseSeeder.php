<?php

declare(strict_types=1);

namespace Database\Seeders;

use Toporia\Framework\Database\Seeder;

/**
 * Database Seeder
 *
 * Main seeder that runs all other seeders.
 *
 * Usage:
 * php console db:seed
 * php console db:seed --class=DatabaseSeeder
 * php console db:seed --all
 */
final class DatabaseSeeder extends Seeder
{
    /**
     * Get seeder dependencies.
     *
     * @return array<string>
     */
    public function dependencies(): array
    {
        return [
            // Add dependent seeders here
            // Example: RoleSeeder::class,
        ];
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    protected function seed(): void
    {
        // Example: Seed users, roles, etc.
        // $this->call(RoleSeeder::class);
        // $this->call(UserSeeder::class);

        // Or use factory directly
        // $this->factory(UserFactory::new(), 10);
    }

    /**
     * Whether to use transaction for this seeder.
     *
     * @return bool
     */
    public function useTransaction(): bool
    {
        return true;
    }
}

