<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects\Export;

/**
 * Export Result
 *
 * Represents the result of an export operation.
 * Contains statistics and file information.
 *
 * Clean Architecture:
 * - Domain layer value object
 * - Immutable
 *
 * SOLID Principles:
 * - Single Responsibility: Represents export result
 * - Immutability: Result is readonly
 */
final class ExportResult
{
    /**
     * @param string $filePath Path to exported file
     * @param int $totalRows Total rows exported
     * @param int $fileSize File size in bytes
     * @param float $executionTime Execution time in seconds
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        public readonly string $filePath,
        public readonly int $totalRows,
        public readonly int $fileSize,
        public readonly float $executionTime = 0.0,
        public readonly array $metadata = []
    ) {}

    /**
     * Check if export was successful.
     *
     * @return bool True if export succeeded
     */
    public function isSuccess(): bool
    {
        return file_exists($this->filePath) && $this->totalRows > 0;
    }

    /**
     * Get file size in human-readable format.
     *
     * @return string Human-readable file size
     */
    public function getFileSizeHuman(): string
    {
        $bytes = $this->fileSize;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed> Result as array
     */
    public function toArray(): array
    {
        return [
            'file_path' => $this->filePath,
            'file_name' => basename($this->filePath),
            'total_rows' => $this->totalRows,
            'file_size' => $this->fileSize,
            'file_size_human' => $this->getFileSizeHuman(),
            'execution_time' => $this->executionTime,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create success result.
     *
     * @param string $filePath File path
     * @param int $totalRows Total rows
     * @param float $executionTime Execution time
     * @param array<string, mixed> $metadata Metadata
     * @return self Success result
     */
    public static function success(
        string $filePath,
        int $totalRows,
        float $executionTime = 0.0,
        array $metadata = []
    ): self {
        $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
        return new self(
            filePath: $filePath,
            totalRows: $totalRows,
            fileSize: $fileSize,
            executionTime: $executionTime,
            metadata: $metadata
        );
    }
}
