<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Macro;

/**
 * Macro Interface
 *
 * Contract for macro definitions.
 * Macros allow extending classes with new methods dynamically.
 *
 * Clean Architecture:
 * - Domain layer defines the contract
 * - Infrastructure layer provides implementations
 *
 * SOLID Principles:
 * - Single Responsibility: Defines macro contract
 * - Open/Closed: Extensible via implementations
 * - Dependency Inversion: Depends on abstraction
 *
 * Performance:
 * - Macros are cached after first registration
 * - O(1) lookup via registry
 */
interface MacroInterface
{
    /**
     * Get macro name.
     *
     * @return string Macro name (method name)
     */
    public function getName(): string;

    /**
     * Get macro callback.
     *
     * @return callable Macro implementation
     */
    public function getCallback(): callable;

    /**
     * Get target class or interface.
     *
     * @return class-string|string Target class/interface name
     */
    public function getTarget(): string;

    /**
     * Check if macro is bound to specific instance.
     *
     * @return bool True if bound to instance
     */
    public function isInstanceBound(): bool;
}
