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
        // Seed blog data (users, categories, tags)
        $this->call(BlogSeeder::class);

        // Seed relationship testing data (countries, cities, authors, books, etc.)
        // $this->call(RelationshipTestSeeder::class);

        // Note: Posts are imported separately via CSV for performance
        // Run: php console posts:generate-csv 1000000
        // Then: php console posts:import
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
