<?php

declare(strict_types=1);

namespace Database\Examples;

use Toporia\Framework\Database\Factory;
use Toporia\Framework\Database\ORM\Model;
use Database\Factories\PostFactory;

/**
 * Example User Factory
 *
 * Demonstrates factory usage with:
 * - Faker integration
 * - State management
 * - Relationships
 * - Sequences
 *
 * Usage:
 * ```php
 * // Create single user
 * $user = UserFactory::new()->create();
 *
 * // Create admin user
 * $admin = UserFactory::new()->state('admin')->create();
 *
 * // Create user with posts
 * $user = UserFactory::new()->has(PostFactory::new()->count(3))->create();
 *
 * // Create many users
 * $users = UserFactory::new()->count(10)->create();
 * ```
 *
 * @template T of Model
 */
class UserFactoryExample extends Factory
{
    /**
     * Model class name.
     *
     * @var string
     */
    protected string $model = 'App\Domain\User\UserModel'; // Update with actual model class

    /**
     * Define model's default attributes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker()->name(),
            'email' => $this->faker()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt('password'), // Default password
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * State: Unverified user.
     *
     * @return array<string, mixed>
     */
    public function stateUnverified(): array
    {
        return [
            'email_verified_at' => null,
        ];
    }

    /**
     * State: Admin user.
     *
     * @return array<string, mixed>
     */
    public function stateAdmin(): array
    {
        return [
            'role' => 'admin',
            'is_admin' => true,
        ];
    }

    /**
     * State: Verified user.
     *
     * @return array<string, mixed>
     */
    public function stateVerified(): array
    {
        return [
            'email_verified_at' => now(),
            'is_verified' => true,
        ];
    }

    /**
     * State: User created in past.
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function stateCreatedInPast(array $attributes): array
    {
        return [
            'created_at' => $this->faker()->dateTimeBetween('-1 year', 'now'),
            'updated_at' => fn() => $this->faker()->dateTimeBetween($attributes['created_at'], 'now'),
        ];
    }
}
