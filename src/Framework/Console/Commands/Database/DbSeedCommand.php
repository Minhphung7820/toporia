<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Database;

use Toporia\Framework\Console\Command;
use Toporia\Framework\Database\{DatabaseManager, SeederManager};
use Toporia\Framework\Database\Contracts\SeederInterface;

final class DbSeedCommand extends Command
{
    protected string $signature = 'db:seed {--class=DatabaseSeeder : The class name of the root seeder} {--force : Force the operation to run in production} {--all : Run all registered seeders}';

    protected string $description = 'Seed the database with records';

    public function handle(): int
    {
        if (!$this->confirmToProceed()) {
            return 1;
        }

        $db = $this->resolveDatabaseManager();
        $manager = new SeederManager($db);

        // Set progress callback for output
        $manager->setProgressCallback(function (string $seederClass, int $current, int $total) {
            if ($current === 0 && $total === 1) {
                $this->info("Running seeder: {$seederClass}");
            }
        });

        if ($this->option('all')) {
            // Run all registered seeders
            $this->info('Running all seeders...');
            $manager->run();
        } else {
            // Run specific seeder
            $class = $this->option('class', 'DatabaseSeeder');

            if (!str_contains($class, '\\')) {
                $class = 'Database\\Seeders\\' . $class;
            }

            if (!class_exists($class)) {
                $this->error("Seeder class [{$class}] does not exist.");
                return 1;
            }

            if (!is_subclass_of($class, SeederInterface::class)) {
                $this->error("Seeder class [{$class}] must implement " . SeederInterface::class);
                return 1;
            }

            $this->info("Seeding: {$class}");
            $manager->runSeeder($class);
        }

        $this->info('Database seeding completed successfully!');

        return 0;
    }

    private function confirmToProceed(): bool
    {
        $env = env('APP_ENV', 'production');

        if ($env === 'production' && !$this->option('force')) {
            $this->warn('Application is in production!');
            return $this->confirm('Do you really wish to run this command?', false);
        }

        return true;
    }

    /**
     * Resolve database manager from container.
     *
     * @return DatabaseManager
     */
    private function resolveDatabaseManager(): DatabaseManager
    {
        // Try to resolve from container
        if (function_exists('app') && app()->has('db')) {
            return app()->get('db');
        }

        // Fallback: create from config
        $config = config('database', []);
        return new DatabaseManager($config);
    }
}
