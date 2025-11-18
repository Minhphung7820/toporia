<?php

declare(strict_types=1);

namespace Toporia\Framework\Events\Contracts;

/**
 * Listener Interface
 *
 * Contract for event listeners.
 * All listeners must implement this interface.
 *
 * Clean Architecture:
 * - Dependency Inversion: Framework depends on abstraction
 * - Open/Closed: Extensible via implementations
 *
 * SOLID Principles:
 * - I: Interface Segregation - focused interface
 * - D: Dependency Inversion - depends on abstraction
 */
interface ListenerInterface
{
    /**
     * Handle the event.
     *
     * @param EventInterface $event The event instance
     * @return void
     */
    public function handle(EventInterface $event): void;
}

