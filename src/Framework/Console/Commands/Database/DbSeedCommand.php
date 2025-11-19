<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Database;

use Toporia\Framework\Console\Command;

final class DbSeedCommand extends Command
{
    protected string $signature = 'db:seed {--class=DatabaseSeeder : The class name of the root seeder} {--force : Force the operation to run in production}';

    protected string $description = 'Seed the database with records';

    public function handle(): int
    {
        if (!$this->confirmToProceed()) {
            return 1;
        }

        $class = $this->option('class', 'DatabaseSeeder');

        if (!str_contains($class, '\\')) {
            $class = 'Database\\Seeders\\' . $class;
        }

        $this->info("Seeding: {$class}");
        $this->info('This command requires the seeder class to exist.');

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
}
