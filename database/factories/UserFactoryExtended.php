<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Models\UserModel;
use Toporia\Framework\Testing\Factories\Factory;

/**
 * Extended User Factory for comprehensive testing.
 */
class UserFactoryExtended extends Factory
{
    protected string $model = UserModel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 year', 'now'),
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'avatar' => $this->faker->optional(0.6)->imageUrl(200, 200, 'people'),
            'phone' => $this->faker->optional(0.7)->phoneNumber(),
            'address' => $this->faker->optional(0.8)->address(),
            'city' => $this->faker->optional(0.8)->city(),
            'country' => $this->faker->optional(0.8)->country(),
            'postal_code' => $this->faker->optional(0.7)->postcode(),
            'role' => $this->faker->randomElement(['user', 'admin', 'moderator']),
            'is_active' => $this->faker->boolean(90), // 90% active
            'last_login_at' => $this->faker->optional(0.8)->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Create admin user.
     */
    public function admin(): static
    {
        return $this->state([
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Create verified user.
     */
    public function verified(): static
    {
        return $this->state([
            'email_verified_at' => date('Y-m-d H:i:s'),
            'is_active' => true,
        ]);
    }

    /**
     * Create inactive user.
     */
    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
            'last_login_at' => null,
        ]);
    }

    /**
     * Create and persist model.
     */
    public function create(array $attributes = []): UserModel
    {
        $model = $this->make($attributes);
        if ($model instanceof UserModel) {
            $model->save();
            return $model;
        }
        return UserModel::create(array_merge($this->definition(), $attributes));
    }
}

