<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\Contracts;

use Toporia\Framework\Database\ORM\Model;

/**
 * Factory Interface
 *
 * Defines contract for model factories.
 *
 * SOLID Principles:
 * - Interface Segregation: Specific interface for factory operations
 * - Dependency Inversion: Depend on abstraction, not concrete implementations
 *
 * @template T of Model
 */
interface FactoryInterface
{
    /**
     * Create a new factory instance.
     *
     * @return static
     */
    public static function new(): static;

    /**
     * Create a new model instance (not persisted).
     *
     * @param array<string, mixed> $attributes
     * @return T
     */
    public function make(array $attributes = []): Model;

    /**
     * Create a model instance and persist it to database.
     *
     * @param array<string, mixed> $attributes
     * @return T
     */
    public function create(array $attributes = []): Model;

    /**
     * Create multiple model instances (not persisted).
     *
     * @param int $count
     * @param array<string, mixed> $attributes
     * @return array<int, T>
     */
    public function makeMany(int $count, array $attributes = []): array;

    /**
     * Create multiple model instances and persist them to database.
     *
     * @param int $count
     * @param array<string, mixed> $attributes
     * @return array<int, T>
     */
    public function createMany(int $count, array $attributes = []): array;

    /**
     * Apply state transformations.
     *
     * @param string|callable|array<string, mixed> $state
     * @return static
     */
    public function state(string|callable|array $state): static;

    /**
     * Define model's default attributes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array;
}

