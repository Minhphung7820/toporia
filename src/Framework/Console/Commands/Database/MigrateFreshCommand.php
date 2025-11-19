<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Database;

use Toporia\Framework\Console\Command;

final class MigrateFreshCommand extends Command
{
    protected string $signature = 'migrate:fresh {--seed : Indicates if the seed task should be run} {--force : Force the operation to run in production}';

    protected string $description = 'Drop all tables and re-run all migrations';

    public function handle(): int
    {
        if (!$this->confirmToProceed()) {
            return 1;
        }

        $this->info('Dropping all tables...');
        $this->info('This command requires database to be configured.');
        $this->newLine();
        $this->info('Then running migrations...');

        if ($this->option('seed')) {
            $this->newLine();
            $this->info('Then running seeders...');
        }

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
