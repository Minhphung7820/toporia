<?php

declare(strict_types=1);

namespace Toporia\Framework\Validation\Rules;

use Toporia\Framework\Validation\Contracts\ImplicitRuleInterface;

/**
 * Required Rule
 *
 * Validates that a field is present and not empty.
 * Implicit rule - runs even when field is empty.
 *
 * Performance: O(1) - Fast presence check
 *
 * @package Toporia\Framework\Validation\Rules
 */
final class Required implements ImplicitRuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function passes(string $attribute, mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && count($value) === 0) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function message(): string
    {
        return 'The :attribute field is required.';
    }
}
