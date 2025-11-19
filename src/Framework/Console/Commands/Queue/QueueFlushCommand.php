<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Queue;

use Toporia\Framework\Console\Command;

final class QueueFlushCommand extends Command
{
    protected string $signature = 'queue:flush {--hours= : The number of hours to retain failed job data}';

    protected string $description = 'Flush all of the failed queue jobs';

    public function handle(): int
    {
        $this->info('Flushing failed jobs...');
        $this->info('This command requires database to be configured with failed_jobs table.');

        return 0;
    }
}
