<?php

declare(strict_types=1);

namespace Toporia\Framework\Events;

use Toporia\Framework\Queue\Contracts\JobInterface;
use Toporia\Framework\Events\Contracts\{EventInterface, ListenerInterface};
use Toporia\Framework\Container\Contracts\ContainerInterface;

/**
 * Listener Job
 *
 * Job for executing queued event listeners.
 * Serializes listener and event for queue processing.
 *
 * Performance:
 * - Efficient serialization
 * - Lazy listener resolution
 *
 * Clean Architecture:
 * - Single Responsibility: Only handles listener job execution
 * - Dependency Inversion: Uses ContainerInterface
 *
 * SOLID Principles:
 * - S: Only executes queued listeners
 * - O: Extensible via different job implementations
 * - L: Implements JobInterface correctly
 * - I: Focused interface
 * - D: Depends on ContainerInterface abstraction
 */
final class ListenerJob implements JobInterface
{
    private string $id;
    private string $queue = 'default';
    private int $attempts = 0;
    private int $maxAttempts = 3;

    /**
     * @var ListenerInterface|callable|string
     */
    private $listener;

    /**
     * @var EventInterface
     */
    private EventInterface $event;

    /**
     * @param ListenerInterface|callable|string $listener Listener to execute
     * @param EventInterface $event Event instance
     * @throws \InvalidArgumentException If listener is a Closure (not serializable)
     */
    public function __construct(
        ListenerInterface|callable|string $listener,
        EventInterface $event
    ) {
        // CRITICAL: Closures cannot be serialized for queue storage
        // Only class-based listeners or string class names can be queued
        if ($listener instanceof \Closure) {
            throw new \InvalidArgumentException(
                'Closures cannot be queued. Use a class-based listener implementing ListenerInterface instead.'
            );
        }

        $this->id = uniqid('listener_job_', true);
        $this->listener = $listener;
        $this->event = $event;
    }

    /**
     * Execute the job.
     *
     * @param ContainerInterface $container Container for resolving listeners
     * @return void
     */
    public function handle(ContainerInterface $container): void
    {
        $listener = $this->resolveListener($container);

        if ($listener instanceof ListenerInterface) {
            $listener->handle($this->event);
        } elseif (is_callable($listener)) {
            $listener($this->event);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * {@inheritdoc}
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * {@inheritdoc}
     */
    public function incrementAttempts(): void
    {
        $this->attempts++;
    }

    /**
     * {@inheritdoc}
     */
    public function failed(\Throwable $exception): void
    {
        // Log failure or handle as needed
        error_log("ListenerJob failed: " . $exception->getMessage());
    }

    /**
     * {@inheritdoc}
     */
    public function getTimeout(): int
    {
        return 60; // Default 60 seconds timeout for event listeners
    }

    /**
     * {@inheritdoc}
     */
    public function timeout(): void
    {
        error_log("ListenerJob timed out: " . $this->displayName());
    }

    /**
     * {@inheritdoc}
     */
    public function getBackoffDelay(): int
    {
        return 3; // Default 3 seconds backoff delay
    }

    /**
     * Resolve listener from container if needed.
     *
     * @param ContainerInterface $container
     * @return ListenerInterface|callable
     */
    private function resolveListener(ContainerInterface $container): ListenerInterface|callable
    {
        if (is_string($this->listener) && $container->has($this->listener)) {
            return $container->get($this->listener);
        }

        if (is_string($this->listener) && class_exists($this->listener)) {
            return $container->get($this->listener);
        }

        return $this->listener;
    }

    /**
     * Get job display name.
     *
     * @return string
     */
    public function displayName(): string
    {
        $listenerName = is_string($this->listener)
            ? $this->listener
            : get_debug_type($this->listener);

        return "Event Listener: {$listenerName}";
    }

    /**
     * Get job unique ID.
     *
     * @return string
     */
    public function uniqueId(): string
    {
        return md5(serialize([$this->listener, $this->event->getName()]));
    }
}
