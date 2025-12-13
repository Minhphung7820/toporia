<?php

declare(strict_types=1);

namespace App\Infrastructure\Import;

use App\Domain\Contracts\Import\ImportInterface;
use App\Domain\ValueObjects\Import\ImportResult;

/**
 * Base Importer Implementation
 *
 * Provides common import functionality with streaming and chunking support.
 *
 * Clean Architecture:
 * - Implements Domain ImportInterface
 * - Abstract base for concrete implementations
 *
 * SOLID Principles:
 * - Single Responsibility: Base import functionality
 * - Open/Closed: Extensible via inheritance
 *
 * Performance:
 * - Streaming support
 * - Chunking for memory efficiency
 * - Progress tracking
 */
abstract class BaseImporter implements ImportInterface
{
    /**
     * @var float Current progress (0-100)
     */
    protected float $progress = 0.0;

    /**
     * @var int Total rows processed
     */
    protected int $totalRows = 0;

    /**
     * @var int Chunk size for processing
     */
    protected int $chunkSize = 1000;

    /**
     * @param int $chunkSize Chunk size for processing (default: 1000)
     */
    public function __construct(
        int $chunkSize = 1000
    ) {
        $this->chunkSize = $chunkSize;
    }

    /**
     * {@inheritdoc}
     */
    public function import(string $filePath, array $options = []): ImportResult
    {
        $startTime = microtime(true);
        $this->progress = 0.0;
        $this->totalRows = 0;

        // Validate file
        if (!$this->validate($filePath)) {
            throw new \RuntimeException("Invalid file: {$filePath}");
        }

        // Get total rows for progress tracking
        $totalRows = $this->getTotalRows($filePath);
        $successRows = 0;
        $failedRows = 0;
        $errors = [];
        $warnings = [];

        // Process file
        $rows = $this->readFile($filePath, $options);
        $chunk = [];
        $chunkIndex = 0;

        foreach ($rows as $rowIndex => $row) {
            $chunk[] = $row;
            $this->totalRows++;

            // Process chunk when full
            if (count($chunk) >= $this->chunkSize) {
                $result = $this->processChunk($chunk, $chunkIndex, $options);
                $successRows += $result['success'];
                $failedRows += $result['failed'];
                $errors = array_merge($errors, $result['errors']);
                $warnings = array_merge($warnings, $result['warnings']);
                $chunk = [];
                $chunkIndex++;

                // Update progress (avoid division by zero when totalRows is unknown)
                if ($totalRows > 0) {
                    $this->progress = ($this->totalRows / $totalRows) * 100;
                }
            }
        }

        // Process remaining rows
        if (!empty($chunk)) {
            $result = $this->processChunk($chunk, $chunkIndex, $options);
            $successRows += $result['success'];
            $failedRows += $result['failed'];
            $errors = array_merge($errors, $result['errors']);
            $warnings = array_merge($warnings, $result['warnings']);
        }

        $this->progress = 100.0;
        $executionTime = microtime(true) - $startTime;

        return new ImportResult(
            totalRows: $this->totalRows,
            successRows: $successRows,
            failedRows: $failedRows,
            errors: $errors,
            warnings: $warnings,
            executionTime: $executionTime
        );
    }

    /**
     * {@inheritdoc}
     */
    public function importChunked(string $filePath, callable $chunkCallback, array $options = []): ImportResult
    {
        $startTime = microtime(true);
        $this->progress = 0.0;
        $this->totalRows = 0;

        // Validate file
        if (!$this->validate($filePath)) {
            throw new \RuntimeException("Invalid file: {$filePath}");
        }

        // Get total rows for progress tracking
        $totalRows = $this->getTotalRows($filePath);
        $successRows = 0;
        $failedRows = 0;
        $errors = [];
        $warnings = [];

        // Process file in chunks
        $rows = $this->readFile($filePath, $options);
        $chunk = [];
        $chunkIndex = 0;

        foreach ($rows as $row) {
            $chunk[] = $row;
            $this->totalRows++;

            // Process chunk when full
            if (count($chunk) >= $this->chunkSize) {
                try {
                    $chunkCallback($chunk, $chunkIndex);
                    $successRows += count($chunk);
                } catch (\Throwable $e) {
                    $failedRows += count($chunk);
                    $errors[] = [
                        'chunk' => $chunkIndex,
                        'error' => $e->getMessage(),
                    ];
                }

                $chunk = [];
                $chunkIndex++;

                // Update progress (avoid division by zero when totalRows is unknown)
                if ($totalRows > 0) {
                    $this->progress = ($this->totalRows / $totalRows) * 100;
                }
            }
        }

        // Process remaining rows
        if (!empty($chunk)) {
            try {
                $chunkCallback($chunk, $chunkIndex);
                $successRows += count($chunk);
            } catch (\Throwable $e) {
                $failedRows += count($chunk);
                $errors[] = [
                    'chunk' => $chunkIndex,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->progress = 100.0;
        $executionTime = microtime(true) - $startTime;

        return new ImportResult(
            totalRows: $this->totalRows,
            successRows: $successRows,
            failedRows: $failedRows,
            errors: $errors,
            warnings: $warnings,
            executionTime: $executionTime
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getProgress(): float
    {
        return $this->progress;
    }

    /**
     * Read file and return row iterator.
     * Override this method in child classes.
     *
     * @param string $filePath File path
     * @param array<string, mixed> $options Options
     * @return \Generator<int, array<string, mixed>> Row iterator
     */
    abstract protected function readFile(string $filePath, array $options = []): \Generator;

    /**
     * Get total rows in file.
     * Override this method in child classes.
     *
     * @param string $filePath File path
     * @return int Total rows
     */
    abstract protected function getTotalRows(string $filePath): int;

    /**
     * Process a chunk of rows.
     * Override this method in child classes.
     *
     * @param array<array<string, mixed>> $chunk Chunk of rows
     * @param int $chunkIndex Chunk index
     * @param array<string, mixed> $options Options
     * @return array{success: int, failed: int, errors: array, warnings: array} Processing result
     */
    abstract protected function processChunk(array $chunk, int $chunkIndex, array $options = []): array;
}

