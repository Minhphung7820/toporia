<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Queue;

use Toporia\Framework\Console\Command;

final class QueueFailedCommand extends Command
{
    protected string $signature = 'queue:failed';

    protected string $description = 'List all of the failed queue jobs';

    public function handle(): int
    {
        $this->info('Listing failed jobs...');
        $this->info('This command requires database to be configured with failed_jobs table.');
        $this->info("Run 'php console queue:failed-table' to create the migration.");

        return 0;
    }
}
