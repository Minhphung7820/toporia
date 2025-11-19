<?php

declare(strict_types=1);

namespace Toporia\Framework\Validation\Rules;

use Toporia\Framework\Validation\Contracts\DataAwareRuleInterface;
use Toporia\Framework\Validation\ValidationData;

/**
 * Same Rule
 *
 * Validates that a field matches another field's value.
 * Data-aware rule - needs access to other fields.
 *
 * Usage:
 * ```php
 * 'password_confirmation' => [new Same('password')]
 * ```
 *
 * Performance: O(1) - Simple value comparison
 *
 * @package Toporia\Framework\Validation\Rules
 */
final class Same implements DataAwareRuleInterface
{
    /**
     * @var ValidationData|null All validation data
     */
    private ?ValidationData $data = null;

    /**
     * @param string $otherField The field to compare with
     */
    public function __construct(
        private readonly string $otherField
    ) {}

    /**
     * {@inheritdoc}
     */
    public function setData(ValidationData $data): void
    {
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function passes(string $attribute, mixed $value): bool
    {
        if ($this->data === null) {
            return false;
        }

        $otherValue = $this->data->get($this->otherField);
        return $value === $otherValue;
    }

    /**
     * {@inheritdoc}
     */
    public function message(): string
    {
        return "The :attribute must match {$this->otherField}.";
    }
}
