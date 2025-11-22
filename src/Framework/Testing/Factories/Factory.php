<?php

declare(strict_types=1);

namespace Toporia\Framework\Testing\Factories;

use Faker\Generator;


/**
 * Abstract Class Factory
 *
 * Abstract base class for Factory implementations in the Factories layer
 * providing common functionality and contracts.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Factories
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
abstract class Factory
{
    /**
     * Faker instance.
     */
    protected Generator $faker;

    /**
     * Model class name.
     */
    protected string $model;

    /**
     * Default attributes.
     */
    protected array $defaultAttributes = [];

    public function __construct()
    {
        $this->faker = \Faker\Factory::create();
    }

    /**
     * Define the model's default state.
     *
     * Override in child classes.
     */
    abstract protected function definition(): array;

    /**
     * Create a new factory instance.
     *
     * Performance: O(1)
     */
    public static function new(): static
    {
        return new static();
    }

    /**
     * Create a model instance.
     *
     * Performance: O(N) where N = number of attributes
     */
    public function make(array $attributes = []): mixed
    {
        $attributes = array_merge($this->defaultAttributes, $this->definition(), $attributes);
        return $this->createModel($attributes);
    }

    /**
     * Create multiple model instances.
     *
     * Performance: O(N*M) where N = count, M = attributes per model
     */
    public function makeMany(int $count, array $attributes = []): array
    {
        $models = [];
        for ($i = 0; $i < $count; $i++) {
            $models[] = $this->make($attributes);
        }
        return $models;
    }

    /**
     * Set default attributes.
     *
     * Performance: O(1)
     */
    public function state(array $attributes): self
    {
        $this->defaultAttributes = array_merge($this->defaultAttributes, $attributes);
        return $this;
    }

    /**
     * Create the model instance.
     *
     * Performance: O(N) where N = number of attributes
     */
    protected function createModel(array $attributes): mixed
    {
        if ($this->model) {
            return new $this->model(...$attributes);
        }

        // Fallback: return array if no model class
        return $attributes;
    }
}
