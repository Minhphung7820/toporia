<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Blog;

use App\Domain\Entities\CommentAttachment;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Storage\UploadedFile;
use Toporia\Framework\Support\Accessors\Storage;

/**
 * Comment Upload Controller - Handles file uploads for comments.
 *
 * Supports images and files up to 15MB, max 9 files per comment.
 */
final class CommentUploadController extends BaseController
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    /**
     * Upload file(s) for comment.
     *
     * POST /api/blog/comments/upload
     *
     * Accepts multipart/form-data with 'files[]' field.
     * Returns array of uploaded file info.
     */
    public function upload(Request $request): JsonResponseInterface
    {
        $files = $request->file('files');

        // Handle both single file and array of files
        if (!is_array($files)) {
            $files = $files ? [$files] : [];
        }

        if (empty($files)) {
            return $this->json([
                'success' => false,
                'message' => 'No files uploaded',
            ], 400);
        }

        // Check max files limit
        if (count($files) > CommentAttachment::MAX_FILES_PER_COMMENT) {
            return $this->json([
                'success' => false,
                'message' => 'Maximum ' . CommentAttachment::MAX_FILES_PER_COMMENT . ' files allowed',
            ], 400);
        }

        $uploaded = [];
        $errors = [];

        foreach ($files as $index => $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            // Validate file
            if (!$file->isValid()) {
                $errors[] = [
                    'index' => $index,
                    'filename' => $file->getClientFilename(),
                    'error' => $file->getErrorMessage() ?? 'Upload failed',
                ];
                continue;
            }

            // Check file size
            if ($file->getSize() > CommentAttachment::MAX_FILE_SIZE) {
                $errors[] = [
                    'index' => $index,
                    'filename' => $file->getClientFilename(),
                    'error' => 'File too large. Maximum: 15MB',
                ];
                continue;
            }

            // Validate MIME type (use getRealMimeType for security - server-side detection)
            $mimeType = $file->getRealMimeType() ?? $file->getClientMimeType() ?? '';
            if (!CommentAttachment::isAllowedMimeType($mimeType)) {
                $errors[] = [
                    'index' => $index,
                    'filename' => $file->getClientFilename(),
                    'error' => 'File type not allowed',
                ];
                continue;
            }

            // Generate storage path
            $extension = $file->guessExtension() ?? 'bin';
            $yearMonth = date('Y/m');
            $filename = uniqid('comment_') . '_' . time() . '.' . $extension;
            $storagePath = "comments/{$yearMonth}/{$filename}";

            // Store file
            $stored = $file->store("comments/{$yearMonth}", $filename, 'public');

            if ($stored === false) {
                $errors[] = [
                    'index' => $index,
                    'filename' => $file->getClientFilename(),
                    'error' => 'Failed to save file',
                ];
                continue;
            }

            // Get image dimensions if applicable
            $width = null;
            $height = null;
            $type = CommentAttachment::determineType($mimeType);

            if ($type === CommentAttachment::TYPE_IMAGE) {
                // Get dimensions from the stored file using storage_path helper
                $fullPath = storage_path("app/public/{$storagePath}");
                if (file_exists($fullPath)) {
                    $imageInfo = @getimagesize($fullPath);
                    if ($imageInfo) {
                        $width = $imageInfo[0];
                        $height = $imageInfo[1];
                    }
                }
            }

            // Build public URL
            $url = Storage::disk('public')->url($storagePath);

            $uploaded[] = [
                'filename' => $file->getClientFilename(),
                'path' => $storagePath,
                'url' => $url,
                'mime_type' => $mimeType,
                'size' => $file->getSize(),
                'type' => $type,
                'width' => $width,
                'height' => $height,
            ];
        }

        if (empty($uploaded) && !empty($errors)) {
            return $this->json([
                'success' => false,
                'message' => 'All files failed to upload',
                'errors' => $errors,
            ], 400);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'files' => $uploaded,
                'errors' => $errors,
            ],
            'message' => count($uploaded) . ' file(s) uploaded successfully',
        ]);
    }

    /**
     * Delete an uploaded file (before comment is submitted).
     *
     * DELETE /api/blog/comments/upload
     */
    public function delete(Request $request): JsonResponseInterface
    {
        $path = $request->json('path');

        if (!$path) {
            return $this->json([
                'success' => false,
                'message' => 'File path required',
            ], 400);
        }

        // Security: Only allow deleting from comments directory
        if (!str_starts_with($path, 'comments/')) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid file path',
            ], 400);
        }

        $deleted = Storage::disk('public')->delete($path);

        return $this->json([
            'success' => $deleted,
            'message' => $deleted ? 'File deleted' : 'Failed to delete file',
        ]);
    }
}
