<?php

declare(strict_types=1);

namespace Toporia\Framework\Events;

use Toporia\Framework\Events\Contracts\{EventInterface, ListenerInterface, ShouldQueue};
use Toporia\Framework\Queue\Contracts\QueueInterface;

/**
 * Queued Listener Wrapper
 *
 * Wraps a listener to queue it for asynchronous execution.
 * Implements ShouldQueue marker interface.
 *
 * Performance:
 * - Offloads listener execution to background
 * - Improves response time
 * - Supports delayed execution
 *
 * Clean Architecture:
 * - Single Responsibility: Only handles queuing
 * - Decorator pattern: Wraps existing listener
 * - Dependency Inversion: Uses QueueInterface
 *
 * SOLID Principles:
 * - S: Only handles queuing
 * - O: Extensible via different queue implementations
 * - L: Behaves like ListenerInterface
 * - I: Focused interface
 * - D: Depends on QueueInterface abstraction
 */
final class QueuedListener implements ListenerInterface, ShouldQueue
{
    /**
     * @var callable|string|ListenerInterface
     */
    private $listener;

    /**
     * @param callable|string|ListenerInterface $listener The listener to queue
     * @param QueueInterface $queue Queue instance
     * @param int $delay Delay in seconds before execution
     */
    public function __construct(
        callable|string|ListenerInterface $listener,
        private QueueInterface $queue,
        private int $delay = 0
    ) {
        $this->listener = $listener;
    }

    /**
     * Handle the event by queuing it.
     *
     * @param EventInterface $event The event instance
     * @return void
     */
    public function handle(EventInterface $event): void
    {
        $job = new ListenerJob($this->listener, $event);

        if ($this->delay > 0) {
            $this->queue->later($job, $this->delay);
        } else {
            $this->queue->push($job);
        }
    }
}
