<?php

declare(strict_types=1);

namespace Toporia\Framework\Webhook\Providers;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Webhook\{WebhookDispatcher, WebhookReceiver, WebhookManager, SignatureGenerator};
use Toporia\Framework\Webhook\Contracts\{WebhookDispatcherInterface, WebhookReceiverInterface, SignatureGeneratorInterface};

/**
 * Webhook Service Provider
 *
 * Registers webhook services.
 */
final class WebhookServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Register signature generator
        $container->singleton(SignatureGeneratorInterface::class, function ($c) {
            $config = $c->has('config')
                ? $c->get('config')->get('webhook', [])
                : [];

            $algorithm = $config['signature_algorithm'] ?? 'sha256';

            return new SignatureGenerator($algorithm);
        });

        // Register webhook dispatcher
        $container->singleton(WebhookDispatcherInterface::class, function ($c) {
            return new WebhookDispatcher(
                $c->get(\Toporia\Framework\Http\Contracts\HttpClientInterface::class),
                $c->get(SignatureGeneratorInterface::class),
                $c->has(\Toporia\Framework\Queue\Contracts\QueueManagerInterface::class)
                    ? $c->get(\Toporia\Framework\Queue\Contracts\QueueManagerInterface::class)
                    : null,
                $c->has(\Toporia\Framework\Log\Contracts\LoggerInterface::class)
                    ? $c->get(\Toporia\Framework\Log\Contracts\LoggerInterface::class)
                    : null
            );
        });

        // Register webhook receiver
        $container->singleton(WebhookReceiverInterface::class, function ($c) {
            return new WebhookReceiver(
                $c->get(SignatureGeneratorInterface::class),
                $c->has(\Toporia\Framework\Log\Contracts\LoggerInterface::class)
                    ? $c->get(\Toporia\Framework\Log\Contracts\LoggerInterface::class)
                    : null
            );
        });

        // Register webhook manager
        $container->singleton(WebhookManager::class, function ($c) {
            return new WebhookManager(
                $c->get(WebhookDispatcherInterface::class)
            );
        });

        // Bind aliases
        $container->bind('webhook', fn($c) => $c->get(WebhookManager::class));
        $container->bind('webhook.dispatcher', fn($c) => $c->get(WebhookDispatcherInterface::class));
        $container->bind('webhook.receiver', fn($c) => $c->get(WebhookReceiverInterface::class));
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        // Webhook services are ready
    }
}

