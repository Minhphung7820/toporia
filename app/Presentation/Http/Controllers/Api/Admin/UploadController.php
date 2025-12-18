<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Admin;

use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Upload Controller - Handles file uploads for admin.
 */
final class UploadController extends BaseController
{
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

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
     */
    public function upload(Request $request): JsonResponseInterface
    {
        // CKEditor 5 uses 'upload' field, but we also support 'file'
        $file = $request->file('upload') ?? $request->file('file');

        if (!$file) {
            return $this->json([
                'error' => ['message' => 'No file uploaded'],
            ], 400);
        }

        // Validate file type
        $mimeType = $file['type'] ?? '';
        if (!in_array($mimeType, self::ALLOWED_IMAGE_TYPES, true)) {
            return $this->json([
                'error' => ['message' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP'],
            ], 400);
        }

        // Validate file size
        $fileSize = $file['size'] ?? 0;
        if ($fileSize > self::MAX_FILE_SIZE) {
            return $this->json([
                'error' => ['message' => 'File too large. Maximum size: 5MB'],
            ], 400);
        }

        // Validate upload was successful
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return $this->json([
                'error' => ['message' => 'File upload failed'],
            ], 400);
        }

        // Generate unique filename
        $extension = $this->getExtensionFromMime($mimeType);
        $filename = date('Y/m/') . uniqid() . '_' . time() . '.' . $extension;

        // Create directory if needed
        $uploadDir = public_path('uploads/images/' . date('Y/m'));
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Move uploaded file
        $targetPath = public_path('uploads/images/' . $filename);
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $this->json([
                'error' => ['message' => 'Failed to save file'],
            ], 500);
        }

        // Build URL
        $url = url('/uploads/images/' . $filename);

        // Return CKEditor 5 compatible response
        return $this->json([
            'url' => $url,
            'uploaded' => true,
        ]);
    }

    /**
     * Get file extension from MIME type.
     */
    private function getExtensionFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
