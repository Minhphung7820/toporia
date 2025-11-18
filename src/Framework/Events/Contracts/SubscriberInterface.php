<?php

declare(strict_types=1);

namespace Toporia\Framework\Events\Contracts;

/**
 * Event Subscriber Interface
 *
 * Contract for event subscribers that listen to multiple events.
 * Subscribers can register multiple event-listener mappings.
 *
 * Performance:
 * - Allows bulk registration of listeners
 * - Reduces individual listen() calls
 *
 * Clean Architecture:
 * - Single Responsibility: Only handles event subscription
 * - Dependency Inversion: Framework depends on abstraction
 *
 * SOLID Principles:
 * - S: Only handles subscription logic
 * - O: Extensible via implementations
 * - I: Focused interface
 * - D: Depends on EventDispatcherInterface abstraction
 */
interface SubscriberInterface
{
    /**
     * Subscribe to events.
     *
     * Returns an array mapping event names to listeners.
     * Listener can be:
     * - callable (closure or array)
     * - string (method name on this subscriber)
     * - array [callable|string, priority]
     *
     * @param EventDispatcherInterface $dispatcher Event dispatcher instance
     * @return array<string, callable|string|array{0: callable|string, 1: int}> Event => listener mapping
     */
    public function subscribe(EventDispatcherInterface $dispatcher): array;
}

