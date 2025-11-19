<?php

declare(strict_types=1);

namespace Toporia\Framework\Validation\Rules;

use Toporia\Framework\Validation\Contracts\RuleInterface;

/**
 * Array Min Rule
 *
 * Validates that an array has at least N elements.
 *
 * Performance: O(1) - Simple count check
 *
 * @package Toporia\Framework\Validation\Rules
 */
final class ArrayMin implements RuleInterface
{
    /**
     * @param int $min Minimum number of elements
     */
    public function __construct(
        private readonly int $min
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

        return count($value) >= $this->min;
    }

    /**
     * {@inheritdoc}
     */
    public function message(): string
    {
        return "The :attribute must have at least {$this->min} items.";
    }
}

