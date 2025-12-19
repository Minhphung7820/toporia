<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Admin;

use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Storage\UploadedFile;
use Toporia\Framework\Support\Accessors\Storage;

/**
 * Upload Controller - Handles file uploads for admin.
 *
 * Uses framework's UploadedFile and Storage abstraction for secure,
 * standardized file handling with MIME type validation.
 */
final class UploadController extends BaseController
{
    /**
     * Allowed image MIME types.
     */
    private const ALLOWED_IMAGE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Maximum file size in bytes (5MB).
     */
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    /**
     * Upload an image file.
     *
     * POST /api/admin/upload
     *
     * Accepts multipart/form-data with 'upload' or 'file' field.
     * Returns CKEditor-compatible response format.
     *
     * Security features:
     * - Server-side MIME type detection (not trusting client)
     * - File extension validation
     * - Size limit enforcement
     * - Unique filename generation
     */
    public function upload(Request $request): JsonResponseInterface
    {
        // CKEditor 5 uses 'upload' field, but we also support 'file'
        $file = $request->file('upload') ?? $request->file('file');

        if (!$file instanceof UploadedFile) {
            return $this->json([
                'error' => ['message' => 'No file uploaded'],
            ], 400);
        }

        // Validate file was uploaded successfully
        if (!$file->isValid()) {
            $errorMessage = $file->getErrorMessage() ?? 'File upload failed';
            return $this->json([
                'error' => ['message' => $errorMessage],
            ], 400);
        }

        // Validate file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return $this->json([
                'error' => ['message' => 'File too large. Maximum size: 5MB'],
            ], 400);
        }

        // Validate MIME type using server-side detection (more secure)
        // strict=false allows client MIME type mismatch for better compatibility
        if (!$file->isValidMimeType(self::ALLOWED_IMAGE_TYPES, strict: false)) {
            return $this->json([
                'error' => ['message' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP'],
            ], 400);
        }

        // Generate unique filename with date-based directory structure
        $extension = $file->guessExtension() ?? 'jpg';
        $yearMonth = date('Y/m');
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $storagePath = "images/{$yearMonth}/{$filename}";

        // Store file using Storage abstraction (public disk)
        $stored = $file->store("images/{$yearMonth}", $filename, 'public');

        if ($stored === false) {
            return $this->json([
                'error' => ['message' => 'Failed to save file'],
            ], 500);
        }

        // Build public URL
        $url = Storage::disk('public')->url($storagePath);

        // Return CKEditor 5 compatible response
        return $this->json([
            'url' => $url,
            'uploaded' => true,
        ]);
    }
}
