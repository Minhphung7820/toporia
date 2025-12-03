<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Exceptions;

/**
 * 419 Token Mismatch HTTP Exception (CSRF)
 *
 * Used when CSRF token validation fails.
 * Status 419 is a non-standard HTTP status used by Laravel/Symfony
 * to indicate CSRF token mismatch/expired session.
 *
 * @package Toporia\Framework\Http\Exceptions
 */
class TokenMismatchException extends HttpException
{
    public function __construct(
        string $message = 'CSRF token mismatch',
        ?\Throwable $previous = null
    ) {
        parent::__construct(419, $message, [], $previous);
    }
}
