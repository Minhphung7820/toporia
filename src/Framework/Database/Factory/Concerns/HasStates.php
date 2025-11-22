<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\Factory\Concerns;

use Toporia\Framework\Database\Factory;

/**
 * Has States Trait
 *
 * Provides convenient methods for defining and applying state transformations.
 *
 * SOLID Principles:
 * - Single Responsibility: Only handles state management
 * - Open/Closed: States can be extended without modifying factory
 *
 * Usage:
 * ```php
 * UserFactory::new()
 *     ->admin()
 *     ->verified()
 *     ->create();
 * ```
 *
 * @mixin Factory
 */
trait HasStates
{
    /**
     * Apply multiple states at once.
     *
     * @param array<int, string|callable|array<string, mixed>> $states
     * @return static
     */
    public function states(array $states): static
    {
        foreach ($states as $state) {
            $this->state($state);
        }
        return $this;
    }

    /**
     * Magic method to call state methods dynamically.
     *
     * Allows: $factory->admin() instead of $factory->state('admin')
     *
     * @param string $name Method name
     * @param array<int, mixed> $arguments Arguments
     * @return static
     */
    public function __call(string $name, array $arguments): static
    {
        // If method exists, call it
        if (method_exists($this, $name)) {
            return $this->$name(...$arguments);
        }

        // Otherwise, treat as state name
        return $this->state($name);
    }

    /**
     * Reset states.
     *
     * @return static
     */
    public function resetStates(): static
    {
        $this->states = [];
        return $this;
    }
}

