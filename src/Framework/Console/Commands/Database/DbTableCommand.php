<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Database;

use Toporia\Framework\Console\Command;

final class DbTableCommand extends Command
{
    protected string $signature = 'db:table {table : The name of the table}';

    protected string $description = 'Display information about the given database table';

    public function handle(): int
    {
        $table = $this->argument('table');

        $this->info("Table: {$table}");
        $this->line();
        $this->info('This command requires database to be configured.');

        return 0;
    }
}
