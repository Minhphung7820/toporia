<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Import;

use App\Domain\ValueObjects\Import\ImportResult;

/**
 * Import Interface
 *
 * Contract for importing data from external sources (Excel, CSV, etc.).
 * Supports streaming and chunking for large files.
 *
 * Clean Architecture:
 * - Domain layer defines the contract
 * - Infrastructure layer provides implementations
 *
 * SOLID Principles:
 * - Single Responsibility: Imports data from external sources
 * - Open/Closed: Extensible via implementations
 * - Dependency Inversion: Depends on abstraction
 *
 * Performance:
 * - Streaming support for large files
 * - Chunking for memory efficiency
 * - Progress tracking
 *
 * @template TEntity Entity type to import
 */
interface ImportInterface
{
    /**
     * Import data from file.
     *
     * @param string $filePath Path to file
     * @param array<string, mixed> $options Import options
     * @return ImportResult Import result with statistics
     * @throws \RuntimeException If import fails
     */
    public function import(string $filePath, array $options = []): ImportResult;

    /**
     * Import data from file with chunking (for large files).
     *
     * @param string $filePath Path to file
     * @param callable $chunkCallback Callback for each chunk: function(array $rows, int $chunkIndex): void
     * @param array<string, mixed> $options Import options
     * @return ImportResult Import result with statistics
     * @throws \RuntimeException If import fails
     */
    public function importChunked(string $filePath, callable $chunkCallback, array $options = []): ImportResult;

    /**
     * Validate file before import.
     *
     * @param string $filePath Path to file
     * @return bool True if file is valid
     * @throws \RuntimeException If validation fails
     */
    public function validate(string $filePath): bool;

    /**
     * Get import progress.
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

