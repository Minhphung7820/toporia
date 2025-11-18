<?php

declare(strict_types=1);

namespace Toporia\Framework\Events\Contracts;

/**
 * Event Dispatcher Interface
 *
 * Contract for event dispatching and listener management.
 * Supports multiple listener types, wildcards, and queued execution.
 *
 * Clean Architecture:
 * - Dependency Inversion: Framework depends on abstraction
 * - Open/Closed: Extensible via implementations
 *
 * SOLID Principles:
 * - I: Interface Segregation - focused interface
 * - D: Dependency Inversion - depends on abstraction
 */
interface EventDispatcherInterface
{
    /**
     * Register an event listener.
     *
     * @param string $eventName Event name or class (supports wildcards: 'user.*')
     * @param callable|string|ListenerInterface $listener Callable, class name, or listener instance
     * @param int $priority Higher priority listeners execute first (default: 0)
     * @return void
     */
    public function listen(string $eventName, callable|string|ListenerInterface $listener, int $priority = 0): void;

    /**
     * Dispatch an event to all registered listeners.
     *
     * @param string|EventInterface $event Event name or event object
     * @param array $payload Event data (used if event is string)
     * @return EventInterface The event object after dispatch
     */
    public function dispatch(string|EventInterface $event, array $payload = []): EventInterface;

    /**
     * Check if an event has listeners.
     *
     * @param string $eventName Event name
     * @return bool
     */
    public function hasListeners(string $eventName): bool;

    /**
     * Remove all listeners for an event.
     *
     * @param string $eventName Event name
     * @return void
     */
    public function removeListeners(string $eventName): void;

    /**
     * Get all listeners for an event.
     *
     * @param string $eventName Event name
     * @return array<callable|ListenerInterface>
     */
    public function getListeners(string $eventName): array;
}
