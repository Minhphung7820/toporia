<?php

declare(strict_types=1);

namespace Toporia\Framework\Bus\Contracts;

/**
 * Batch Repository Interface
 *
 * Storage for batch operations.
 */
interface BatchRepositoryInterface
{
    /**
     * Store a batch.
     *
     * @param \Toporia\Framework\Bus\Batch $batch Batch instance
     * @return void
     */
    public function store(\Toporia\Framework\Bus\Batch $batch): void;

    /**
     * Retrieve a batch by ID.
     *
     * @param string $batchId Batch ID
     * @return \Toporia\Framework\Bus\Batch|null
     */
    public function find(string $batchId): ?\Toporia\Framework\Bus\Batch;

    /**
     * Update a batch.
     *
     * @param \Toporia\Framework\Bus\Batch $batch Batch instance
     * @return void
     */
    public function update(\Toporia\Framework\Bus\Batch $batch): void;

    /**
     * Delete a batch.
     *
     * @param string $batchId Batch ID
     * @return void
     */
    public function delete(string $batchId): void;

    /**
     * Increment job counts.
     *
     * @param string $batchId Batch ID
     * @param int $processed Processed count
     * @param int $failed Failed count
     * @return void
     */
    public function incrementCounts(string $batchId, int $processed, int $failed): void;
}
