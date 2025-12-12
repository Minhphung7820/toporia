<?php

declare(strict_types=1);

namespace App\Presentation\Console\Commands;

use App\Infrastructure\Persistence\Models\PostModel;
use Toporia\Framework\Console\Command;

/**
 * Export posts to CSV file.
 *
 * Usage:
 *   php console export:posts
 *   php console export:posts --output=my_posts.csv
 *   php console export:posts --chunk=10000
 */
final class ExportPostsCommand extends Command
{
    protected string $signature = 'export:posts {--output=posts_export.csv} {--chunk=5000}';
    protected string $description = 'Export posts to CSV file';

    public function handle(): int
    {
        $outputFile = $this->option('output') ?? 'posts_export.csv';
        $chunkSize = (int) ($this->option('chunk') ?? 5000);

        $this->info("Exporting posts to: {$outputFile}");
        $this->info("Chunk size: {$chunkSize}");

        $startTime = microtime(true);

        // Open file for writing
        $handle = fopen($outputFile, 'w');
        if ($handle === false) {
            $this->error("Cannot create file: {$outputFile}");
            return 1;
        }

        // Write CSV header
        fputcsv($handle, ['id', 'title', 'slug', 'content', 'views', 'is_published', 'created_at', 'updated_at']);

        // Get total count for progress
        $totalCount = PostModel::count();
        $this->info("Total posts: " . number_format($totalCount));

        if ($totalCount === 0) {
            $this->warn('No posts to export.');
            fclose($handle);
            return 0;
        }

        $exported = 0;
        $chunkIndex = 0;

        // Stream export using chunk to avoid memory issues
        PostModel::query()
            ->orderBy('id')
            ->chunk($chunkSize, function ($posts) use ($handle, &$exported, &$chunkIndex, $totalCount) {
                foreach ($posts as $post) {
                    fputcsv($handle, [
                        $post->id,
                        $post->title,
                        $post->slug,
                        $post->content,
                        $post->views,
                        $post->is_published ? 1 : 0,
                        $post->created_at,
                        $post->updated_at,
                    ]);
                    $exported++;
                }

                $percent = round(($exported / $totalCount) * 100, 1);
                $this->output->write("\r[{$percent}%] Exported " . number_format($exported) . " / " . number_format($totalCount) . " records...");
                $chunkIndex++;
            });

        fclose($handle);

        $duration = round(microtime(true) - $startTime, 2);
        $fileSize = round(filesize($outputFile) / 1024 / 1024, 2);
        $memory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        $this->newLine();
        $this->info('========================================');
        $this->info('Export Complete!');
        $this->info('========================================');
        $this->info("Total exported: " . number_format($exported));
        $this->info("File size: {$fileSize} MB");
        $this->info("Duration: {$duration}s");
        $this->info("Peak memory: {$memory}MB");
        $this->info("Output: {$outputFile}");
        $this->info('========================================');

        return 0;
    }
}
