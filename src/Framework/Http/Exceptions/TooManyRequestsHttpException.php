<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Exceptions;

/**
 * 429 Too Many Requests HTTP Exception
 *
 * @package Toporia\Framework\Http\Exceptions
 */
class TooManyRequestsHttpException extends HttpException
{
    /**
     * @param int|null $retryAfter Seconds until retry allowed
     * @param string $message Error message
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        ?int $retryAfter = null,
        string $message = 'Too Many Requests',
        ?\Throwable $previous = null
    ) {
        $headers = [];
        if ($retryAfter !== null) {
            $headers['Retry-After'] = (string) $retryAfter;
        }
        parent::__construct(429, $message, $headers, $previous);
    }

    /**
     * Get the retry-after value in seconds.
     *
     * @return int|null
     */
    public function getRetryAfter(): ?int
    {
        $value = $this->headers['Retry-After'] ?? null;
        return $value !== null ? (int) $value : null;
    }
}
