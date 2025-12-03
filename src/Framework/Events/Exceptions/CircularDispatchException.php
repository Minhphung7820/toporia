<?php

declare(strict_types=1);

namespace Toporia\Framework\Events\Exceptions;

/**
 * Circular Dispatch Exception
 *
 * Exception thrown when circular event dispatch is detected.
 * This prevents infinite loops caused by listeners dispatching the same event.
 *
 * @package Toporia\Framework\Events\Exceptions
 */
class CircularDispatchException extends EventException
{
    /**
     * Create exception for max depth exceeded.
     *
     * @param array<string> $dispatchStack Current dispatch stack
     * @param string $eventName Event attempting to dispatch
     * @param int $maxDepth Maximum allowed depth
     * @return static
     */
    public static function maxDepthExceeded(array $dispatchStack, string $eventName, int $maxDepth): static
    {
        $chain = implode(' -> ', $dispatchStack) . ' -> ' . $eventName;

        return new static(
            sprintf(
                'Maximum event dispatch depth (%d) exceeded. Possible circular dispatch detected: %s',
                $maxDepth,
                $chain
            ),
            [
                'dispatch_stack' => $dispatchStack,
                'event_name' => $eventName,
                'max_depth' => $maxDepth,
            ]
        );
    }

    /**
     * Create exception for self-dispatch detected.
     *
     * @param string $eventName Event name
     * @return static
     */
    public static function selfDispatch(string $eventName): static
    {
        return new static(
            sprintf('Event "%s" is dispatching itself, causing infinite recursion.', $eventName),
            ['event_name' => $eventName]
        );
    }
}
