<?php

declare(strict_types=1);

namespace Toporia\Framework\Http\Exceptions;

/**
 * 404 Not Found HTTP Exception
 *
 * @package Toporia\Framework\Http\Exceptions
 */
class NotFoundHttpException extends HttpException
{
    public function __construct(string $message = 'Not Found', ?\Throwable $previous = null)
    {
        parent::__construct(404, $message, [], $previous);
    }
}
