<?php

declare(strict_types=1);

namespace App\Domain\Entities;

/**
 * CommentAttachment Entity - Domain model for comment file attachments.
 *
 * Supports images and files with size limit 15MB, max 9 per comment.
 */
final class CommentAttachment
{
    public const MAX_FILE_SIZE = 15 * 1024 * 1024; // 15MB
    public const MAX_FILES_PER_COMMENT = 9;

    public const TYPE_IMAGE = 'image';
    public const TYPE_FILE = 'file';

    public const ALLOWED_IMAGE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public const ALLOWED_FILE_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
        'application/zip',
        'application/x-rar-compressed',
    ];

    public function __construct(
        public readonly ?int $id,
        public readonly int $commentId,
        public readonly string $filename,
        public readonly string $path,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly string $type = self::TYPE_FILE,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?string $thumbnailPath = null,
        public readonly int $sortOrder = 0,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $updatedAt = null
    ) {}

    /**
     * Create with ID after persistence.
     */
    public function withId(int $id): self
    {
        return new self(
            id: $id,
            commentId: $this->commentId,
            filename: $this->filename,
            path: $this->path,
            mimeType: $this->mimeType,
            size: $this->size,
            type: $this->type,
            width: $this->width,
            height: $this->height,
            thumbnailPath: $this->thumbnailPath,
            sortOrder: $this->sortOrder,
            createdAt: $this->createdAt ?? new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Check if attachment is an image.
     */
    public function isImage(): bool
    {
        return $this->type === self::TYPE_IMAGE;
    }

    /**
     * Get human-readable file size.
     */
    public function getHumanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->size;
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get file extension from filename.
     */
    public function getExtension(): string
    {
        return strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'comment_id' => $this->commentId,
            'filename' => $this->filename,
            'path' => $this->path,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'size_human' => $this->getHumanSize(),
            'type' => $this->type,
            'width' => $this->width,
            'height' => $this->height,
            'thumbnail_path' => $this->thumbnailPath,
            'sort_order' => $this->sortOrder,
            'extension' => $this->getExtension(),
            'is_image' => $this->isImage(),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Validate MIME type is allowed.
     */
    public static function isAllowedMimeType(string $mimeType): bool
    {
        return in_array($mimeType, self::ALLOWED_IMAGE_TYPES, true)
            || in_array($mimeType, self::ALLOWED_FILE_TYPES, true);
    }

    /**
     * Determine type from MIME type.
     */
    public static function determineType(string $mimeType): string
    {
        return in_array($mimeType, self::ALLOWED_IMAGE_TYPES, true)
            ? self::TYPE_IMAGE
            : self::TYPE_FILE;
    }
}
