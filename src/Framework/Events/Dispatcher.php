<?php

declare(strict_types=1);

namespace Toporia\Framework\Events;

use Toporia\Framework\Events\Contracts\{EventDispatcherInterface, EventInterface, ListenerInterface, SubscriberInterface, ShouldQueue};
use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Queue\Contracts\QueueInterface;

/**
 * Event Dispatcher
 *
 * Advanced event dispatcher with support for:
 * - Priority-based listener execution
 * - Wildcard event listeners
 * - Class-based listeners
 * - Queued listeners
 * - Event subscribers
 * - Event discovery
 * - Performance optimizations (caching, lazy loading)
 *
 * Performance:
 * - O(1) listener registration
 * - O(N) dispatch where N = listeners (with caching)
 * - O(1) wildcard matching (pattern cache)
 * - Lazy listener resolution
 *
 * Clean Architecture:
 * - Single Responsibility: Only handles event dispatching
 * - Dependency Inversion: Uses interfaces
 * - Open/Closed: Extensible via listeners and subscribers
 *
 * SOLID Principles:
 * - S: Only handles event dispatching
 * - O: Extensible via listeners
 * - L: All listeners interchangeable
 * - I: Focused interfaces
 * - D: Depends on abstractions
 */
final class Dispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, array<int, array<callable|string|ListenerInterface>>> Event listeners
     */
    private array $listeners = [];

    /**
     * @var array<string, array<callable|ListenerInterface>>|null Sorted listeners cache
     */
    private ?array $sortedListeners = null;

    /**
     * @var array<string, array<callable|string|ListenerInterface>> Wildcard listeners
     */
    private array $wildcards = [];

    /**
     * @var array<string, bool> Wildcard pattern cache
     */
    private array $wildcardCache = [];

    /**
     * @param ContainerInterface|null $container Container for resolving listeners
     * @param QueueInterface|null $queue Queue for queued listeners
     */
    public function __construct(
        private ?ContainerInterface $container = null,
        private ?QueueInterface $queue = null
    ) {}

    /**
     * {@inheritdoc}
     */
    public function listen(string $eventName, callable|string|ListenerInterface $listener, int $priority = 0): void
    {
        // Support wildcard listeners
        if ($this->isWildcard($eventName)) {
            $this->wildcards[$eventName][$priority][] = $listener;
        } else {
            $this->listeners[$eventName][$priority][] = $listener;
        }

        // Invalidate cache
        $this->sortedListeners = null;
        $this->wildcardCache = [];
    }

    /**
     * Register a class-based listener.
     *
     * @param string $eventName Event name
     * @param string|ListenerInterface $listener Listener class name or instance
     * @param int $priority Listener priority
     * @return void
     */
    public function listenClass(string $eventName, string|ListenerInterface $listener, int $priority = 0): void
    {
        $this->listen($eventName, $listener, $priority);
    }

    /**
     * Register a queued listener.
     *
     * @param string $eventName Event name
     * @param callable|string|ListenerInterface $listener Listener to queue
     * @param int $priority Listener priority
     * @param int $delay Delay in seconds
     * @return void
     */
    public function listenQueue(string $eventName, callable|string|ListenerInterface $listener, int $priority = 0, int $delay = 0): void
    {
        if ($this->queue === null) {
            throw new \RuntimeException('Queue service is required for queued listeners. Please register QueueServiceProvider.');
        }

        $queuedListener = new QueuedListener($listener, $this->queue, $delay);
        $this->listen($eventName, $queuedListener, $priority);
    }

    /**
     * Register an event subscriber.
     *
     * @param SubscriberInterface|string $subscriber Subscriber instance or class name
     * @return void
     */
    public function subscribe(SubscriberInterface|string $subscriber): void
    {
        // Resolve subscriber from container if string
        if (is_string($subscriber)) {
            if ($this->container === null) {
                throw new \RuntimeException('Container is required for subscriber resolution. Please register ContainerInterface.');
            }

            $subscriber = $this->container->get($subscriber);
            if (!$subscriber instanceof SubscriberInterface) {
                throw new \InvalidArgumentException("Subscriber must implement SubscriberInterface: {$subscriber}");
            }
        }

        // Get subscriptions from subscriber
        $subscriptions = $subscriber->subscribe($this);

        // Register all subscriptions
        foreach ($subscriptions as $eventName => $listener) {
            if (is_array($listener)) {
                [$callable, $priority] = $listener;
                $this->registerListener($eventName, $callable, $priority);
            } else {
                $this->registerListener($eventName, $listener);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(string|EventInterface $event, array $payload = []): EventInterface
    {
        // Convert string to GenericEvent
        if (is_string($event)) {
            $eventName = $event;
            $event = new GenericEvent($eventName, $payload);
        } else {
            $eventName = $event->getName();
        }

        // Get all listeners (direct + wildcard)
        $listeners = $this->getAllListeners($eventName);

        // Dispatch to each listener
        foreach ($listeners as $listener) {
            // Stop if propagation was stopped
            if ($event->isPropagationStopped()) {
                break;
            }

            $this->callListener($listener, $event);
        }

        return $event;
    }

    /**
     * {@inheritdoc}
     */
    public function hasListeners(string $eventName): bool
    {
        // Check direct listeners
        if (!empty($this->listeners[$eventName])) {
            return true;
        }

        // Check wildcard listeners
        return $this->hasWildcardListeners($eventName);
    }

    /**
     * {@inheritdoc}
     */
    public function removeListeners(string $eventName): void
    {
        unset($this->listeners[$eventName]);
        $this->sortedListeners = null;
        $this->wildcardCache = [];
    }

    /**
     * {@inheritdoc}
     */
    public function getListeners(string $eventName): array
    {
        return $this->getAllListeners($eventName);
    }

    /**
     * Get all listeners for an event (direct + wildcard).
     *
     * @param string $eventName Event name
     * @return array<callable|ListenerInterface>
     */
    private function getAllListeners(string $eventName): array
    {
        $listeners = [];

        // Get direct listeners
        if (isset($this->listeners[$eventName])) {
            $listeners = array_merge($listeners, $this->getSortedListeners($eventName));
        }

        // Get wildcard listeners
        $wildcardListeners = $this->getWildcardListeners($eventName);
        $listeners = array_merge($listeners, $wildcardListeners);

        return $listeners;
    }

    /**
     * Get sorted listeners for an event.
     *
     * @param string $eventName Event name
     * @return array<callable|ListenerInterface>
     */
    private function getSortedListeners(string $eventName): array
    {
        // Return cached if available
        if (isset($this->sortedListeners[$eventName])) {
            return $this->sortedListeners[$eventName];
        }

        // Sort listeners by priority (higher priority first)
        $prioritizedListeners = $this->listeners[$eventName];
        krsort($prioritizedListeners);

        // Flatten and resolve listeners
        $sorted = [];
        foreach ($prioritizedListeners as $priority => $listeners) {
            foreach ($listeners as $listener) {
                $sorted[] = $this->resolveListener($listener);
            }
        }

        // Cache the sorted result
        $this->sortedListeners[$eventName] = $sorted;

        return $sorted;
    }

    /**
     * Get wildcard listeners matching event name.
     *
     * @param string $eventName Event name
     * @return array<callable|ListenerInterface>
     */
    private function getWildcardListeners(string $eventName): array
    {
        $listeners = [];

        foreach ($this->wildcards as $pattern => $prioritizedListeners) {
            if ($this->matchesWildcard($pattern, $eventName)) {
                krsort($prioritizedListeners);
                foreach ($prioritizedListeners as $priority => $patternListeners) {
                    foreach ($patternListeners as $listener) {
                        $listeners[] = $this->resolveListener($listener);
                    }
                }
            }
        }

        return $listeners;
    }

    /**
     * Check if event name matches wildcard pattern.
     *
     * @param string $pattern Wildcard pattern
     * @param string $eventName Event name
     * @return bool
     */
    private function matchesWildcard(string $pattern, string $eventName): bool
    {
        $cacheKey = "{$pattern}:{$eventName}";
        if (isset($this->wildcardCache[$cacheKey])) {
            return $this->wildcardCache[$cacheKey];
        }

        // Convert wildcard pattern to regex
        $regex = '#^' . str_replace(['*', '.'], ['.*', '\.'], $pattern) . '$#';
        $matches = preg_match($regex, $eventName) === 1;

        // Cache result
        $this->wildcardCache[$cacheKey] = $matches;

        return $matches;
    }

    /**
     * Check if event name is a wildcard pattern.
     *
     * @param string $eventName Event name
     * @return bool
     */
    private function isWildcard(string $eventName): bool
    {
        return str_contains($eventName, '*');
    }

    /**
     * Check if event has wildcard listeners.
     *
     * @param string $eventName Event name
     * @return bool
     */
    private function hasWildcardListeners(string $eventName): bool
    {
        foreach (array_keys($this->wildcards) as $pattern) {
            if ($this->matchesWildcard($pattern, $eventName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve listener (string to instance).
     *
     * @param callable|string|ListenerInterface $listener Listener
     * @return callable|ListenerInterface
     */
    private function resolveListener(callable|string|ListenerInterface $listener): callable|ListenerInterface
    {
        // Already resolved
        if (is_callable($listener) || $listener instanceof ListenerInterface) {
            return $listener;
        }

        // Resolve from container
        if (is_string($listener) && $this->container !== null) {
            if ($this->container->has($listener)) {
                return $this->container->get($listener);
            }

            if (class_exists($listener)) {
                return $this->container->get($listener);
            }
        }

        return $listener;
    }

    /**
     * Call listener with event.
     *
     * @param callable|ListenerInterface $listener Listener
     * @param EventInterface $event Event instance
     * @return void
     */
    private function callListener(callable|ListenerInterface $listener, EventInterface $event): void
    {
        if ($listener instanceof ListenerInterface) {
            $listener->handle($event);
        } elseif (is_callable($listener)) {
            $listener($event);
        }
    }

    /**
     * Register a listener (internal helper).
     *
     * @param string $eventName Event name
     * @param callable|string|ListenerInterface $listener Listener
     * @param int $priority Priority
     * @return void
     */
    private function registerListener(string $eventName, callable|string|ListenerInterface $listener, int $priority = 0): void
    {
        // Check if listener should be queued
        if ($listener instanceof ShouldQueue || (is_string($listener) && $this->shouldQueueListener($listener))) {
            $this->listenQueue($eventName, $listener, $priority);
            return;
        }

        $this->listen($eventName, $listener, $priority);
    }

    /**
     * Check if listener class should be queued.
     *
     * @param string $listenerClass Listener class name
     * @return bool
     */
    private function shouldQueueListener(string $listenerClass): bool
    {
        if (!class_exists($listenerClass)) {
            return false;
        }

        $reflection = new \ReflectionClass($listenerClass);
        return $reflection->implementsInterface(ShouldQueue::class);
    }

    /**
     * Get all registered event names.
     *
     * @return array<string>
     */
    public function getEventNames(): array
    {
        return array_unique(array_merge(
            array_keys($this->listeners),
            array_keys($this->wildcards)
        ));
    }

    /**
     * Count listeners for an event.
     *
     * @param string $eventName Event name
     * @return int
     */
    public function countListeners(string $eventName): int
    {
        return count($this->getAllListeners($eventName));
    }

    /**
     * Clear all listeners and cache.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->listeners = [];
        $this->wildcards = [];
        $this->sortedListeners = null;
        $this->wildcardCache = [];
    }
}
