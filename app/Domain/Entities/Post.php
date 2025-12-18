<?php

declare(strict_types=1);

namespace App\Domain\Entities;

/**
 * Post Entity - Domain model for blog posts.
 *
 * Immutable entity following Clean Architecture principles.
 *
 * SOLID Principles:
 * - Single Responsibility: Post business logic only
 * - Immutability: All properties readonly, with* methods for changes
 */
final class Post
{
    /**
     * @param int|null $id Post ID
     * @param string $title Post title
     * @param string $slug URL-friendly slug
     * @param string|null $content Post content (HTML/Markdown)
     * @param string|null $excerpt Short description
     * @param string|null $featuredImage Featured image URL
     * @param int $views View count
     * @param int $readingTime Estimated reading time in minutes
     * @param bool $isPublished Whether post is published
     * @param bool $isFeatured Whether post is featured
     * @param int|null $authorId Author user ID
     * @param int|null $categoryId Primary category ID
     * @param string|null $metaTitle SEO title
     * @param string|null $metaDescription SEO description
     * @param array|null $metaKeywords SEO keywords
     * @param \DateTimeImmutable|null $publishedAt When post was published
     * @param \DateTimeImmutable|null $scheduledAt When post is scheduled to publish
     * @param \DateTimeImmutable|null $createdAt Creation timestamp
     * @param \DateTimeImmutable|null $updatedAt Last update timestamp
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly ?string $content = null,
        public readonly ?string $excerpt = null,
        public readonly ?string $featuredImage = null,
        public readonly int $views = 0,
        public readonly int $readingTime = 0,
        public readonly bool $isPublished = false,
        public readonly bool $isFeatured = false,
        public readonly ?int $authorId = null,
        public readonly ?int $categoryId = null,
        public readonly ?string $metaTitle = null,
        public readonly ?string $metaDescription = null,
        public readonly ?array $metaKeywords = null,
        public readonly ?\DateTimeImmutable $publishedAt = null,
        public readonly ?\DateTimeImmutable $scheduledAt = null,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $updatedAt = null
    ) {}

    /**
     * Create a new Post with ID (after persistence).
     */
    public function withId(int $id): self
    {
        return new self(
            id: $id,
            title: $this->title,
            slug: $this->slug,
            content: $this->content,
            excerpt: $this->excerpt,
            featuredImage: $this->featuredImage,
            views: $this->views,
            readingTime: $this->readingTime,
            isPublished: $this->isPublished,
            isFeatured: $this->isFeatured,
            authorId: $this->authorId,
            categoryId: $this->categoryId,
            metaTitle: $this->metaTitle,
            metaDescription: $this->metaDescription,
            metaKeywords: $this->metaKeywords,
            publishedAt: $this->publishedAt,
            scheduledAt: $this->scheduledAt,
            createdAt: $this->createdAt ?? new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Publish the post.
     */
    public function publish(): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            slug: $this->slug,
            content: $this->content,
            excerpt: $this->excerpt,
            featuredImage: $this->featuredImage,
            views: $this->views,
            readingTime: $this->readingTime,
            isPublished: true,
            isFeatured: $this->isFeatured,
            authorId: $this->authorId,
            categoryId: $this->categoryId,
            metaTitle: $this->metaTitle,
            metaDescription: $this->metaDescription,
            metaKeywords: $this->metaKeywords,
            publishedAt: new \DateTimeImmutable(),
            scheduledAt: null,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Unpublish the post.
     */
    public function unpublish(): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            slug: $this->slug,
            content: $this->content,
            excerpt: $this->excerpt,
            featuredImage: $this->featuredImage,
            views: $this->views,
            readingTime: $this->readingTime,
            isPublished: false,
            isFeatured: $this->isFeatured,
            authorId: $this->authorId,
            categoryId: $this->categoryId,
            metaTitle: $this->metaTitle,
            metaDescription: $this->metaDescription,
            metaKeywords: $this->metaKeywords,
            publishedAt: $this->publishedAt,
            scheduledAt: null,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Toggle featured status.
     */
    public function toggleFeatured(): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            slug: $this->slug,
            content: $this->content,
            excerpt: $this->excerpt,
            featuredImage: $this->featuredImage,
            views: $this->views,
            readingTime: $this->readingTime,
            isPublished: $this->isPublished,
            isFeatured: !$this->isFeatured,
            authorId: $this->authorId,
            categoryId: $this->categoryId,
            metaTitle: $this->metaTitle,
            metaDescription: $this->metaDescription,
            metaKeywords: $this->metaKeywords,
            publishedAt: $this->publishedAt,
            scheduledAt: $this->scheduledAt,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Increment view count.
     */
    public function incrementViews(): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            slug: $this->slug,
            content: $this->content,
            excerpt: $this->excerpt,
            featuredImage: $this->featuredImage,
            views: $this->views + 1,
            readingTime: $this->readingTime,
            isPublished: $this->isPublished,
            isFeatured: $this->isFeatured,
            authorId: $this->authorId,
            categoryId: $this->categoryId,
            metaTitle: $this->metaTitle,
            metaDescription: $this->metaDescription,
            metaKeywords: $this->metaKeywords,
            publishedAt: $this->publishedAt,
            scheduledAt: $this->scheduledAt,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt
        );
    }

    /**
     * Schedule the post for future publication.
     */
    public function schedule(\DateTimeImmutable $scheduledAt): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            slug: $this->slug,
            content: $this->content,
            excerpt: $this->excerpt,
            featuredImage: $this->featuredImage,
            views: $this->views,
            readingTime: $this->readingTime,
            isPublished: false,
            isFeatured: $this->isFeatured,
            authorId: $this->authorId,
            categoryId: $this->categoryId,
            metaTitle: $this->metaTitle,
            metaDescription: $this->metaDescription,
            metaKeywords: $this->metaKeywords,
            publishedAt: null,
            scheduledAt: $scheduledAt,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Check if post is scheduled for future publication.
     */
    public function isScheduled(): bool
    {
        return $this->scheduledAt !== null && !$this->isPublished;
    }

    /**
     * Check if post is a draft.
     */
    public function isDraft(): bool
    {
        return !$this->isPublished && $this->scheduledAt === null;
    }

    /**
     * Calculate reading time based on content.
     *
     * @param int $wordsPerMinute Average reading speed (default: 200)
     */
    public function calculateReadingTime(int $wordsPerMinute = 200): self
    {
        $wordCount = str_word_count(strip_tags($this->content ?? ''));
        $readingTime = max(1, (int) ceil($wordCount / $wordsPerMinute));

        return new self(
            id: $this->id,
            title: $this->title,
            slug: $this->slug,
            content: $this->content,
            excerpt: $this->excerpt,
            featuredImage: $this->featuredImage,
            views: $this->views,
            readingTime: $readingTime,
            isPublished: $this->isPublished,
            isFeatured: $this->isFeatured,
            authorId: $this->authorId,
            categoryId: $this->categoryId,
            metaTitle: $this->metaTitle,
            metaDescription: $this->metaDescription,
            metaKeywords: $this->metaKeywords,
            publishedAt: $this->publishedAt,
            scheduledAt: $this->scheduledAt,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            'featured_image' => $this->featuredImage,
            'views' => $this->views,
            'reading_time' => $this->readingTime,
            'is_published' => $this->isPublished,
            'is_featured' => $this->isFeatured,
            'author_id' => $this->authorId,
            'category_id' => $this->categoryId,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'meta_keywords' => $this->metaKeywords,
            'published_at' => $this->publishedAt?->format('Y-m-d H:i:s'),
            'scheduled_at' => $this->scheduledAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
