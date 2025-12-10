<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Realtime\RateLimiting\RateLimiterFactory;
use Toporia\Framework\Realtime\RateLimiting\MultiLayerRateLimiter;
use Toporia\Framework\Realtime\Security\DDoSProtection;
use Toporia\Framework\Realtime\Security\IpWhitelist;
use Toporia\Framework\Realtime\Metrics\MiddlewareMetrics;
use Toporia\Framework\Realtime\Middleware\RateLimitMiddleware;
use Toporia\Framework\Realtime\Middleware\SecurityMiddleware;
use Toporia\Framework\Realtime\Middleware\IpWhitelistMiddleware;

/**
 * Realtime Service Provider
 *
 * Bootstraps the realtime system with v2 components:
 * - Multi-layer rate limiting
 * - DDoS protection
 * - Enhanced middleware pipeline
 * - Metrics collection
 *
 * Usage in bootstrap/app.php or similar:
 *
 * ```php
 * $container->register(RealtimeServiceProvider::class);
 * ```
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     2.0.0
 * @package     toporia/framework
 * @subpackage  Realtime
 * @since       2025-12-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class RealtimeServiceProvider
{
    /**
     * Register services in the container.
     *
     * @param ContainerInterface $container
     */
    public function register(ContainerInterface $container): void
    {
        // Register rate limiting components
        $this->registerRateLimiting($container);

        // Register security components
        $this->registerSecurity($container);

        // Register metrics
        $this->registerMetrics($container);

        // Register middleware
        $this->registerMiddleware($container);

        // Register RealtimeManager
        $this->registerRealtimeManager($container);
    }

    /**
     * Register rate limiting components.
     *
     * @param ContainerInterface $container
     */
    private function registerRateLimiting(ContainerInterface $container): void
    {
        // Load config
        $config = $container->has('config') ? $container->get('config') : null;
        $rateLimitConfig = $config?->get('realtime-ratelimit', []) ?? [];

        // Register Redis connection for rate limiting
        $container->singleton('realtime.redis', function () use ($rateLimitConfig) {
            if (!($rateLimitConfig['redis']['enabled'] ?? true)) {
                return null;
            }

            return RateLimiterFactory::createRedisConnection($rateLimitConfig['redis']);
        });

        // Register multi-layer rate limiter
        $container->singleton(MultiLayerRateLimiter::class, function ($c) use ($rateLimitConfig) {
            $redis = $c->get('realtime.redis');

            return RateLimiterFactory::createMultiLayer(
                $rateLimitConfig['layers'] ?? [],
                $redis
            );
        });
    }

    /**
     * Register security components.
     *
     * @param ContainerInterface $container
     */
    private function registerSecurity(ContainerInterface $container): void
    {
        // Load config
        $config = $container->has('config') ? $container->get('config') : null;
        $rateLimitConfig = $config?->get('realtime-ratelimit', []) ?? [];
        $ddosConfig = $rateLimitConfig['ddos_protection'] ?? [];
        $ipControlConfig = $rateLimitConfig['ip_control'] ?? [];

        // Register DDoS protection
        $container->singleton(DDoSProtection::class, function ($c) use ($ddosConfig) {
            $redis = $c->get('realtime.redis');

            return new DDoSProtection(
                redis: $redis,
                connectionThreshold: (int) ($ddosConfig['connection_threshold'] ?? 10),
                connectionWindow: (int) ($ddosConfig['connection_window'] ?? 60),
                blockDuration: (int) ($ddosConfig['block_duration'] ?? 3600),
                enabled: (bool) ($ddosConfig['enabled'] ?? true)
            );
        });

        // Register IP whitelist
        $container->singleton(IpWhitelist::class, function () use ($ipControlConfig) {
            $whitelist = array_filter($ipControlConfig['whitelist'] ?? []);
            $blacklist = array_filter($ipControlConfig['blacklist'] ?? []);
            $whitelistMode = (bool) ($ipControlConfig['whitelist_mode'] ?? false);

            return new IpWhitelist($whitelist, $blacklist, $whitelistMode);
        });
    }

    /**
     * Register metrics collection.
     *
     * @param ContainerInterface $container
     */
    private function registerMetrics(ContainerInterface $container): void
    {
        // Load config
        $config = $container->has('config') ? $container->get('config') : null;
        $rateLimitConfig = $config?->get('realtime-ratelimit', []) ?? [];

        if (!($rateLimitConfig['metrics']['enabled'] ?? true)) {
            return;
        }

        $container->singleton(MiddlewareMetrics::class, function () {
            return new MiddlewareMetrics();
        });
    }

    /**
     * Register middleware instances.
     *
     * @param ContainerInterface $container
     */
    private function registerMiddleware(ContainerInterface $container): void
    {
        // Register RateLimitMiddleware
        $container->bind(RateLimitMiddleware::class, function ($c) {
            return new RateLimitMiddleware(
                $c->get(MultiLayerRateLimiter::class)
            );
        });

        // Register SecurityMiddleware
        $container->bind(SecurityMiddleware::class, function ($c) {
            return new SecurityMiddleware(
                $c->get(DDoSProtection::class),
                $c->get(IpWhitelist::class)
            );
        });

        // Register IpWhitelistMiddleware
        $container->bind(IpWhitelistMiddleware::class, function ($c) {
            return new IpWhitelistMiddleware(
                $c->get(IpWhitelist::class)
            );
        });
    }

    /**
     * Register RealtimeManager.
     *
     * @param ContainerInterface $container
     */
    private function registerRealtimeManager(ContainerInterface $container): void
    {
        $container->singleton(RealtimeManager::class, function ($c) {
            $config = $c->has('config') ? $c->get('config') : null;
            $realtimeConfig = $config?->get('realtime', []) ?? [];

            return new RealtimeManager($realtimeConfig, $c);
        });

        // Alias for easier access
        $container->alias('realtime', RealtimeManager::class);
    }

    /**
     * Bootstrap services (called after all providers registered).
     *
     * @param ContainerInterface $container
     */
    public function boot(ContainerInterface $container): void
    {
        // Register middleware aliases in config
        $this->registerMiddlewareAliases();

        // Start background cleanup tasks if needed
        $this->startCleanupTasks($container);
    }

    /**
     * Register middleware aliases for easier usage.
     */
    private function registerMiddlewareAliases(): void
    {
        // These are already registered in config/realtime.php
        // But we can programmatically register them here if needed

        // Example:
        // Middleware\EnhancedChannelMiddlewarePipeline::register(
        //     'custom_middleware',
        //     CustomMiddleware::class,
        //     priority: 90
        // );
    }

    /**
     * Start background cleanup tasks.
     *
     * @param ContainerInterface $container
     */
    private function startCleanupTasks(ContainerInterface $container): void
    {
        // In CLI mode, we can start periodic cleanup
        if (PHP_SAPI === 'cli') {
            // DDoS protection cleanup (every 5 minutes)
            // This would typically be done via a scheduled task or worker

            // For now, just register a shutdown handler
            register_shutdown_function(function () use ($container) {
                if ($container->has(DDoSProtection::class)) {
                    $ddos = $container->get(DDoSProtection::class);
                    $ddos->cleanup();
                }
            });
        }
    }
}

