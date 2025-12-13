<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Export;

use App\Domain\ValueObjects\Export\ExportResult;

/**
 * Export Interface
 *
 * Contract for exporting data to external formats (Excel, CSV, etc.).
 * Supports streaming and chunking for large datasets.
 *
 * Clean Architecture:
 * - Domain layer defines the contract
 * - Infrastructure layer provides implementations
 *
 * SOLID Principles:
 * - Single Responsibility: Exports data to external formats
 * - Open/Closed: Extensible via implementations
 * - Dependency Inversion: Depends on abstraction
 *
 * Performance:
 * - Streaming support for large datasets
 * - Chunking for memory efficiency
 * - Progress tracking
 *
 * @template TEntity Entity type to export
 */
interface ExportInterface
{
    /**
     * Export data to file.
     *
     * @param iterable<TEntity> $data Data to export
     * @param string $filePath Output file path
     * @param array<string, mixed> $options Export options
     * @return ExportResult Export result with statistics
     * @throws \RuntimeException If export fails
     */
    public function export(iterable $data, string $filePath, array $options = []): ExportResult;

    /**
     * Export data to file with chunking (for large datasets).
     *
     * @param iterable<TEntity> $data Data to export
     * @param string $filePath Output file path
     * @param callable|null $chunkCallback Optional callback for each chunk: function(int $chunkIndex, int $chunkSize): void
     * @param array<string, mixed> $options Export options
     * @return ExportResult Export result with statistics
     * @throws \RuntimeException If export fails
     */
    public function exportChunked(
        iterable $data,
        string $filePath,
        ?callable $chunkCallback = null,
        array $options = []
    ): ExportResult;

    /**
     * Export data to download (stream to browser).
     *
     * @param iterable<TEntity> $data Data to export
     * @param string $filename Output filename
     * @param array<string, mixed> $options Export options
     * @return void Streams file to browser
     * @throws \RuntimeException If export fails
     */
    public function exportToDownload(iterable $data, string $filename, array $options = []): void;

    /**
     * Get export progress.
     *
     * @return float Progress percentage (0-100)
     */
    public function getProgress(): float;

    /**
     * Get supported file extensions.
     *
     * @return array<string> Array of supported extensions (e.g., ['xlsx', 'xls', 'csv'])
     */
    public function getSupportedExtensions(): array;
}
