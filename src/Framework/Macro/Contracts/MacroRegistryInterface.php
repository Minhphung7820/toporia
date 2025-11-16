<?php

declare(strict_types=1);

namespace Toporia\Framework\Macro\Contracts;

/**
 * Macro Registry Interface
 *
 * Contract for macro registration and retrieval.
 * Provides centralized macro management with caching.
 *
 * Clean Architecture:
 * - Framework layer defines the contract (core functionality)
 * - Application layer provides implementation
 *
 * SOLID Principles:
 * - Single Responsibility: Manages macro registration
 * - Open/Closed: Extensible via implementations
 * - Dependency Inversion: Framework depends on this abstraction
 *
 * Performance:
 * - O(1) registration and lookup
 * - Cached macros for fast access
 * - Lazy evaluation support
 */
interface MacroRegistryInterface
{
    /**
     * Register a macro for a class or interface.
     *
     * @param string|class-string $target Target class or interface name
     * @param string $name Macro name (method name)
     * @param callable $callback Macro implementation
     * @return void
     */
    public function register(string $target, string $name, callable $callback): void;

    /**
     * Check if macro exists for target.
     *
     * @param string|class-string $target Target class or interface name
     * @param string $name Macro name
     * @return bool True if macro exists
     */
    public function has(string $target, string $name): bool;

    /**
     * Get macro callback for target.
     *
     * @param string|class-string $target Target class or interface name
     * @param string $name Macro name
     * @return callable|null Macro callback or null if not found
     */
    public function get(string $target, string $name): ?callable;

    /**
     * Get all macros for a target.
     *
     * @param string|class-string $target Target class or interface name
     * @return array<string, callable> Array of macro name => callback
     */
    public function getAll(string $target): array;

    /**
     * Remove a macro.
     *
     * @param string|class-string $target Target class or interface name
     * @param string $name Macro name
     * @return void
     */
    public function remove(string $target, string $name): void;

    /**
     * Clear all macros for a target.
     *
     * @param string|class-string $target Target class or interface name
     * @return void
     */
    public function clear(string $target): void;

    /**
     * Clear all registered macros.
     *
     * @return void
     */
    public function clearAll(): void;
}

