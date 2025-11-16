<?php

declare(strict_types=1);

namespace Toporia\Framework\Application\Exception;

/**
 * Command Validation Exception
 *
 * Thrown when command validation fails.
 *
 * @package Toporia\Framework\Application\Exception
 */
final class CommandValidationException extends ApplicationException
{
    /**
     * @param array<string, string> $errors Validation errors
     * @param string $message Exception message
     */
    public function __construct(
        public readonly array $errors = [],
        string $message = 'Command validation failed'
    ) {
        parent::__construct($message);
    }
}

