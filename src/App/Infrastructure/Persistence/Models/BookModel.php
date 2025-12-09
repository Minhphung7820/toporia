<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Book Model
 *
 * Relationships demonstrated:
 * - belongsTo: author, publisher
 * - hasMany: chapters
 * - belongsToMany: categories
 * - hasManyThrough: pages (through chapters)
 */
class BookModel extends Model
{
    protected static string $table = 'books';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'author_id',
        'publisher_id',
        'title',
        'isbn',
        'description',
        'pages_count',
        'price',
        'published_year',
        'stock',
        'rating',
        'reviews_count',
        'is_bestseller',
        'is_available'
    ];
    protected static array $casts = [
        'author_id' => 'int',
        'publisher_id' => 'int',
        'pages_count' => 'int',
        'price' => 'float',
        'published_year' => 'int',
        'stock' => 'int',
        'rating' => 'float',
        'reviews_count' => 'int',
        'is_bestseller' => 'bool',
        'is_available' => 'bool',
    ];

    public function author()
    {
        return $this->belongsTo(AuthorModel::class, 'author_id');
    }

    public function publisher()
    {
        return $this->belongsTo(PublisherModel::class, 'publisher_id');
    }

    /**
     * hasMany: A book has many chapters
     */
    public function chapters()
    {
        return $this->hasMany(ChapterModel::class, 'book_id');
    }

    /**
     * hasOne: First chapter
     */
    public function firstChapter()
    {
        return $this->hasOne(ChapterModel::class, 'book_id')
            ->orderBy('chapter_number', 'ASC');
    }

    /**
     * belongsToMany: A book belongs to many categories
     */
    public function categories()
    {
        return $this->belongsToMany(
            CategoryModel::class,
            'book_category',
            'book_id',
            'category_id'
        )->withPivot('is_primary', 'order')
            ->wherePivot('is_primary', true)
            ->withTimestamps();
    }

    /**
     * hasManyThrough: A book has many pages through chapters
     */
    public function pages()
    {
        return $this->hasManyThrough(
            PageModel::class,
            ChapterModel::class,
            'book_id',
            'chapter_id',
            'id',
            'id'
        );
    }
}
