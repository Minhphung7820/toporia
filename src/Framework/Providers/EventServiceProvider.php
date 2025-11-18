<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Events\Dispatcher;
use Toporia\Framework\Events\Contracts\EventDispatcherInterface;
use Toporia\Framework\Foundation\ServiceProvider;

/**
 * Event Service Provider
 *
 * Registers the event dispatcher with container and queue support.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Event Dispatcher - Singleton with container and queue support
        $container->singleton(EventDispatcherInterface::class, function ($c) {
            $queue = $c->has('queue') ? $c->get('queue') : null;
            return new Dispatcher($c, $queue);
        });

        $container->singleton(Dispatcher::class, function ($c) {
            $queue = $c->has('queue') ? $c->get('queue') : null;
            return new Dispatcher($c, $queue);
        });

        $container->singleton('events', function ($c) {
            $queue = $c->has('queue') ? $c->get('queue') : null;
            return new Dispatcher($c, $queue);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        // Auto-discover and register event listeners
        $this->discoverListeners($container);
    }

    /**
     * Discover and register event listeners.
     *
     * @param ContainerInterface $container
     * @return void
     */
    private function discoverListeners(ContainerInterface $container): void
    {
        // This can be extended to auto-discover listeners from directories
        // For now, listeners should be registered in EventServiceProvider subclasses
    }
}
