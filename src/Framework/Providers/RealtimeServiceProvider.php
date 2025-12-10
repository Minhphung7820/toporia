<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Realtime\RealtimeManager;
use Toporia\Framework\Realtime\Contracts\RealtimeManagerInterface;

/**
 * Class RealtimeServiceProvider
 *
 * Registers realtime services with multi-transport and multi-broker support.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Providers
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class RealtimeServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Register RealtimeManager
        $container->singleton(RealtimeManager::class, function ($c) {
            $config = $c->has('config')
                ? $c->get('config')->get('realtime', [])
                : $this->getDefaultConfig();

            return new RealtimeManager($config, $c);
        });

        // Register interface bindings
        $container->bind(RealtimeManagerInterface::class, fn($c) => $c->get(RealtimeManager::class));
        $container->bind('realtime', fn($c) => $c->get(RealtimeManager::class));
    }

    /**
     * Bootstrap realtime services.
     *
     * Loads channel route definitions from routes/channels.php
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function boot(ContainerInterface $container): void
    {
        $this->loadChannelRoutes($container);
    }

    /**
     * Load channel route definitions.
     *
     * @param ContainerInterface $container
     * @return void
     */
    private function loadChannelRoutes(ContainerInterface $container): void
    {
        $channelRoutesPath = $this->getBasePath() . '/routes/channels.php';

        if (file_exists($channelRoutesPath)) {
            require_once $channelRoutesPath;
        }
    }

    /**
     * Get application base path.
     *
     * @return string
     */
    private function getBasePath(): string
    {
        // Try to get base path from container
        if ($this->container !== null && $this->container->has('path.base')) {
            return $this->container->get('path.base');
        }

        // Fallback: assume standard structure
        return dirname(__DIR__, 3);
    }

    /**
     * Get default realtime configuration.
     *
     * @return array
     */
    private function getDefaultConfig(): array
    {
        return [
            'default_transport' => 'memory',
            'default_broker' => null,

            'transports' => [
                'memory' => [
                    'driver' => 'memory',
                ],
            ],

            'brokers' => [],

            'authorizers' => [],
        ];
    }
}
