<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Models\UserProfileModel;
use Toporia\Framework\Database\Factories\Factory;

/**
 * UserProfile Factory for testing.
 */
class UserProfileFactory extends Factory
{
    protected string $model = UserProfileModel::class;

    public function definition(): array
    {
        return [
            'bio' => $this->faker->optional(0.8)->paragraph(),
            'website' => $this->faker->optional(0.4)->url(),
            'twitter' => $this->faker->optional(0.3)->userName(),
            'facebook' => $this->faker->optional(0.3)->userName(),
            'linkedin' => $this->faker->optional(0.4)->userName(),
            'github' => $this->faker->optional(0.5)->userName(),
            'birth_date' => rand(0, 100) < 70 ? $this->faker->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d') : null,
            'gender' => $this->faker->optional(0.8)->randomElement(['male', 'female', 'other']),
            'preferences' => $this->faker->optional(0.6)->randomElements([
                'newsletter' => $this->faker->boolean(),
                'notifications' => $this->faker->boolean(),
                'theme' => $this->faker->randomElement(['light', 'dark', 'auto']),
                'language' => $this->faker->randomElement(['en', 'vi', 'fr', 'es']),
            ]),
            'settings' => $this->faker->optional(0.5)->randomElements([
                'privacy_level' => $this->faker->randomElement(['public', 'friends', 'private']),
                'show_email' => $this->faker->boolean(),
                'show_phone' => $this->faker->boolean(),
            ]),
        ];
    }

    /**
     * Create complete profile.
     */
    public function complete(): static
    {
        return $this->state([
            'bio' => $this->faker->paragraph(),
            'birth_date' => $this->faker->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'gender' => $this->faker->randomElement(['male', 'female', 'other']),
        ]);
    }

    /**
     * Create and persist model.
     */
    public function create(array $attributes = []): UserProfileModel
    {
        $model = $this->make($attributes);
        if ($model instanceof UserProfileModel) {
            $model->save();
            return $model;
        }
        return UserProfileModel::create(array_merge($this->definition(), $attributes));
    }
}
