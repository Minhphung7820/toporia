<?php

declare(strict_types=1);

namespace Toporia\Framework\Container;

/**
 * Needs Binding Builder
 *
 * Second part of contextual binding chain (Laravel-style).
 *
 * Usage:
 * ```php
 * $container->when(ConcreteClass::class)
 *     ->needs(AbstractInterface::class)
 *     ->give(ConcreteImplementation::class);
 * ```
 *
 * Clean Architecture:
 * - Single Responsibility: Needs binding builder only
 * - Fluent Interface: Method chaining for readability
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
