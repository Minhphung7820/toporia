<?php

declare(strict_types=1);

namespace Toporia\Framework\Providers;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;
use Toporia\Framework\Console\Application;

/**
 * Console Service Provider
 *
 * Binds the console application and registers CLI commands.
 * Framework commands are auto-registered here (framework layer).
 * Application commands are registered in Application Kernel (application layer).
 */
final class ConsoleServiceProvider extends ServiceProvider
{
  public function register(ContainerInterface $container): void
  {
    // Register console application
    $container->singleton(Application::class, fn($c) => new Application($c));
  }

  public function boot(ContainerInterface $container): void
  {
    $application = $container->get(Application::class);

    // Register FRAMEWORK commands here (framework layer)
    $this->registerFrameworkCommands($application);

    // Bootstrap APPLICATION kernel (application layer)
    // App must register a callback or service to bootstrap its kernel
    if ($container->has('console.kernel.bootstrap')) {
      $bootstrap = $container->get('console.kernel.bootstrap');
      if (is_callable($bootstrap)) {
        $bootstrap($application);
      }
    }
  }

  /**
   * Register framework-level commands.
   *
   * @param Application $application
   * @return void
   */
  private function registerFrameworkCommands(Application $application): void
  {
    $application->registerMany([
      // Database commands
      \Toporia\Framework\Console\Commands\MigrateCommand::class,
      \Toporia\Framework\Console\Commands\MigrateRollbackCommand::class,
      \Toporia\Framework\Console\Commands\MigrateStatusCommand::class,
      \Toporia\Framework\Console\Commands\MigrateAlterCommand::class,
      \Toporia\Framework\Console\Commands\Database\MigrateFreshCommand::class,
      \Toporia\Framework\Console\Commands\Database\MigrateRefreshCommand::class,
      \Toporia\Framework\Console\Commands\Database\DbSeedCommand::class,
      \Toporia\Framework\Console\Commands\Database\DbWipeCommand::class,
      \Toporia\Framework\Console\Commands\Database\DbShowCommand::class,
      \Toporia\Framework\Console\Commands\Database\DbTableCommand::class,

      // Route commands
      \Toporia\Framework\Console\Commands\RouteCacheCommand::class,
      \Toporia\Framework\Console\Commands\RouteClearCommand::class,
      \Toporia\Framework\Console\Commands\RouteListCommand::class,

      // Config commands
      \Toporia\Framework\Console\Commands\ConfigCacheCommand::class,
      \Toporia\Framework\Console\Commands\ConfigClearCommand::class,
      \Toporia\Framework\Console\Commands\KeyGenerateCommand::class,

      // Cache commands
      \Toporia\Framework\Console\Commands\CacheClearCommand::class,
      \Toporia\Framework\Console\Commands\Optimize\CacheTableCommand::class,

      // Queue commands
      \Toporia\Framework\Console\Commands\QueueWorkCommand::class,
      \Toporia\Framework\Console\Commands\Queue\QueueListenCommand::class,
      \Toporia\Framework\Console\Commands\Queue\QueueRestartCommand::class,
      \Toporia\Framework\Console\Commands\Queue\QueueRetryCommand::class,
      \Toporia\Framework\Console\Commands\Queue\QueueFailedCommand::class,
      \Toporia\Framework\Console\Commands\Queue\QueueFlushCommand::class,
      \Toporia\Framework\Console\Commands\Queue\QueueTableCommand::class,
      \Toporia\Framework\Console\Commands\Queue\QueueFailedTableCommand::class,
      \Toporia\Framework\Console\Commands\Queue\QueueMonitorCommand::class,

      // Schedule commands
      \Toporia\Framework\Console\Commands\ScheduleRunCommand::class,
      \Toporia\Framework\Console\Commands\ScheduleWorkCommand::class,
      \Toporia\Framework\Console\Commands\ScheduleListCommand::class,
      \Toporia\Framework\Console\Commands\ScheduleTestCommand::class,

      // Event commands
      \Toporia\Framework\Console\Commands\Event\EventListCommand::class,
      \Toporia\Framework\Console\Commands\Event\EventCacheCommand::class,
      \Toporia\Framework\Console\Commands\Event\EventClearCommand::class,
      \Toporia\Framework\Console\Commands\Event\EventGenerateCommand::class,

      // Realtime commands
      \Toporia\Framework\Console\Commands\RealtimeServeCommand::class,
      \Toporia\Framework\Console\Commands\RealtimeKafkaConsumerCommand::class,
      \Toporia\Framework\Console\Commands\RealtimeRedisConsumerCommand::class,
      \Toporia\Framework\Console\Commands\RealtimeRabbitMqConsumerCommand::class,
      \Toporia\Framework\Console\Commands\Realtime\ChannelListCommand::class,
      \Toporia\Framework\Console\Commands\Realtime\RealtimePublishCommand::class,

      // Notification commands
      \Toporia\Framework\Console\Commands\Notification\NotificationTableCommand::class,

      // Search
      \Toporia\Framework\Console\Commands\ReindexSearchCommand::class,

      // Optimization commands
      \Toporia\Framework\Console\Commands\Optimize\OptimizeCommand::class,
      \Toporia\Framework\Console\Commands\Optimize\OptimizeClearCommand::class,
      \Toporia\Framework\Console\Commands\Optimize\ViewCacheCommand::class,
      \Toporia\Framework\Console\Commands\Optimize\ViewClearCommand::class,
      \Toporia\Framework\Console\Commands\Optimize\StorageLinkCommand::class,

      // Make commands (code generation)
      \Toporia\Framework\Console\Commands\Make\MakeCommandCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeControllerCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeModelCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeMigrationCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeMiddlewareCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeEventCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeListenerCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeSubscriberCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeNotificationCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeJobCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeRequestCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakePolicyCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeProviderCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeActionCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeEntityCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeHandlerCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeRuleCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeExceptionCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeSeederCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeFactoryCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeRepositoryCommand::class,
      \Toporia\Framework\Console\Commands\Make\MakeObserverCommand::class,

      // App commands
      \Toporia\Framework\Console\Commands\App\AboutCommand::class,
      \Toporia\Framework\Console\Commands\App\EnvCommand::class,
      \Toporia\Framework\Console\Commands\App\DownCommand::class,
      \Toporia\Framework\Console\Commands\App\UpCommand::class,
      \Toporia\Framework\Console\Commands\App\InspireCommand::class,
      \Toporia\Framework\Console\Commands\App\TinkerCommand::class,
      \Toporia\Framework\Console\Commands\App\StubPublishCommand::class,

      // Development server
      \Toporia\Framework\Console\Commands\ServeCommand::class,

      // Testing
      \Toporia\Framework\Console\Commands\TestCommand::class,
    ]);
  }
}
