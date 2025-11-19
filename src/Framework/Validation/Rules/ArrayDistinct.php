<?php

declare(strict_types=1);

namespace Toporia\Framework\Validation\Rules;

use Toporia\Framework\Validation\Contracts\RuleInterface;

/**
 * Array Distinct Rule
 *
 * Validates that all values in an array are unique.
 *
 * Performance: O(n) where n = array size
 *
 * @package Toporia\Framework\Validation\Rules
 */
final class ArrayDistinct implements RuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function passes(string $attribute, mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        // Check for duplicates using array_unique
        $unique = array_unique($value, SORT_REGULAR);
        return count($unique) === count($value);
    }

    /**
     * {@inheritdoc}
     */
    public function message(): string
    {
        return 'The :attribute must have unique values.';
    }
}

