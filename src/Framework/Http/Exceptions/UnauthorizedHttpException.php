<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Exceptions;

/**
 * 401 Unauthorized HTTP Exception
 *
 * @package Toporia\Framework\Http\Exceptions
 */
class UnauthorizedHttpException extends HttpException
{
    public function __construct(
        string $challenge = '',
        string $message = 'Unauthorized',
        ?\Throwable $previous = null
    ) {
        $headers = [];
        if ($challenge !== '') {
            $headers['WWW-Authenticate'] = $challenge;
        }
        parent::__construct(401, $message, $headers, $previous);
    }
}
