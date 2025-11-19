<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Database;

use Toporia\Framework\Console\Command;

final class DbWipeCommand extends Command
{
    protected string $signature = 'db:wipe {--drop-views : Drop all tables and views} {--drop-types : Drop all tables and types (Postgres only)} {--force : Force the operation to run in production}';

    protected string $description = 'Drop all tables, views, and types';

    public function handle(): int
    {
        if (!$this->confirmToProceed()) {
            return 1;
        }

        $this->info('Wiping database...');
        $this->info('This command requires database to be configured.');

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
