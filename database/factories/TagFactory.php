<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Models\TagModel;
use Toporia\Framework\Database\Factories\Factory;

/**
 * Tag Factory for testing.
 */
class TagFactory extends Factory
{
    protected string $model = TagModel::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => strtolower($name),
            'description' => $this->faker->optional(0.7)->sentence(),
            'color' => $this->faker->hexColor(),
            'is_active' => $this->faker->boolean(85), // 85% active
            'usage_count' => $this->faker->numberBetween(0, 1000),
        ];
    }

    /**
     * Create popular tag.
     */
    public function popular(): static
    {
        return $this->state([
            'usage_count' => $this->faker->numberBetween(500, 2000),
            'is_active' => true,
        ]);
    }

    /**
     * Create tech-related tag.
     */
    public function tech(): static
    {
        $techTerms = ['PHP', 'JavaScript', 'Python', 'React', 'Vue', 'Laravel', 'Docker', 'AWS', 'MySQL', 'Redis'];
        $name = $this->faker->randomElement($techTerms);

        return $this->state([
            'name' => $name,
            'slug' => strtolower($name),
            'color' => '#007bff',
            'is_active' => true,
        ]);
    }

    /**
     * Create and persist model.
     */
    public function create(array $attributes = []): TagModel
    {
        $model = $this->make($attributes);
        if ($model instanceof TagModel) {
            $model->save();
            return $model;
        }
        return TagModel::create(array_merge($this->definition(), $attributes));
    }
}
