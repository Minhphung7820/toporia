<?php

declare(strict_types=1);

namespace App\Infrastructure\Exports;

use Toporia\Tabula\Exports\FromQueryExport;
use App\Infrastructure\Persistence\Models\PostModel;

/**
 * PostsExport
 *
 * Export all posts to CSV using Tabula.
 * Uses cursor() for memory-efficient streaming (O(1) memory).
 *
 * Optimized for large datasets (3M+ rows).
 */
final class PostsExport
{
    /**
     * Create export instance for all posts.
     *
     * @return FromQueryExport
     */
    public static function make(): FromQueryExport
    {
        return FromQueryExport::make(PostModel::query())
            ->columns([
                'id',
                'title',
                'slug',
                'content',
                'excerpt',
                'author_id',
                'category_id',
                'is_published',
                'is_featured',
                'views',
                'reading_time',
                'published_at',
                'created_at',
                'updated_at',
            ])
            ->withHeaders([
                'ID',
                'Title',
                'Slug',
                'Content',
                'Excerpt',
                'Author ID',
                'Category ID',
                'Is Published',
                'Is Featured',
                'Views',
                'Reading Time',
                'Published At',
                'Created At',
                'Updated At',
            ])
            ->format('is_published', fn($v) => $v ? '1' : '0')
            ->format('is_featured', fn($v) => $v ? '1' : '0')
            ->withTitle('Posts');
    }

    /**
     * Create export instance with filters.
     *
     * @param bool|null $isPublished Filter by published status
     * @param int|null $categoryId Filter by category
     * @param int|null $authorId Filter by author
     * @return FromQueryExport
     */
    public static function filtered(
        ?bool $isPublished = null,
        ?int $categoryId = null,
        ?int $authorId = null
    ): FromQueryExport {
        $query = PostModel::query();

        if ($isPublished !== null) {
            $query = $query->where('is_published', $isPublished ? 1 : 0);
        }

        if ($categoryId !== null) {
            $query = $query->where('category_id', $categoryId);
        }

        if ($authorId !== null) {
            $query = $query->where('author_id', $authorId);
        }

        return FromQueryExport::make($query)
            ->columns([
                'id',
                'title',
                'slug',
                'content',
                'excerpt',
                'author_id',
                'category_id',
                'is_published',
                'is_featured',
                'views',
                'reading_time',
                'published_at',
                'created_at',
                'updated_at',
            ])
            ->withHeaders([
                'ID',
                'Title',
                'Slug',
                'Content',
                'Excerpt',
                'Author ID',
                'Category ID',
                'Is Published',
                'Is Featured',
                'Views',
                'Reading Time',
                'Published At',
                'Created At',
                'Updated At',
            ])
            ->format('is_published', fn($v) => $v ? '1' : '0')
            ->format('is_featured', fn($v) => $v ? '1' : '0')
            ->withTitle('Posts');
    }
}
