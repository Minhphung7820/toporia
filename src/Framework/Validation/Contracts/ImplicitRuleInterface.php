<?php

declare(strict_types=1);

namespace Toporia\Framework\Validation\Contracts;

/**
 * Implicit Rule Interface
 *
 * Rules implementing this interface will run even when the field is empty/null.
 * This is useful for rules like "required" that need to check presence.
 *
 * SOLID Principles:
 * - Interface Segregation: Separate interface for implicit rules
 * - Open/Closed: Extensible without modifying Validator core
 *
 * Performance:
 * - Implicit rules are checked first (fail-fast optimization)
 * - Can short-circuit validation if field is required but missing
 *
 * @package Toporia\Framework\Validation\Contracts
 */
interface ImplicitRuleInterface extends RuleInterface
{
    // Marker interface - no additional methods required
    // The presence of this interface signals to Validator that
    // this rule should run even when value is null/empty
}

