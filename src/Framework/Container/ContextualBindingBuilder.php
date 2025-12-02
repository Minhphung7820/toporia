<?php

declare(strict_types=1);

namespace Toporia\Framework\Container;

/**
 * Contextual Binding Builder
 *
 * Fluent builder for contextual bindings.
 *
 * Usage:
 * ```php
 * // Single concrete
 * $container->when(ConcreteClass::class)
 *     ->needs(AbstractInterface::class)
 *     ->give(ConcreteImplementation::class);
 *
 * // Multiple concretes
 * $container->when([ConcreteA::class, ConcreteB::class])
 *     ->needs(AbstractInterface::class)
 *     ->give(SharedImplementation::class);
 * ```
 *
 * Clean Architecture:
 * - Single Responsibility: Contextual binding builder only
 * - Fluent Interface: Method chaining for readability
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     2.0.0
 */
final class ContextualBindingBuilder
{
    /**
     * @param Container $container Container instance
     * @param array<string> $concrete Concrete classes that need the binding
     */
    public function __construct(
        private Container $container,
        private array $concrete
    ) {}

    /**
     * Specify the abstract class/interface that needs binding.
     *
     * @param string $abstract Abstract class/interface
     * @return NeedsBindingBuilder
     */
    public function needs(string $abstract): NeedsBindingBuilder
    {
        return new NeedsBindingBuilder($this->container, $this->concrete, $abstract);
    }
}
