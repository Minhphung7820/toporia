<?php

declare(strict_types=1);

namespace Toporia\Framework\Container;

/**
 * Contextual Binding Builder
 *
 * Fluent builder for contextual bindings (Laravel-style).
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

/**
 * Needs Binding Builder
 *
 * Second part of contextual binding chain.
 */
final class NeedsBindingBuilder
{
    /**
     * @param Container $container Container instance
     * @param string $concrete Concrete class
     * @param string $abstract Abstract class/interface
     */
    public function __construct(
        private Container $container,
        private string $concrete,
        private string $abstract
    ) {}

    /**
     * Specify the implementation to use.
     *
     * @param callable|string $implementation Implementation class or factory
     * @return void
     */
    public function give(callable|string $implementation): void
    {
        $this->container->addContextualBinding($this->concrete, $this->abstract, $implementation);
    }
}
