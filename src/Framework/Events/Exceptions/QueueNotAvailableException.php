<?php

declare(strict_types=1);

namespace Toporia\Framework\Events\Exceptions;

/**
 * Queue Not Available Exception
 *
 * Exception thrown when trying to queue a listener but queue service is not available.
 *
 * @package Toporia\Framework\Events\Exceptions
 */
class QueueNotAvailableException extends EventException
{
    /**
     * Create exception for missing queue service.
     *
     * @param string|null $listenerName Listener that requires queue
     * @return static
     */
    public static function forListener(?string $listenerName = null): static
    {
        $message = 'Queue service is required for queued listeners. Please register QueueServiceProvider.';

        if ($listenerName !== null) {
            $message = sprintf('Cannot queue listener "%s". %s', $listenerName, $message);
        }

        return new static($message, ['listener' => $listenerName]);
    }
}
