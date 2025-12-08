<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

class ChapterModel extends Model
{
    protected static string $table = 'chapters';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'book_id', 'chapter_number', 'title', 'summary',
        'pages_count', 'words_count', 'reading_time_minutes', 'is_free_preview'
    ];
    protected static array $casts = [
        'book_id' => 'int',
        'chapter_number' => 'int',
        'pages_count' => 'int',
        'words_count' => 'int',
        'reading_time_minutes' => 'int',
        'is_free_preview' => 'bool',
    ];

    public function book()
    {
        return $this->belongsTo(BookModel::class, 'book_id');
    }

    public function pages()
    {
        return $this->hasMany(PageModel::class, 'chapter_id');
    }

    public function firstPage()
    {
        return $this->hasOne(PageModel::class, 'chapter_id')
            ->orderBy('page_number', 'ASC');
    }
}

