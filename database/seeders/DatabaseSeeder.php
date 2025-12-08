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
        // Seed users first (they might be referenced by other tables)
        $this->call(UserSeeder::class);

        // Then seed other data
        // $this->call(ProductSeeder::class);

        // Seed polymorphic relationships (posts, videos, comments, images)
        // $this->call(PolymorphicRelationshipsSeeder::class);

        // Seed relationship testing data (countries, cities, authors, books, etc.)
        $this->call(RelationshipTestSeeder::class);
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
