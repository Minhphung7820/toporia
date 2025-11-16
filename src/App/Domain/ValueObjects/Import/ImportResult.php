<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects\Import;

/**
 * Import Result
 *
 * Represents the result of an import operation.
 * Contains statistics and error information.
 *
 * Clean Architecture:
 * - Domain layer value object
 * - Immutable
 *
 * SOLID Principles:
 * - Single Responsibility: Represents import result
 * - Immutability: Result is readonly
 */
final class ImportResult
{
    /**
     * @param int $totalRows Total rows processed
     * @param int $successRows Number of successfully imported rows
     * @param int $failedRows Number of failed rows
     * @param array<string, mixed> $errors Array of errors
     * @param array<string, mixed> $warnings Array of warnings
     * @param float $executionTime Execution time in seconds
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        public readonly int $totalRows,
        public readonly int $successRows,
        public readonly int $failedRows,
        public readonly array $errors = [],
        public readonly array $warnings = [],
        public readonly float $executionTime = 0.0,
        public readonly array $metadata = []
    ) {}

    /**
     * Check if import was successful.
     *
     * @return bool True if all rows imported successfully
     */
    public function isSuccess(): bool
    {
        return $this->failedRows === 0 && $this->totalRows > 0;
    }

    /**
     * Get success rate.
     *
     * @return float Success rate (0-100)
     */
    public function getSuccessRate(): float
    {
        if ($this->totalRows === 0) {
            return 0.0;
        }

        return ($this->successRows / $this->totalRows) * 100;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed> Result as array
     */
    public function toArray(): array
    {
        return [
            'total_rows' => $this->totalRows,
            'success_rows' => $this->successRows,
            'failed_rows' => $this->failedRows,
            'success_rate' => $this->getSuccessRate(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'execution_time' => $this->executionTime,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create success result.
     *
     * @param int $totalRows Total rows
     * @param float $executionTime Execution time
     * @param array<string, mixed> $metadata Metadata
     * @return self Success result
     */
    public static function success(int $totalRows, float $executionTime = 0.0, array $metadata = []): self
    {
        return new self(
            totalRows: $totalRows,
            successRows: $totalRows,
            failedRows: 0,
            errors: [],
            warnings: [],
            executionTime: $executionTime,
            metadata: $metadata
        );
    }

    /**
     * Create failure result.
     *
     * @param int $totalRows Total rows
     * @param int $failedRows Failed rows
     * @param array<string, mixed> $errors Errors
     * @param float $executionTime Execution time
     * @return self Failure result
     */
    public static function failure(int $totalRows, int $failedRows, array $errors, float $executionTime = 0.0): self
    {
        return new self(
            totalRows: $totalRows,
            successRows: $totalRows - $failedRows,
            failedRows: $failedRows,
            errors: $errors,
            warnings: [],
            executionTime: $executionTime
        );
    }
}
