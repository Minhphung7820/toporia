<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * CommentAttachment Model - ORM model for comment_attachments table.
 */
class CommentAttachmentModel extends Model
{
    protected static string $table = 'comment_attachments';

    protected static array $fillable = [
        'comment_id',
        'filename',
        'path',
        'mime_type',
        'size',
        'type',
        'width',
        'height',
        'thumbnail_path',
        'sort_order',
    ];

    protected static array $casts = [
        'comment_id' => 'int',
        'size' => 'int',
        'width' => 'int',
        'height' => 'int',
        'sort_order' => 'int',
    ];

    /**
     * Attributes to append when serializing to array/JSON.
     */
    protected static array $appends = ['url'];

    /**
     * Get the public URL for this attachment.
     */
    public function getUrlAttribute(): ?string
    {
        if (empty($this->path)) {
            return null;
        }
        return '/storage/' . $this->path;
    }

    /**
     * Relationship: Comment that owns this attachment.
     */
    public function comment()
    {
        return $this->belongsTo(CommentModel::class, 'comment_id', 'id');
    }

    /**
     * Scope: Get attachments by type.
     */
    public static function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Get images only.
     */
    public static function scopeImages($query)
    {
        return $query->where('type', 'image');
    }

    /**
     * Scope: Get files only.
     */
    public static function scopeFiles($query)
    {
        return $query->where('type', 'file');
    }
}
