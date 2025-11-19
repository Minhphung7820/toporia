<?php

declare(strict_types=1);

namespace App\Application\Rules;

use Toporia\Framework\Validation\Contracts\RuleInterface;

final class RuleTest implements RuleInterface
{
    /**
     * Determine if the validation rule passes.
     */
    public function passes(string $attribute, mixed $value): bool
    {
        return true;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The :attribute is invalid.';
    }
}
