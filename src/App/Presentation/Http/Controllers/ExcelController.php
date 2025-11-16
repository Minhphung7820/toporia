<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Domain\Contracts\Repository\ProductRepository;
use App\Infrastructure\Export\ExcelExporter;
use App\Infrastructure\Import\ExcelImporter;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Excel Controller
 *
 * Handles Excel import/export via web interface.
 * Supports file uploads and downloads.
 *
 * Clean Architecture:
 * - Presentation layer (Controller)
 * - Uses Domain repositories
 * - Uses Infrastructure importers/exporters
 */
final class ExcelController extends BaseController
{
    /**
     * Import Excel file.
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @return void
     */
    public function import(Request $request, Response $response): void
    {
        // Get uploaded file from $_FILES
        $fileKey = 'file';
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            $response->json(['error' => 'No file uploaded or upload error'], 400);
            return;
        }

        $uploadedFile = $_FILES[$fileKey];
        $chunkSize = (int) $request->input('chunk_size', 1000);
        $hasHeader = $request->input('has_header', true);

        // Move uploaded file to temp location
        $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
        $tempPath = sys_get_temp_dir() . '/' . uniqid('import_', true) . '.' . $extension;

        if (!move_uploaded_file($uploadedFile['tmp_name'], $tempPath)) {
            $response->json(['error' => 'Failed to move uploaded file'], 500);
            return;
        }

        try {
            $importer = new ExcelImporter($chunkSize);
            $importer->setHasHeader($hasHeader);

            // Set processor for products
            $container = \Toporia\Framework\Container\Container::getInstance();
            $repository = $container->get(ProductRepository::class);

            $options = [
                'processor' => function (array $row, int $index) use ($repository) {
                    // Process row (e.g., save to database)
                    // This is a placeholder - implement your business logic
                    // Example:
                    // $product = \App\Domain\Product\Product::fromArray($row);
                    // $repository->save($product);
                },
            ];

            $result = $importer->import($tempPath, $options);

            // Clean up temp file
            unlink($tempPath);

            $response->json([
                'success' => true,
                'result' => $result->toArray(),
            ]);
        } catch (\Throwable $e) {
            // Clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            $response->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export data to Excel.
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param ProductRepository $repository Product repository
     * @return void
     */
    public function export(Request $request, Response $response, ProductRepository $repository): void
    {
        $format = $request->input('format', 'xlsx');
        $chunkSize = (int) $request->input('chunk_size', 1000);
        $filename = $request->input('filename', 'products_' . date('Y-m-d_His') . '.xlsx');

        try {
            $products = $repository->findAll();

            $exporter = new ExcelExporter($chunkSize);
            $exporter->setRowMapper(function ($product) {
                return [
                    $product->id,
                    $product->title,
                    $product->sku,
                    $product->description,
                    $product->price,
                    $product->stock,
                    $product->isActive ? 'Yes' : 'No',
                    $product->status,
                ];
            });

            $options = [
                'headers' => ['ID', 'Title', 'SKU', 'Description', 'Price', 'Stock', 'Active', 'Status'],
            ];

            // Export to download
            $exporter->exportToDownload($products, $filename, $options);
        } catch (\Throwable $e) {
            $response->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
