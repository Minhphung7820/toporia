<?php

declare(strict_types=1);

namespace App\Presentation\Console\Commands;

use App\Infrastructure\Jobs\ImportPostsJob;
use App\Infrastructure\Jobs\ExportPostsJob;
use App\Infrastructure\Persistence\Models\ImportExportJobModel;
use Toporia\Framework\Console\Command;

/**
 * Re-dispatch pending import/export jobs.
 */
final class RedispatchImportJobsCommand extends Command
{
    protected string $signature = 'jobs:redispatch';
    protected string $description = 'Re-dispatch pending import/export jobs to queue';

    public function handle(): int
    {
        $jobs = ImportExportJobModel::where('status', 'pending')->get();

        if ($jobs->isEmpty()) {
            $this->info('No pending jobs found.');
            return 0;
        }

        $this->info("Found {$jobs->count()} pending job(s)");

        foreach ($jobs as $job) {
            $this->line("Dispatching job: {$job->id} ({$job->type})");

            if ($job->type === 'import') {
                $workers = $job->options['workers'] ?? 0;
                dispatch(new ImportPostsJob(
                    jobId: $job->id,
                    userId: $job->user_id,
                    filePath: $job->file_path,
                    workers: $workers
                ))->onQueue($job->queue_name ?? 'import-export-high');
            } else {
                dispatch(new ExportPostsJob(
                    jobId: $job->id,
                    userId: $job->user_id,
                    filters: $job->options ?? []
                ))->onQueue($job->queue_name ?? 'import-export-high');
            }

            $this->info("  -> Dispatched to queue: {$job->queue_name}");
        }

        $this->info('Done!');
        return 0;
    }
}
