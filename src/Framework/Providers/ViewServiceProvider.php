<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\View\ViewFactory;
use Toporia\Framework\View\View;
use Toporia\Framework\View\Contracts\ViewFactoryInterface;

/**
 * View Service Provider
 *
 * Registers view and component services into the container.
 * Provides templating and view component functionality.
 *
 * Services Registered:
 * - 'view' => ViewFactory instance
 * - ViewFactoryInterface => ViewFactory instance
 *
 * Configuration (config/view.php):
 * ```php
 * 'paths' => [resource_path('views')],
 * 'compiled' => storage_path('framework/views'),
 * 'components' => [
 *     'alert' => \App\View\Components\Alert::class,
 * ],
 * 'namespaces' => [
 *     'mail' => \App\View\Components\Mail::class,
 * ],
 * ```
 *
 * Usage:
 * ```php
 * $view = app('view')->make('users.index', ['users' => $users]);
 * echo $view->render();
 *
 * // With facade/helper
 * return view('users.index', ['users' => $users]);
 * ```
 *
 * @package Toporia\Framework\Providers
 */
final class ViewServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register(ContainerInterface $container): void
    {
        // Register ViewFactory as singleton
        $container->singleton('view', function ($c) {
            $config = $this->getConfig($c);

            $factory = new ViewFactory($config['paths'] ?? []);

            // Register components
            foreach ($config['components'] ?? [] as $alias => $class) {
                $factory->component($class, $alias);
            }

            // Register component namespaces
            foreach ($config['namespaces'] ?? [] as $prefix => $namespace) {
                $factory->componentNamespace($prefix, $namespace);
            }

            return $factory;
        });

        // Register ViewFactoryInterface binding
        $container->bind(
            ViewFactoryInterface::class,
            fn($c) => $c->get('view')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function boot(ContainerInterface $container): void
    {
        // Share common data with all views
        /** @var ViewFactory $viewFactory */
        $viewFactory = $container->get('view');

        // Share app config if available
        if ($container->has('config')) {
            $viewFactory->share('config', $container->get('config'));
        }

        // Share auth user if available
        if ($container->has('auth')) {
            $viewFactory->composer('*', function (View $view) use ($container) {
                $auth = $container->get('auth');
                $view->with('currentUser', $auth->user());
            });
        }
    }

    /**
     * Get view configuration.
     *
     * @param ContainerInterface $container
     * @return array<string, mixed>
     */
    private function getConfig(ContainerInterface $container): array
    {
        if (!$container->has('config')) {
            // Default paths
            $basePath = dirname(__DIR__, 4);

            return [
                'paths' => [
                    $basePath . '/resources/views',
                ],
                'components' => [],
                'namespaces' => [],
            ];
        }

        $config = $container->get('config');
        $basePath = $config->get('app.base_path', dirname(__DIR__, 4));

        return [
            'paths' => $config->get('view.paths', [
                $basePath . '/resources/views',
            ]),
            'components' => $config->get('view.components', []),
            'namespaces' => $config->get('view.namespaces', []),
        ];
    }
}
