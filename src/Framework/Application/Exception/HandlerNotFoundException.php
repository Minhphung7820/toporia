<?php

declare(strict_types=1);

namespace Toporia\Framework\Application\Exception;

/**
 * Handler Not Found Exception
 *
 * Thrown when a handler cannot be resolved for a command or query.
 *
 * @package Toporia\Framework\Application\Exception
 */
final class HandlerNotFoundException extends ApplicationException
{
    /**
     * Create exception for a message that has no handler.
     *
     * @param object $message Command or Query object
     * @return self
     */
    public static function forMessage(object $message): self
    {
        $messageClass = get_class($message);

        // Try to determine expected handler name
        $handlerClass = preg_replace('/(Command|Query)$/', 'Handler', $messageClass);

        return new self(
            sprintf(
                'Handler not found for %s. Expected handler: %s',
                $messageClass,
                $handlerClass
            )
        );
    }
}

