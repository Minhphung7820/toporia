<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Post ORM Model for blog posts.
 *
 * @property int $id
 * @property string $title
 * @property string|null $slug
 * @property string|null $content
 * @property string|null $excerpt
 * @property string|null $featured_image
 * @property int $views
 * @property int $reading_time
 * @property bool $is_published
 * @property bool $is_featured
 * @property int|null $author_id
 * @property int|null $category_id
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property array|null $meta_keywords
 * @property string|null $published_at
 * @property string|null $scheduled_at
 * @property string $created_at
 * @property string $updated_at
 */
class PostModel extends Model
{
    protected static string $table = 'posts';

    protected static array $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_image',
        'views',
        'reading_time',
        'is_published',
        'is_featured',
        'author_id',
        'category_id',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'published_at',
        'scheduled_at',
    ];

    protected static array $casts = [
        'views' => 'int',
        'reading_time' => 'int',
        'is_published' => 'bool',
        'is_featured' => 'bool',
        'author_id' => 'int',
        'category_id' => 'int',
        'meta_keywords' => 'array',
    ];

    /**
     * Post belongs to author (user).
     */
    public function author()
    {
        return $this->belongsTo(UserModel::class, 'author_id');
    }

    /**
     * Post belongs to primary category.
     */
    public function category()
    {
        return $this->belongsTo(CategoryModel::class, 'category_id');
    }

    /**
     * Post belongs to many categories (many-to-many).
     */
    public function categories()
    {
        return $this->belongsToMany(CategoryModel::class, 'post_categories')
            ->withTimestamps();
    }

    /**
     * Post has one image (polymorphic one-to-one).
     */
    public function image()
    {
        return $this->morphOne(ImageModel::class, 'imageable');
    }

    /**
     * Post has many comments (polymorphic one-to-many).
     * Only returns root comments (no parent).
     */
    public function comments()
    {
        return $this->morphMany(CommentModel::class, 'commentable');
    }

    /**
     * Post has many approved comments.
     */
    public function approvedComments()
    {
        return $this->morphMany(CommentModel::class, 'commentable')
            ->where('is_approved', true)
            ->whereNull('parent_id');
    }

    /**
     * Post has many tags (polymorphic many-to-many).
     */
    public function tags()
    {
        return $this->morphToMany(TagModel::class, 'taggable', 'taggables')
            ->withPivot('created_at')
            ->withTimestamps();
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Get published posts.
     */
    public static function published()
    {
        return static::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', date('Y-m-d H:i:s'));
    }

    /**
     * Get featured posts.
     */
    public static function featured()
    {
        return static::published()
            ->where('is_featured', true);
    }

    /**
     * Get draft posts.
     */
    public static function drafts()
    {
        return static::query()
            ->where('is_published', false)
            ->whereNull('scheduled_at');
    }

    /**
     * Get scheduled posts.
     */
    public static function scheduled()
    {
        return static::query()
            ->where('is_published', false)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', date('Y-m-d H:i:s'));
    }

    /**
     * Get posts ready to be published (scheduled and past due).
     */
    public static function scheduledReady()
    {
        return static::query()
            ->where('is_published', false)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', date('Y-m-d H:i:s'));
    }

    /**
     * Get most viewed posts.
     */
    public static function mostViewed(int $limit = 10)
    {
        return static::published()
            ->orderBy('views', 'desc')
            ->limit($limit);
    }

    /**
     * Get latest posts.
     */
    public static function latest(int $limit = 10)
    {
        return static::published()
            ->orderBy('published_at', 'desc')
            ->limit($limit);
    }

    /**
     * Get posts by category.
     */
    public static function byCategory(int $categoryId)
    {
        return static::published()
            ->where('category_id', $categoryId);
    }

    /**
     * Get posts by author.
     */
    public static function byAuthor(int $authorId)
    {
        return static::query()
            ->where('author_id', $authorId);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Calculate and set reading time based on content.
     */
    public function calculateReadingTime(int $wordsPerMinute = 200): int
    {
        $wordCount = str_word_count(strip_tags($this->content ?? ''));
        return max(1, (int) ceil($wordCount / $wordsPerMinute));
    }

    /**
     * Check if post is a draft.
     */
    public function isDraft(): bool
    {
        return !$this->is_published && $this->scheduled_at === null;
    }

    /**
     * Check if post is scheduled.
     */
    public function isScheduled(): bool
    {
        return !$this->is_published && $this->scheduled_at !== null;
    }

    /**
     * Check if post is ready to publish.
     */
    public function isReadyToPublish(): bool
    {
        return $this->isScheduled() && strtotime($this->scheduled_at) <= time();
    }

    /**
     * Get the foreign key name for this model.
     */
    protected function getForeignKey(): string
    {
        return 'post_id';
    }
}
