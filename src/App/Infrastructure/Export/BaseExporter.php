<?php

declare(strict_types=1);

namespace App\Infrastructure\Export;

use App\Domain\Contracts\Export\ExportInterface;
use App\Domain\ValueObjects\Export\ExportResult;

/**
 * Base Exporter Implementation
 *
 * Provides common export functionality with streaming and chunking support.
 *
 * Clean Architecture:
 * - Implements Domain ExportInterface
 * - Abstract base for concrete implementations
 *
 * SOLID Principles:
 * - Single Responsibility: Base export functionality
 * - Open/Closed: Extensible via inheritance
 *
 * Performance:
 * - Streaming support for large datasets
 * - Chunking for memory efficiency
 * - Progress tracking
 */
abstract class BaseExporter implements ExportInterface
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
    public function export(iterable $data, string $filePath, array $options = []): ExportResult
    {
        $startTime = microtime(true);
        $this->progress = 0.0;
        $this->totalRows = 0;

        // Ensure directory exists
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Get total count if possible
        $totalCount = $this->getTotalCount($data);

        // Initialize file writer
        $writer = $this->initializeWriter($filePath, $options);

        // Write headers if needed
        $headers = $this->getHeaders($options);
        if (!empty($headers)) {
            $this->writeHeaders($writer, $headers);
        }

        // Write data in chunks
        $chunk = [];
        $chunkIndex = 0;

        foreach ($data as $item) {
            $chunk[] = $item;
            $this->totalRows++;

            // Write chunk when full
            if (count($chunk) >= $this->chunkSize) {
                $this->writeChunk($writer, $chunk, $chunkIndex, $options);
                $chunk = [];
                $chunkIndex++;

                // Update progress
                if ($totalCount > 0) {
                    $this->progress = ($this->totalRows / $totalCount) * 100;
                }
            }
        }

        // Write remaining rows
        if (!empty($chunk)) {
            $this->writeChunk($writer, $chunk, $chunkIndex, $options);
        }

        // Finalize writer
        $this->finalizeWriter($writer);

        $this->progress = 100.0;
        $executionTime = microtime(true) - $startTime;

        return ExportResult::success($filePath, $this->totalRows, $executionTime);
    }

    /**
     * {@inheritdoc}
     */
    public function exportChunked(
        iterable $data,
        string $filePath,
        ?callable $chunkCallback = null,
        array $options = []
    ): ExportResult {
        $startTime = microtime(true);
        $this->progress = 0.0;
        $this->totalRows = 0;

        // Ensure directory exists
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Get total count if possible
        $totalCount = $this->getTotalCount($data);

        // Initialize file writer
        $writer = $this->initializeWriter($filePath, $options);

        // Write headers if needed
        $headers = $this->getHeaders($options);
        if (!empty($headers)) {
            $this->writeHeaders($writer, $headers);
        }

        // Write data in chunks
        $chunk = [];
        $chunkIndex = 0;

        foreach ($data as $item) {
            $chunk[] = $item;
            $this->totalRows++;

            // Write chunk when full
            if (count($chunk) >= $this->chunkSize) {
                $this->writeChunk($writer, $chunk, $chunkIndex, $options);

                // Call chunk callback if provided
                if ($chunkCallback !== null) {
                    $chunkCallback($chunkIndex, count($chunk));
                }

                $chunk = [];
                $chunkIndex++;

                // Update progress
                if ($totalCount > 0) {
                    $this->progress = ($this->totalRows / $totalCount) * 100;
                }
            }
        }

        // Write remaining rows
        if (!empty($chunk)) {
            $this->writeChunk($writer, $chunk, $chunkIndex, $options);
            if ($chunkCallback !== null) {
                $chunkCallback($chunkIndex, count($chunk));
            }
        }

        // Finalize writer
        $this->finalizeWriter($writer);

        $this->progress = 100.0;
        $executionTime = microtime(true) - $startTime;

        return ExportResult::success($filePath, $this->totalRows, $executionTime);
    }

    /**
     * {@inheritdoc}
     */
    public function exportToDownload(iterable $data, string $filename, array $options = []): void
    {
        // Create temporary file
        $tempFile = sys_get_temp_dir() . '/' . uniqid('export_', true) . '.xlsx';

        // Export to temp file
        $this->export($data, $tempFile, $options);

        // SECURITY: Sanitize filename to prevent header injection
        // Remove any characters that could be used for HTTP header injection
        $safeFilename = $this->sanitizeFilename($filename);

        // Stream to browser
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
        header('Content-Length: ' . filesize($tempFile));

        readfile($tempFile);
        unlink($tempFile);
        exit;
    }

    /**
     * Sanitize filename to prevent header injection attacks.
     *
     * SECURITY: This prevents HTTP header injection via filename parameter.
     * Characters like \r\n can be used to inject additional headers.
     *
     * @param string $filename Raw filename
     * @return string Sanitized filename
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Remove any characters that could be used for header injection
        // Including: newlines, carriage returns, null bytes, and control characters
        $sanitized = preg_replace('/[\r\n\x00-\x1f\x7f]/', '', $filename);

        // Remove directory traversal attempts
        $sanitized = basename($sanitized);

        // Remove or replace characters problematic in Content-Disposition
        // Quotes and backslashes need special handling
        $sanitized = str_replace(['"', '\\'], ['', ''], $sanitized);

        // Ensure filename is not empty
        if (empty($sanitized)) {
            $sanitized = 'export.xlsx';
        }

        // Limit filename length to prevent buffer overflow
        if (strlen($sanitized) > 255) {
            $ext = pathinfo($sanitized, PATHINFO_EXTENSION);
            $name = pathinfo($sanitized, PATHINFO_FILENAME);
            $sanitized = substr($name, 0, 250 - strlen($ext)) . '.' . $ext;
        }

        return $sanitized;
    }

    /**
     * {@inheritdoc}
     */
    public function getProgress(): float
    {
        return $this->progress;
    }

    /**
     * Initialize file writer.
     * Override this method in child classes.
     *
     * @param string $filePath File path
     * @param array<string, mixed> $options Options
     * @return mixed Writer instance
     */
    abstract protected function initializeWriter(string $filePath, array $options = []): mixed;

    /**
     * Write headers to file.
     * Override this method in child classes.
     *
     * @param mixed $writer Writer instance
     * @param array<string> $headers Headers
     * @return void
     */
    abstract protected function writeHeaders(mixed $writer, array $headers): void;

    /**
     * Write chunk of data to file.
     * Override this method in child classes.
     *
     * @param mixed $writer Writer instance
     * @param array<mixed> $chunk Chunk of data
     * @param int $chunkIndex Chunk index
     * @param array<string, mixed> $options Options
     * @return void
     */
    abstract protected function writeChunk(mixed $writer, array $chunk, int $chunkIndex, array $options = []): void;

    /**
     * Finalize writer (close file, etc.).
     * Override this method in child classes.
     *
     * @param mixed $writer Writer instance
     * @return void
     */
    abstract protected function finalizeWriter(mixed $writer): void;

    /**
     * Get headers for export.
     * Override this method in child classes.
     *
     * @param array<string, mixed> $options Options
     * @return array<string> Headers
     */
    protected function getHeaders(array $options = []): array
    {
        return $options['headers'] ?? [];
    }

    /**
     * Get total count of data.
     *
     * @param iterable $data Data
     * @return int Total count (0 if unknown)
     */
    protected function getTotalCount(iterable $data): int
    {
        if (is_array($data)) {
            return count($data);
        }

        if ($data instanceof \Countable) {
            return count($data);
        }

        // Unknown count
        return 0;
    }
}

