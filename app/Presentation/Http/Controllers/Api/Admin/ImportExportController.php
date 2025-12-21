<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Admin;

use App\Application\Services\Admin\ImportExportService;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Contracts\ResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Storage\UploadedFile;

/**
 * Import Export Controller - Handles posts import/export with progress tracking.
 *
 * Features:
 * - Import CSV with tier-based queue priority
 * - Export CSV with filters
 * - Real-time progress tracking
 * - Job management (status, cancel, download)
 */
final class ImportExportController extends BaseController
{
    /**
     * Allowed CSV MIME types.
     */
    private const ALLOWED_CSV_TYPES = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
        'text/x-csv',
        'application/x-csv',
    ];

    /**
     * Maximum upload file size (2GB).
     */
    private const MAX_UPLOAD_SIZE = 2 * 1024 * 1024 * 1024;

    public function __construct(
        Request $request,
        Response $response,
        private readonly ImportExportService $importExportService
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Start import job.
     *
     * POST /api/admin/posts/import
     */
    public function import(Request $request): JsonResponseInterface
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $file = $request->file('file');

            // Debug: Log file upload info
            if (!$file instanceof UploadedFile) {
                return $this->json([
                    'success' => false,
                    'message' => 'No file uploaded.',
                    'debug' => [
                        'has_files' => !empty($_FILES),
                        'content_type' => $request->header('Content-Type'),
                    ],
                ], 400);
            }

            // Validate file was uploaded successfully
            if (!$file->isValid()) {
                return $this->json([
                    'success' => false,
                    'message' => $file->getErrorMessage() ?? 'File upload failed.',
                ], 400);
            }

            // Validate file size
            if ($file->getSize() > self::MAX_UPLOAD_SIZE) {
                return $this->json([
                    'success' => false,
                    'message' => 'File too large. Maximum size: 100MB',
                ], 400);
            }

            // Validate MIME type
            if (!$file->isValidMimeType(self::ALLOWED_CSV_TYPES, strict: false)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid file type. Only CSV files are allowed.',
                ], 400);
            }

            // Store file temporarily for job processing
            // Use relative path for cross-environment compatibility
            $filename = uniqid('import_') . '.csv';
            $relativePath = "app/imports/{$filename}";
            $absolutePath = storage_path($relativePath);

            // Ensure directory exists
            $dir = dirname($absolutePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Move uploaded file to temp location (move() takes full destination path)
            if (!$file->move($absolutePath)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Failed to save uploaded file.',
                ], 500);
            }

            // Create import job - store relative path for cross-environment compatibility
            $result = $this->importExportService->createImportJob(
                userId: $userId,
                filePath: $relativePath,  // Relative to storage/
                originalFilename: $file->getClientFilename() ?? $filename
            );

            return $this->json($result, $result['success'] ? 200 : 400);
        } catch (\Throwable $e) {
            \Toporia\Framework\Support\Accessors\Log::error('Import controller error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Start export job.
     *
     * POST /api/admin/posts/export
     */
    public function export(Request $request): JsonResponseInterface
    {
        $userId = auth()->id();
        if (!$userId) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Get filters from request
        $filters = [];

        if ($request->has('is_published')) {
            $filters['is_published'] = (bool) $request->input('is_published');
        }

        if ($request->has('category_id')) {
            $filters['category_id'] = (int) $request->input('category_id');
        }

        if ($request->has('author_id')) {
            $filters['author_id'] = (int) $request->input('author_id');
        }

        // Create export job
        $result = $this->importExportService->createExportJob(
            userId: $userId,
            filters: $filters
        );

        return $this->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get job status.
     *
     * GET /api/admin/posts/jobs/{id}
     */
    public function status(Request $request, string $id): JsonResponseInterface
    {
        $userId = auth()->id();
        if (!$userId) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $result = $this->importExportService->getJobStatus($id, $userId);

        return $this->json($result, $result['success'] ? 200 : ($result['message'] === 'Job not found.' ? 404 : 403));
    }

    /**
     * Cancel a job.
     *
     * POST /api/admin/posts/jobs/{id}/cancel
     */
    public function cancel(Request $request, string $id): JsonResponseInterface
    {
        $userId = auth()->id();
        if (!$userId) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $result = $this->importExportService->cancelJob($id, $userId);

        return $this->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get user's jobs list.
     *
     * GET /api/admin/posts/jobs
     */
    public function jobs(Request $request): JsonResponseInterface
    {
        $userId = auth()->id();
        if (!$userId) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $type = $request->input('type'); // 'import' or 'export' or null for all
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 10);

        $result = $this->importExportService->getUserJobs($userId, $type, $page, $perPage);

        return $this->json($result);
    }

    /**
     * Get active jobs for current user.
     *
     * GET /api/admin/posts/jobs/active
     */
    public function activeJobs(Request $request): JsonResponseInterface
    {
        $userId = auth()->id();
        if (!$userId) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $activeJobs = $this->importExportService->getActiveJobs($userId);

        return $this->json([
            'success' => true,
            'data' => $activeJobs,
        ]);
    }

    /**
     * Download exported file.
     *
     * GET /api/admin/posts/jobs/{id}/download
     */
    public function download(Request $request, string $id): ResponseInterface
    {
        $userId = auth()->id();
        if (!$userId) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $result = $this->importExportService->getDownloadUrl($id, $userId);

        if (!$result['success']) {
            return $this->json($result, 400);
        }

        $filePath = $result['data']['file_path'];
        $filename = $result['data']['filename'];

        // Return file download response using ResponseFactory
        // Use application/octet-stream to force browser download instead of display
        return response()->download($filePath, $filename, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

}
