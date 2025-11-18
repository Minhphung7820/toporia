<?php

declare(strict_types=1);

namespace Toporia\Framework\Events;

use Toporia\Framework\Events\Contracts\{EventInterface, ListenerInterface};

/**
 * Base Listener Class
 *
 * Base class for class-based event listeners.
 * Provides convenient handle() method implementation.
 *
 * Performance:
 * - O(1) method resolution
 * - No reflection overhead for simple listeners
 *
 * Clean Architecture:
 * - Single Responsibility: Only handles event listening
 * - Dependency Inversion: Implements ListenerInterface
 *
 * SOLID Principles:
 * - S: Only handles event listening
 * - O: Extensible via inheritance
 * - L: Implements interface correctly
 * - I: Focused interface
 * - D: Depends on EventInterface abstraction
 */
abstract class Listener implements ListenerInterface
{
    /**
     * Handle the event.
     *
     * @param EventInterface $event The event instance
     * @return void
     */
    public function handle(EventInterface $event): void
    {
        // Override in subclasses
    }
}
