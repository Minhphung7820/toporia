<?php

declare(strict_types=1);

namespace Toporia\Framework\Events;

use Toporia\Framework\Events\Contracts\{EventDispatcherInterface, SubscriberInterface};

/**
 * Base Subscriber Class
 *
 * Base class for event subscribers.
 * Provides convenient subscription methods.
 *
 * Performance:
 * - Bulk registration reduces dispatcher calls
 * - Efficient event-listener mapping
 *
 * Clean Architecture:
 * - Single Responsibility: Only handles event subscription
 * - Dependency Inversion: Implements SubscriberInterface
 *
 * SOLID Principles:
 * - S: Only handles subscription
 * - O: Extensible via inheritance
 * - L: Implements interface correctly
 * - I: Focused interface
 * - D: Depends on EventDispatcherInterface abstraction
 */
abstract class Subscriber implements SubscriberInterface
{
    /**
     * Subscribe to events.
     *
     * Override this method to define event subscriptions.
     *
     * @param EventDispatcherInterface $dispatcher Event dispatcher instance
     * @return array<string, callable|string|array{0: callable|string, 1: int}> Event => listener mapping
     */
    public function subscribe(EventDispatcherInterface $dispatcher): array
    {
        return [];
    }

    /**
     * Helper method to create listener mapping with priority.
     *
     * @param callable|string $listener Listener callable or method name
     * @param int $priority Listener priority
     * @return array{0: callable|string, 1: int}
     */
    protected function listener(callable|string $listener, int $priority = 0): array
    {
        return [$listener, $priority];
    }
}

