<?php

declare(strict_types=1);

namespace Toporia\Framework\Validation\Contracts;

/**
 * Rule Interface
 *
 * Base contract for all validation rules.
 *
 * SOLID Principles:
 * - Single Responsibility: Each rule validates one specific condition
 * - Open/Closed: Open for extension via new rule classes
 * - Interface Segregation: Focused interface for rule validation
 * - Dependency Inversion: Validator depends on RuleInterface abstraction
 *
 * Performance:
 * - Rules are stateless by default (can be cached/reused)
 * - No side effects in passes() method
 * - Fast validation with O(1) complexity where possible
 *
 * @package Toporia\Framework\Validation\Contracts
 */
interface RuleInterface
{
    /**
     * Determine if the validation rule passes.
     *
     * This method is called for each field value during validation.
     * It should be pure (no side effects) and fast (O(1) or O(n) where n is value size).
     *
     * @param string $attribute The attribute name being validated
     * @param mixed $value The value being validated
     * @return bool True if validation passes, false otherwise
     */
    public function passes(string $attribute, mixed $value): bool;

    /**
     * Get the validation error message.
     *
     * This method is called when validation fails.
     * It should return a human-readable error message.
     *
     * Performance: Called only on validation failure (lazy evaluation)
     *
     * @return string The error message (supports :attribute placeholder)
     */
    public function message(): string;
}

