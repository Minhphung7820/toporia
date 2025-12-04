<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Session\SessionManager;
use Toporia\Framework\Session\Store;

/**
 * Class SessionServiceProvider
 *
 * Registers session management services.
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
final class SessionServiceProvider extends ServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        // Session Manager
        $container->singleton(SessionManager::class, function ($c) {
            $config = $c->has('config')
                ? $c->get('config')->get('session', [])
                : [];

            // Unwrap ConnectionProxy to get actual ConnectionInterface
            $db = $c->has('db') ? $c->get('db') : null;
            $connection = $db && method_exists($db, 'getConnection')
                ? $db->getConnection()
                : $db;

            $redis = $c->has('redis') ? $c->get('redis') : null;
            $cookieJar = $c->has('cookie') ? $c->get('cookie') : null;

            return new SessionManager($config, $connection, $redis, $cookieJar);
        });

        $container->bind('session.manager', fn($c) => $c->get(SessionManager::class));

        // Default session store
        $container->singleton(Store::class, function ($c) {
            $manager = $c->get(SessionManager::class);
            return $manager->store();
        });

        $container->bind('session', fn($c) => $c->get(Store::class));
    }

    public function boot(ContainerInterface $container): void
    {
        // Start session automatically
        if ($container->has('session')) {
            $session = $container->get('session');
            $session->start();
        }
    }
}

