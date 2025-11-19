<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Queue;

use Toporia\Framework\Console\Command;

final class QueueRetryCommand extends Command
{
    protected string $signature = 'queue:retry {id?* : The IDs of the failed jobs or "all" to retry all jobs}';

    protected string $description = 'Retry a failed queue job';

    public function handle(): int
    {
        $ids = $this->argument('id');

        if (empty($ids)) {
            $this->error('Please specify job IDs to retry, or use "all" to retry all failed jobs.');
            return 1;
        }

        $this->info('Retrying failed jobs...');
        $this->info('This command requires database to be configured with failed_jobs table.');

        return 0;
    }
}
