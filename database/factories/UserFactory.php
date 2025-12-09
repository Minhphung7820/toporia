<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Models\UserModel;
use Toporia\Framework\Database\Factories\Factory;

/**
 * UserFactory Factory
 *
 * Factory for creating UserModel model instances.
 *
 * Features:
 * - Lazy attributes (Closure evaluation)
 * - State modifiers
 * - Sequences for unique values
 * - Relationships (has, for)
 * - After making/creating callbacks
 *
 * Usage:
 * ```php
 * // Create single model
 * UserModel::factory()->create();
 *
 * // Create multiple models
 * UserModel::factory()->count(10)->create();
 *
 * // With attributes
 * UserModel::factory()->create(['name' => 'Custom Name']);
 *
 * // With state
 * UserModel::factory()->state('active')->create();
 *
 * // With relationships
 * UserModel::factory()
 *     ->has(PostFactory::new()->count(3), 'posts')
 *     ->create();
 * ```
 *
 * @template T of UserModel
 */
final class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<T>
     */
    protected string $model = UserModel::class;

    /**
     * Define the model's default state.
     *
     * Supports lazy attributes via Closures:
     * ```php
     * 'email' => fn($faker) => $faker->unique()->safeEmail(),
     * 'created_at' => fn($faker) => now(),
     * ```
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'email_verified_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 year', 'now')?->format('Y-m-d H:i:s'),
            'phone' => $this->faker->optional(0.7)->phoneNumber(),
            'avatar' => $this->faker->optional(0.3)->imageUrl(200, 200, 'people'),
            'is_active' => $this->faker->boolean(90), // 90% active
            'role' => $this->faker->randomElement(['user', 'admin', 'moderator']),
            'created_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Indicate that the model is in a specific state.
     *
     * Usage:
     * ```php
     * UserModel::factory()->state('active')->create();
     * ```
     *
     * @return array<string, mixed>
     */
    public function stateActive(): array
    {
        return [
            'is_active' => true,
            'email_verified_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Indicate that the model is in a specific state.
     *
     * Usage:
     * ```php
     * UserModel::factory()->state('inactive')->create();
     * ```
     *
     * @return array<string, mixed>
     */
    public function stateAdmin(): array
    {
        return [
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now()->format('Y-m-d H:i:s'),
        ];
    }
}
