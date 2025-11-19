<?php

declare(strict_types=1);

namespace Toporia\Framework\Validation\Rules;

use Toporia\Framework\Validation\Contracts\RuleInterface;

/**
 * Array Max Rule
 *
 * Validates that an array has at most N elements.
 *
 * Performance: O(1) - Simple count check
 *
 * @package Toporia\Framework\Validation\Rules
 */
final class ArrayMax implements RuleInterface
{
    /**
     * @param int $max Maximum number of elements
     */
    public function __construct(
        private readonly int $max
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function passes(string $attribute, mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        return count($value) <= $this->max;
    }

    /**
     * {@inheritdoc}
     */
    public function message(): string
    {
        return "The :attribute must not have more than {$this->max} items.";
    }
}

