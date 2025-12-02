<?php

declare(strict_types=1);

namespace Toporia\Framework\Container;

use Closure;

/**
 * Needs Binding Builder
 *
 * Second part of contextual binding chain.
 *
 * Usage:
 * ```php
 * $container->when(ConcreteClass::class)
 *     ->needs(AbstractInterface::class)
 *     ->give(ConcreteImplementation::class);
 *
 * // With factory closure
 * $container->when(Service::class)
 *     ->needs('$config')
 *     ->give(fn($container) => $container->make('config')->get('service'));
 *
 * // Give tagged services
 * $container->when(EventDispatcher::class)
 *     ->needs(ListenerInterface::class)
 *     ->giveTagged('event.listeners');
 * ```
 *
 * Clean Architecture:
 * - Single Responsibility: Needs binding builder only
 * - Fluent Interface: Method chaining for readability
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     2.0.0
 */
final class NeedsBindingBuilder
{
    /**
     * @param Container $container Container instance
     * @param array<string> $concrete Concrete classes
     * @param string $abstract Abstract class/interface
     */
    public function __construct(
        private Container $container,
        private array $concrete,
        private string $abstract
    ) {}

    /**
     * Specify the implementation to use.
     *
     * @param callable|string|array $implementation Implementation class, factory, or array
     * @return void
     */
    public function give(callable|string|array $implementation): void
    {
        foreach ($this->concrete as $concrete) {
            $this->container->addContextualBinding($concrete, $this->abstract, $implementation);
        }
    }

    /**
     * Specify that tagged services should be given.
     *
     * @param string $tag Tag name
     * @return void
     */
    public function giveTagged(string $tag): void
    {
        $this->give(function (Container $container) use ($tag) {
            return iterator_to_array($container->tagged($tag));
        });
    }

    /**
     * Specify a configuration value to be given.
     *
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return void
     */
    public function giveConfig(string $key, mixed $default = null): void
    {
        $this->give(function (Container $container) use ($key, $default) {
            $config = $container->make('config');
            return method_exists($config, 'get')
                ? $config->get($key, $default)
                : $default;
        });
    }
}
