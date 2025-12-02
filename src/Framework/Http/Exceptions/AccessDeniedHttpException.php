<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Exceptions;

/**
 * 403 Forbidden HTTP Exception
 *
 * @package Toporia\Framework\Http\Exceptions
 */
class AccessDeniedHttpException extends HttpException
{
    public function __construct(string $message = 'Forbidden', ?\Throwable $previous = null)
    {
        parent::__construct(403, $message, [], $previous);
    }
}
