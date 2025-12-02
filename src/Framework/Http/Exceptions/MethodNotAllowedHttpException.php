<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Exceptions;

/**
 * 405 Method Not Allowed HTTP Exception
 *
 * @package Toporia\Framework\Http\Exceptions
 */
class MethodNotAllowedHttpException extends HttpException
{
    /**
     * @param array<string> $allowedMethods Allowed HTTP methods
     * @param string $message Error message
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        array $allowedMethods = [],
        string $message = 'Method Not Allowed',
        ?\Throwable $previous = null
    ) {
        $headers = [];
        if (!empty($allowedMethods)) {
            $headers['Allow'] = implode(', ', $allowedMethods);
        }
        parent::__construct(405, $message, $headers, $previous);
    }
}
