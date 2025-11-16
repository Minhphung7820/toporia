<?php

declare(strict_types=1);

namespace Toporia\Framework\Support;

use App\Domain\Macro\MacroRegistryInterface;
use Toporia\Framework\Container\Container;
use Toporia\Framework\Container\Contracts\ContainerInterface;

/**
 * Macroable Trait
 *
 * Provides dynamic method extension capability via macros.
 * Allows adding methods to classes without modifying their source code.
 *
 * Clean Architecture:
 * - Framework layer provides implementation
 * - Uses Domain MacroRegistryInterface via DI
 *
 * SOLID Principles:
 * - Open/Closed: Classes can be extended without modification
 * - Single Responsibility: Provides macro functionality
 * - Dependency Inversion: Depends on MacroRegistryInterface abstraction
 *
 * Performance:
 * - O(1) macro lookup via registry
 * - Cached macros for fast access
 * - Lazy evaluation
 *
 * Usage:
 * ```php
 * use Toporia\Framework\Support\Macroable;
 *
 * class MyClass
 * {
 *     use Macroable;
 * }
 *
 * // Register macro
 * MyClass::macro('customMethod', function($arg) {
 *     return $this->doSomething($arg);
 * });
 *
 * // Use macro
 * $instance = new MyClass();
 * $result = $instance->customMethod('value');
 * ```
 */
trait Macroable
{
    /**
     * Register a macro for this class.
     *
     * @param string $name Macro name (method name)
     * @param callable $callback Macro implementation
     * @return void
     */
    public static function macro(string $name, callable $callback): void
    {
        $registry = self::getMacroRegistry();
        $target = static::class;
        $registry->register($target, $name, $callback);
    }

    /**
     * Check if macro exists.
     *
     * @param string $name Macro name
     * @return bool True if macro exists
     */
    public static function hasMacro(string $name): bool
    {
        $registry = self::getMacroRegistry();
        $target = static::class;
        return $registry->has($target, $name);
    }

    /**
     * Get macro callback.
     *
     * @param string $name Macro name
     * @return callable|null Macro callback or null if not found
     */
    public static function getMacro(string $name): ?callable
    {
        $registry = self::getMacroRegistry();
        $target = static::class;
        return $registry->get($target, $name);
    }

    /**
     * Handle dynamic method calls.
     *
     * @param string $method Method name
     * @param array<mixed> $parameters Method parameters
     * @return mixed Method result
     * @throws \BadMethodCallException If method not found
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Check for macro
        $macro = static::getMacro($method);
        if ($macro !== null) {
            // Bind $this to macro callback
            $boundMacro = $macro->bindTo($this, static::class);
            return $boundMacro(...$parameters);
        }

        // Check parent class for method
        if (method_exists($this, $method)) {
            return $this->$method(...$parameters);
        }

        throw new \BadMethodCallException(
            sprintf(
                'Method %s::%s does not exist and no macro registered.',
                static::class,
                $method
            )
        );
    }

    /**
     * Handle static method calls.
     *
     * @param string $method Method name
     * @param array<mixed> $parameters Method parameters
     * @return mixed Method result
     * @throws \BadMethodCallException If method not found
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        // Check for macro
        $macro = static::getMacro($method);
        if ($macro !== null) {
            return $macro(...$parameters);
        }

        throw new \BadMethodCallException(
            sprintf(
                'Static method %s::%s does not exist and no macro registered.',
                static::class,
                $method
            )
        );
    }

    /**
     * Get macro registry instance.
     *
     * @return MacroRegistryInterface Macro registry
     */
    private static function getMacroRegistry(): MacroRegistryInterface
    {
        // Try to get from container first (if available)
        try {
            if (Container::hasInstance()) {
                $container = Container::getInstance();
                if ($container instanceof ContainerInterface && $container->has(MacroRegistryInterface::class)) {
                    return $container->get(MacroRegistryInterface::class);
                }
            }
        } catch (\Throwable $e) {
            // Container not available, fallback to singleton
        }

        // Fallback to singleton instance
        static $registry = null;
        if ($registry === null) {
            $registry = new \App\Infrastructure\Macro\MacroRegistry();
        }

        return $registry;
    }
}
