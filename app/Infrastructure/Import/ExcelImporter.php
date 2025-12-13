<?php

declare(strict_types=1);

namespace App\Infrastructure\Import;

use App\Domain\ValueObjects\Import\ImportResult;

/**
 * Excel Importer Implementation
 *
 * Imports data from Excel files (XLSX, XLS, CSV, ODS) with streaming support.
 * Optimized for large files (millions of rows).
 *
 * Clean Architecture:
 * - Implements Domain ImportInterface via BaseImporter
 * - Uses OpenSpout or PhpSpreadsheet for Excel reading
 *
 * SOLID Principles:
 * - Single Responsibility: Imports Excel files only
 * - Open/Closed: Extensible via BaseImporter
 *
 * Performance:
 * - Streaming: Reads file row by row (O(1) memory)
 * - Chunking: Processes data in chunks
 * - Supports millions of rows
 * - Memory-efficient
 */
final class ExcelImporter extends BaseImporter
{
    /**
     * @var callable|null Row mapper function
     */
    private $rowMapper = null;

    /**
     * @var bool Whether first row is header
     */
    private bool $hasHeader = true;

    /**
     * @var array<string> Header row (if hasHeader is true)
     */
    private array $headers = [];

    /**
     * Set row mapper function.
     *
     * @param callable $mapper Function to map raw row to entity data
     * @return self
     */
    public function setRowMapper(callable $mapper): self
    {
        $this->rowMapper = $mapper;
        return $this;
    }

    /**
     * Set whether file has header row.
     *
     * @param bool $hasHeader True if first row is header
     * @return self
     */
    public function setHasHeader(bool $hasHeader): self
    {
        $this->hasHeader = $hasHeader;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedExtensions(): array
    {
        return ['xlsx', 'xls', 'csv', 'ods'];
    }

    /**
     * {@inheritdoc}
     */
    public function validate(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, $this->getSupportedExtensions(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function readFile(string $filePath, array $options = []): \Generator
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Try OpenSpout first (best for large files)
        if ($this->hasOpenSpout()) {
            return $this->readWithOpenSpout($filePath, $extension, $options);
        }

        // Fallback to PhpSpreadsheet
        if ($this->hasPhpSpreadsheet()) {
            return $this->readWithPhpSpreadsheet($filePath, $extension, $options);
        }

        // Fallback to CSV native (for CSV files only)
        if ($extension === 'csv') {
            return $this->readCsvNative($filePath, $options);
        }

        throw new \RuntimeException(
            'No Excel library available. Please install openspout/openspout or phpoffice/phpspreadsheet'
        );
    }

    /**
     * Read file using OpenSpout (streaming, best performance).
     *
     * @param string $filePath File path
     * @param string $extension File extension
     * @param array<string, mixed> $options Options
     * @return \Generator<int, array<string, mixed>> Row iterator
     */
    private function readWithOpenSpout(string $filePath, string $extension, array $options = []): \Generator
    {
        // OpenSpout implementation
        if (class_exists(\OpenSpout\Reader\XLSX\Reader::class)) {
            $reader = match ($extension) {
                'xlsx', 'xls' => new \OpenSpout\Reader\XLSX\Reader(),
                'csv' => new \OpenSpout\Reader\CSV\Reader(),
                'ods' => new \OpenSpout\Reader\ODS\Reader(),
                default => throw new \RuntimeException("Unsupported extension: {$extension}"),
            };

            $reader->open($filePath);
            $rowIndex = 0;
            $this->headers = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $row->getCells();
                    $rowData = [];

                    foreach ($cells as $cell) {
                        $rowData[] = $cell->getValue();
                    }

                    // Skip header row if needed
                    if ($this->hasHeader && $rowIndex === 0) {
                        $this->headers = $rowData;
                        $rowIndex++;
                        continue;
                    }

                    // Map row data using headers if available
                    if (!empty($this->headers)) {
                        $mappedRow = [];
                        foreach ($this->headers as $index => $header) {
                            $mappedRow[$header] = $rowData[$index] ?? null;
                        }
                        $rowData = $mappedRow;
                    }

                    // Apply row mapper if set
                    if ($this->rowMapper !== null) {
                        $rowData = ($this->rowMapper)($rowData, $rowIndex);
                    }

                    yield $rowIndex => $rowData;
                    $rowIndex++;
                }
            }

            $reader->close();
            return;
        }

        throw new \RuntimeException('OpenSpout classes not found');
    }

    /**
     * Read file using PhpSpreadsheet (slower but more features).
     *
     * @param string $filePath File path
     * @param string $extension File extension
     * @param array<string, mixed> $options Options
     * @return \Generator<int, array<string, mixed>> Row iterator
     */
    private function readWithPhpSpreadsheet(string $filePath, string $extension, array $options = []): \Generator
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        $rowIndex = 0;
        $this->headers = [];

        for ($row = 1; $row <= $highestRow; $row++) {
            $rowData = [];

            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cell = $worksheet->getCellByColumnAndRow($col, $row);
                $rowData[] = $cell->getValue();
            }

            // Skip header row if needed
            if ($this->hasHeader && $row === 1) {
                $this->headers = $rowData;
                continue;
            }

            // Map row data using headers if available
            if (!empty($this->headers)) {
                $mappedRow = [];
                foreach ($this->headers as $index => $header) {
                    $mappedRow[$header] = $rowData[$index] ?? null;
                }
                $rowData = $mappedRow;
            }

            // Apply row mapper if set
            if ($this->rowMapper !== null) {
                $rowData = ($this->rowMapper)($rowData, $rowIndex);
            }

            yield $rowIndex => $rowData;
            $rowIndex++;
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    /**
     * Read CSV file using native PHP (no library needed).
     *
     * @param string $filePath File path
     * @param array<string, mixed> $options Options
     * @return \Generator<int, array<string, mixed>> Row iterator
     */
    private function readCsvNative(string $filePath, array $options = []): \Generator
    {
        $delimiter = $options['delimiter'] ?? ',';
        $enclosure = $options['enclosure'] ?? '"';
        $escape = $options['escape'] ?? '\\';

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: {$filePath}");
        }

        $rowIndex = 0;
        $this->headers = [];

        while (($row = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== false) {
            // Skip header row if needed
            if ($this->hasHeader && $rowIndex === 0) {
                $this->headers = $row;
                $rowIndex++;
                continue;
            }

            // Map row data using headers if available
            if (!empty($this->headers)) {
                $mappedRow = [];
                foreach ($this->headers as $index => $header) {
                    $mappedRow[$header] = $row[$index] ?? null;
                }
                $row = $mappedRow;
            }

            // Apply row mapper if set
            if ($this->rowMapper !== null) {
                $row = ($this->rowMapper)($row, $rowIndex);
            }

            yield $rowIndex => $row;
            $rowIndex++;
        }

        fclose($handle);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTotalRows(string $filePath): int
    {
        // For performance, we don't count all rows upfront
        // This is a trade-off: accurate count vs performance
        return 0; // Unknown - will be calculated during import
    }

    /**
     * {@inheritdoc}
     */
    protected function processChunk(array $chunk, int $chunkIndex, array $options = []): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];
        $warnings = [];

        foreach ($chunk as $rowIndex => $row) {
            try {
                // Process row (e.g., save to database)
                if (isset($options['processor'])) {
                    $processor = $options['processor'];
                    if (is_callable($processor)) {
                        $processor($row, $rowIndex);
                    }
                }
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = [
                    'row' => $rowIndex,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check if OpenSpout is available.
     *
     * @return bool True if available
     */
    private function hasOpenSpout(): bool
    {
        return class_exists(\OpenSpout\Reader\XLSX\Reader::class);
    }

    /**
     * Check if PhpSpreadsheet is available.
     *
     * @return bool True if available
     */
    private function hasPhpSpreadsheet(): bool
    {
        return class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class);
    }
}
