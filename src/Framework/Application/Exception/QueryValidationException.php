<?php

declare(strict_types=1);

namespace Toporia\Framework\Application\Exception;

/**
 * Query Validation Exception
 *
 * Thrown when query validation fails.
 *
 * @package Toporia\Framework\Application\Exception
 */
final class QueryValidationException extends ApplicationException
{
    /**
     * @param array<string, string> $errors Validation errors
     * @param string $message Exception message
     */
    public function __construct(
        public readonly array $errors = [],
        string $message = 'Query validation failed'
    ) {
        parent::__construct($message);
    }
}

