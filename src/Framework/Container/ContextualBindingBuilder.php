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
 * $container->when(ConcreteClass::class)
 *     ->needs(AbstractInterface::class)
 *     ->give(ConcreteImplementation::class);
 * ```
 *
 * Clean Architecture:
 * - Single Responsibility: Contextual binding builder only
 * - Fluent Interface: Method chaining for readability
 */
final class ContextualBindingBuilder
{
    /**
     * @param Container $container Container instance
     * @param string $concrete Concrete class that needs the binding
     */
    public function __construct(
        private Container $container,
        private string $concrete
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
