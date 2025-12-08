<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

class PageModel extends Model
{
    protected static string $table = 'pages';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'chapter_id', 'page_number', 'content', 'words_count', 'has_images', 'metadata'
    ];
    protected static array $casts = [
        'chapter_id' => 'int',
        'page_number' => 'int',
        'words_count' => 'int',
        'has_images' => 'bool',
        'metadata' => 'array',
    ];

    public function chapter()
    {
        return $this->belongsTo(ChapterModel::class, 'chapter_id');
    }
}

