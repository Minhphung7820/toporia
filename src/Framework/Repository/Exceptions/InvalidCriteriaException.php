<?php

declare(strict_types=1);

namespace Toporia\Framework\Repository\Exceptions;

/**
 * Exception thrown when invalid criteria is provided.
 *
 * @package Toporia\Framework\Repository\Exceptions
 */
class InvalidCriteriaException extends RepositoryException
{
    public function __construct(string $message = 'Invalid criteria provided')
    {
        parent::__construct($message);
    }
}
