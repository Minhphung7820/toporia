<?php

declare(strict_types=1);

namespace Toporia\Framework\Validation\Contracts;

use Toporia\Framework\Validation\ValidationData;

/**
 * Data Aware Rule Interface
 *
 * Rules implementing this interface receive access to all validation data.
 * This is useful for rules that need to compare with other fields (e.g., "same", "different").
 *
 * SOLID Principles:
 * - Interface Segregation: Separate interface for data-aware rules
 * - Dependency Inversion: Depends on ValidationData abstraction
 *
 * Performance:
 * - ValidationData is passed by reference (no copying)
 * - Rules can cache computed values from data
 *
 * @package Toporia\Framework\Validation\Contracts
 */
interface DataAwareRuleInterface extends RuleInterface
{
    /**
     * Set validation data for this rule.
     *
     * Called once before validation starts.
     * Rule can cache/precompute values from data here.
     *
     * @param ValidationData $data All validation data
     * @return void
     */
    public function setData(ValidationData $data): void;
}

