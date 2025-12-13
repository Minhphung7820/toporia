<?php

declare(strict_types=1);

namespace App\Infrastructure\Export;

use App\Domain\ValueObjects\Export\ExportResult;

/**
 * Excel Exporter Implementation
 *
 * Exports data to Excel files (XLSX, XLS, CSV, ODS) with streaming support.
 * Optimized for large datasets (millions of rows).
 *
 * Clean Architecture:
 * - Implements Domain ExportInterface via BaseExporter
 * - Uses OpenSpout or PhpSpreadsheet for Excel writing
 *
 * SOLID Principles:
 * - Single Responsibility: Exports to Excel files only
 * - Open/Closed: Extensible via BaseExporter
 *
 * Performance:
 * - Streaming: Writes file row by row (O(1) memory)
 * - Chunking: Processes data in chunks
 * - Supports millions of rows
 * - Memory-efficient
 */
final class ExcelExporter extends BaseExporter
{
    /**
     * @var callable|null Row mapper function
     */
    private $rowMapper = null;

    /**
     * Set row mapper function.
     *
     * @param callable $mapper Function to map entity to row data
     * @return self
     */
    public function setRowMapper(callable $mapper): self
    {
        $this->rowMapper = $mapper;
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
    protected function initializeWriter(string $filePath, array $options = []): mixed
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Try OpenSpout first (best for large files)
        if ($this->hasOpenSpout()) {
            return $this->initializeOpenSpoutWriter($filePath, $extension);
        }

        // Fallback to PhpSpreadsheet
        if ($this->hasPhpSpreadsheet()) {
            return $this->initializePhpSpreadsheetWriter($filePath, $extension);
        }

        // Fallback to CSV native (for CSV files only)
        if ($extension === 'csv') {
            return $this->initializeCsvWriter($filePath, $options);
        }

        throw new \RuntimeException(
            'No Excel library available. Please install openspout/openspout or phpoffice/phpspreadsheet'
        );
    }

    /**
     * Initialize OpenSpout writer.
     *
     * @param string $filePath File path
     * @param string $extension File extension
     * @return mixed Writer instance
     */
    private function initializeOpenSpoutWriter(string $filePath, string $extension): mixed
    {
        $writer = match ($extension) {
            'xlsx', 'xls' => new \OpenSpout\Writer\XLSX\Writer(),
            'csv' => new \OpenSpout\Writer\CSV\Writer(),
            'ods' => new \OpenSpout\Writer\ODS\Writer(),
            default => throw new \RuntimeException("Unsupported extension: {$extension}"),
        };

        $writer->openToFile($filePath);
        return $writer;
    }

    /**
     * Initialize PhpSpreadsheet writer.
     *
     * @param string $filePath File path
     * @param string $extension File extension
     * @return mixed Spreadsheet instance
     */
    private function initializePhpSpreadsheetWriter(string $filePath, string $extension): mixed
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('Sheet1');
        return ['spreadsheet' => $spreadsheet, 'worksheet' => $worksheet, 'row' => 1, 'filePath' => $filePath];
    }

    /**
     * Initialize CSV writer.
     *
     * @param string $filePath File path
     * @param array<string, mixed> $options Options
     * @return resource File handle
     */
    private function initializeCsvWriter(string $filePath, array $options = []): mixed
    {
        $handle = fopen($filePath, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Cannot create file: {$filePath}");
        }
        return ['handle' => $handle, 'delimiter' => $options['delimiter'] ?? ','];
    }

    /**
     * {@inheritdoc}
     */
    protected function writeHeaders(mixed $writer, array $headers): void
    {
        if (empty($headers)) {
            return;
        }

        // OpenSpout
        if ($writer instanceof \OpenSpout\Writer\WriterInterface) {
            $cells = array_map(fn($header) => new \OpenSpout\Common\Entity\Cell\StringCell($header, null), $headers);
            $row = new \OpenSpout\Common\Entity\Row($cells);
            $writer->addRow($row);
            return;
        }

        // PhpSpreadsheet
        if (is_array($writer) && isset($writer['worksheet'])) {
            $col = 1;
            foreach ($headers as $header) {
                $writer['worksheet']->setCellValueByColumnAndRow($col, 1, $header);
                $col++;
            }
            $writer['row'] = 2;
            return;
        }

        // CSV native
        if (is_array($writer) && isset($writer['handle'])) {
            fputcsv($writer['handle'], $headers, $writer['delimiter']);
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function writeChunk(mixed $writer, array $chunk, int $chunkIndex, array $options = []): void
    {
        foreach ($chunk as $item) {
            // Map entity to row data
            $rowData = $this->mapEntityToRow($item, $options);

            // OpenSpout
            if ($writer instanceof \OpenSpout\Writer\WriterInterface) {
                $cells = [];
                foreach ($rowData as $value) {
                    if (is_numeric($value)) {
                        $cells[] = new \OpenSpout\Common\Entity\Cell\NumericCell((float) $value, null);
                    } else {
                        $cells[] = new \OpenSpout\Common\Entity\Cell\StringCell((string) $value, null);
                    }
                }
                $row = new \OpenSpout\Common\Entity\Row($cells);
                $writer->addRow($row);
                continue;
            }

            // PhpSpreadsheet
            if (is_array($writer) && isset($writer['worksheet'])) {
                $col = 1;
                foreach ($rowData as $value) {
                    $writer['worksheet']->setCellValueByColumnAndRow($col, $writer['row'], $value);
                    $col++;
                }
                $writer['row']++;
                continue;
            }

            // CSV native
            if (is_array($writer) && isset($writer['handle'])) {
                fputcsv($writer['handle'], $rowData, $writer['delimiter']);
                continue;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function finalizeWriter(mixed $writer): void
    {
        // OpenSpout
        if ($writer instanceof \OpenSpout\Writer\WriterInterface) {
            $writer->close();
            return;
        }

        // PhpSpreadsheet
        if (is_array($writer) && isset($writer['spreadsheet'])) {
            $writerType = match (strtolower(pathinfo($writer['filePath'], PATHINFO_EXTENSION))) {
                'xlsx' => \PhpOffice\PhpSpreadsheet\IOFactory::WRITER_XLSX,
                'xls' => \PhpOffice\PhpSpreadsheet\IOFactory::WRITER_XLS,
                'ods' => \PhpOffice\PhpSpreadsheet\IOFactory::WRITER_ODS,
                'csv' => \PhpOffice\PhpSpreadsheet\IOFactory::WRITER_CSV,
                default => \PhpOffice\PhpSpreadsheet\IOFactory::WRITER_XLSX,
            };
            $excelWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($writer['spreadsheet'], $writerType);
            $excelWriter->save($writer['filePath']);
            $writer['spreadsheet']->disconnectWorksheets();
            unset($writer['spreadsheet']);
            return;
        }

        // CSV native
        if (is_array($writer) && isset($writer['handle'])) {
            fclose($writer['handle']);
            return;
        }
    }

    /**
     * Map entity to row data.
     *
     * @param mixed $entity Entity
     * @param array<string, mixed> $options Options
     * @return array<mixed> Row data
     */
    private function mapEntityToRow(mixed $entity, array $options = []): array
    {
        // Use custom mapper if provided
        if ($this->rowMapper !== null) {
            return ($this->rowMapper)($entity);
        }

        // Default: convert entity to array
        if (is_object($entity)) {
            if (method_exists($entity, 'toArray')) {
                return array_values($entity->toArray());
            }
            return array_values((array) $entity);
        }

        if (is_array($entity)) {
            return array_values($entity);
        }

        return [$entity];
    }

    /**
     * {@inheritdoc}
     */
    protected function getHeaders(array $options = []): array
    {
        $headers = $options['headers'] ?? [];

        // Auto-generate headers from entity if not provided
        if (empty($headers) && isset($options['sample_entity'])) {
            $sample = $options['sample_entity'];
            if (is_object($sample) && method_exists($sample, 'toArray')) {
                $data = $sample->toArray();
                $headers = array_keys($data);
            } elseif (is_array($sample)) {
                $headers = array_keys($sample);
            }
        }

        return $headers;
    }

    /**
     * Check if OpenSpout is available.
     *
     * @return bool True if available
     */
    private function hasOpenSpout(): bool
    {
        return class_exists(\OpenSpout\Writer\XLSX\Writer::class);
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
