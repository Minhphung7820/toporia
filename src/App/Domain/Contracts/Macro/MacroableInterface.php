<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Macro;

/**
 * Macroable Interface
 *
 * Contract for classes that can be extended with macros.
 * Provides dynamic method extension capability.
 *
 * Clean Architecture:
 * - Domain layer defines the contract
 * - Framework layer provides implementation via trait
 *
 * SOLID Principles:
 * - Open/Closed: Classes can be extended without modification
 * - Single Responsibility: Defines macroable contract
 *
 * Performance:
 * - Macros are resolved at runtime
 * - Cached for performance
 */
interface MacroableInterface
{
    /**
     * Register a macro for this class.
     *
     * @param string $name Macro name (method name)
     * @param callable $callback Macro implementation
     * @return void
     */
    public static function macro(string $name, callable $callback): void;

    /**
     * Check if macro exists.
     *
     * @param string $name Macro name
     * @return bool True if macro exists
     */
    public static function hasMacro(string $name): bool;

    /**
     * Get macro callback.
     *
     * @param string $name Macro name
     * @return callable|null Macro callback or null if not found
     */
    public static function getMacro(string $name): ?callable;
}
