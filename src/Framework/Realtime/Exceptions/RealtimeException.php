<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Exceptions;

use RuntimeException;

/**
 * Base exception for all Realtime module errors.
 *
 * @package Toporia\Framework\Realtime\Exceptions
 */
class RealtimeException extends RuntimeException
{
    /**
     * Create exception with context.
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context
     * @param int $code Error code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        protected array $context = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get exception context.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
