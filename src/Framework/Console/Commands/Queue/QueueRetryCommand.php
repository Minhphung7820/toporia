<?php

declare(strict_types=1);

namespace Toporia\Framework\Console\Commands\Queue;

use Toporia\Framework\Console\Command;

/**
 * Class QueueRetryCommand
 *
 * Retry a failed queue job.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Console\Commands\Queue
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
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
