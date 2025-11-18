<?php

declare(strict_types=1);

namespace Toporia\Framework\Bus;

use Toporia\Framework\Bus\Contracts\BatchRepositoryInterface;
use Toporia\Framework\Database\Connection;

/**
 * Database Batch Repository
 *
 * Stores batches in database table.
 *
 * Performance:
 * - O(1) lookups via indexed batch_id
 * - Atomic updates for counters
 * - Lazy loading (only load when needed)
 */
final class DatabaseBatchRepository implements BatchRepositoryInterface
{
    private string $table = 'job_batches';

    public function __construct(
        private Connection $connection
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function store(Batch $batch): void
    {
        $data = $batch->toArray();

        $this->connection->execute(
            "INSERT INTO {$this->table} (id, name, total_jobs, processed_jobs, failed_jobs, options, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['id'],
                $data['name'],
                $data['total_jobs'],
                $data['processed_jobs'],
                $data['failed_jobs'],
                json_encode($data['options']),
                $data['created_at'],
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function find(string $batchId): ?Batch
    {
        $row = $this->connection->fetchOne(
            "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1",
            [$batchId]
        );

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * {@inheritdoc}
     */
    public function update(Batch $batch): void
    {
        $data = $batch->toArray();

        $this->connection->execute(
            "UPDATE {$this->table}
             SET processed_jobs = ?, failed_jobs = ?, finished_at = ?, cancelled_at = ?
             WHERE id = ?",
            [
                $data['processed_jobs'],
                $data['failed_jobs'],
                $data['finished_at'],
                $data['cancelled_at'],
                $data['id'],
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $batchId): void
    {
        $this->connection->execute(
            "DELETE FROM {$this->table} WHERE id = ?",
            [$batchId]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function incrementCounts(string $batchId, int $processed, int $failed): void
    {
        // Atomic increment
        $this->connection->execute(
            "UPDATE {$this->table}
             SET processed_jobs = processed_jobs + ?,
                 failed_jobs = failed_jobs + ?
             WHERE id = ?",
            [$processed, $failed, $batchId]
        );

        // Check if finished (all jobs processed)
        $batch = $this->find($batchId);
        if ($batch && $batch->processedJobs() >= $batch->totalJobs() && !$batch->finished()) {
            $this->connection->execute(
                "UPDATE {$this->table} SET finished_at = ? WHERE id = ?",
                [time(), $batchId]
            );
        }
    }

    /**
     * Hydrate batch from database row.
     */
    private function hydrate(array $row): Batch
    {
        return new Batch(
            id: $row['id'],
            name: $row['name'],
            totalJobs: (int) $row['total_jobs'],
            processedJobs: (int) $row['processed_jobs'],
            failedJobs: (int) $row['failed_jobs'],
            options: json_decode($row['options'] ?? '{}', true),
            createdAt: $row['created_at'] ? (int) $row['created_at'] : null,
            finishedAt: $row['finished_at'] ? (int) $row['finished_at'] : null,
            cancelledAt: $row['cancelled_at'] ? (int) $row['cancelled_at'] : null
        );
    }
}
