<?php

declare(strict_types=1);

namespace Toporia\Framework\Events\Exceptions;

/**
 * Listener Exception
 *
 * Exception thrown when listener-related errors occur.
 *
 * @package Toporia\Framework\Events\Exceptions
 */
class ListenerException extends EventException
{
    /**
     * Create exception for unresolvable listener.
     *
     * @param string $listener Listener class/identifier
     * @param string|null $eventName Event name (optional)
     * @return static
     */
    public static function unresolvable(string $listener, ?string $eventName = null): static
    {
        $message = sprintf(
            'Unable to resolve listener "%s". Must be callable, implement ListenerInterface, or be a valid class name.',
            $listener
        );

        return new static($message, [
            'listener' => $listener,
            'event_name' => $eventName,
        ]);
    }

    /**
     * Create exception for closure in queued context.
     *
     * @param string|null $eventName Event name
     * @return static
     */
    public static function closureNotQueueable(?string $eventName = null): static
    {
        return new static(
            'Closures cannot be queued for asynchronous execution. Use a class implementing ListenerInterface instead.',
            ['event_name' => $eventName]
        );
    }

    /**
     * Create exception for listener execution failure.
     *
     * @param string $listener Listener identifier
     * @param string $eventName Event name
     * @param \Throwable $previous Original exception
     * @return static
     */
    public static function executionFailed(string $listener, string $eventName, \Throwable $previous): static
    {
        return new static(
            sprintf('Listener "%s" failed while handling event "%s": %s', $listener, $eventName, $previous->getMessage()),
            ['listener' => $listener, 'event_name' => $eventName],
            $previous
        );
    }
}
