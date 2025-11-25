<?php

declare(strict_types=1);

namespace Database\Seeders;

use Toporia\Framework\Database\Seeder;
use Database\Factories\UserFactory;
use App\Infrastructure\Persistence\Models\UserModel;

/**
 * UserSeeder Seeder
 *
 * Seeds users table with data.
 *
 * Features:
 * - Dependency management
 * - Transaction support
 * - Batch operations
 * - Progress tracking
 * - Factory integration
 *
 * Usage:
 * ```bash
 * php console db:seed --class=UserSeeder
 * php console db:seed --class=UserSeeder --count=100
 * ```
 *
 * Architecture:
 * - Clean Architecture: Business logic separated from infrastructure
 * - SOLID: Single Responsibility, Open/Closed, Dependency Inversion
 * - Performance: Optimized batch inserts, memory-efficient processing
 */
final class UserSeeder extends Seeder
{
    /**
     * Batch size for bulk operations.
     */
    protected int $batchSize = 500;

    /**
     * Get seeder dependencies (other seeders that must run first).
     *
     * @return array<string> Array of seeder class names
     */
    public function dependencies(): array
    {
        return [
            // Example: CategorySeeder::class,
            // Example: UserSeeder::class,
        ];
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    protected function seed(): void
    {
        $this->line('Creating users...');

        // Method 1: Using Model::factory() syntax (Laravel-like)
        $users = UserModel::factory()->createMany(50);
        $this->line("✓ Created " . count($users) . " regular users");

        // Method 2: Using factory with states
        $admins = UserModel::factory()->state('admin')->createMany(5);
        $this->line("✓ Created " . count($admins) . " admin users");

        // Method 3: Using seeder's factory method (alternative)
        $this->factory(UserFactory::class, 20);
        $this->line('✓ Created 20 users via seeder factory method');

        // Method 4: Using factoryBatch for large datasets (most performant)
        $this->factoryBatch(UserFactory::class, 100, [], 'users');
        $this->line('✓ Created 100 users via batch insert');

        // Method 5: Create specific users
        UserModel::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);
        $this->line('✓ Created admin user');

        $this->success('User seeding completed!');
    }

    /**
     * Whether to use transaction for this seeder.
     *
     * @return bool
     */
    public function useTransaction(): bool
    {
        return true; // Wrap in transaction for safety (rollback on error)
    }
}
