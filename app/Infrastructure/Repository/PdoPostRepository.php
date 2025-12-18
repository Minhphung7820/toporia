<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Contracts\Repository\PostRepository;
use App\Domain\Entities\Post;
use App\Infrastructure\Persistence\Models\PostModel;

/**
 * PDO Post Repository Implementation
 *
 * Uses Toporia ORM Model for database persistence.
 * Maps between domain Post entity and database PostModel.
 */
final class PdoPostRepository implements PostRepository
{
    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Post
    {
        $model = PostModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findBySlug(string $slug): ?Post
    {
        $model = PostModel::where('slug', $slug)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findPublished(int $limit = 10, int $offset = 0): array
    {
        $models = PostModel::published()
            ->orderBy('published_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findFeatured(int $limit = 5): array
    {
        $models = PostModel::featured()
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findMostViewed(int $limit = 10): array
    {
        $models = PostModel::mostViewed($limit)->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findLatest(int $limit = 10): array
    {
        $models = PostModel::latest($limit)->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findByCategory(int $categoryId, int $limit = 10, int $offset = 0): array
    {
        $models = PostModel::byCategory($categoryId)
            ->orderBy('published_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findByTag(int $tagId, int $limit = 10, int $offset = 0): array
    {
        $models = PostModel::published()
            ->whereHas('tags', function ($query) use ($tagId) {
                $query->where('tags.id', $tagId);
            })
            ->orderBy('published_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findByAuthor(int $authorId, int $limit = 10, int $offset = 0): array
    {
        $models = PostModel::byAuthor($authorId)
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findRelated(int $postId, int $limit = 5): array
    {
        $post = PostModel::find($postId);
        if (!$post) {
            return [];
        }

        // Find related posts by same category or shared tags
        $models = PostModel::published()
            ->where('id', '!=', $postId)
            ->where(function ($query) use ($post) {
                // Same category
                if ($post->category_id) {
                    $query->orWhere('category_id', $post->category_id);
                }
            })
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $query, int $limit = 10, int $offset = 0): array
    {
        $searchTerm = '%' . $query . '%';

        $models = PostModel::published()
            ->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', $searchTerm)
                    ->orWhere('content', 'LIKE', $searchTerm)
                    ->orWhere('excerpt', 'LIKE', $searchTerm);
            })
            ->orderBy('published_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findWithFilters(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $query = PostModel::query();

        if (isset($filters['status'])) {
            match ($filters['status']) {
                'published' => $query->where('is_published', true),
                'draft' => $query->where('is_published', false)->whereNull('scheduled_at'),
                'scheduled' => $query->where('is_published', false)->whereNotNull('scheduled_at'),
                default => null,
            };
        }

        if (isset($filters['is_featured'])) {
            $query->where('is_featured', $filters['is_featured']);
        }

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['author_id'])) {
            $query->where('author_id', $filters['author_id']);
        }

        if (isset($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', $searchTerm)
                    ->orWhere('content', 'LIKE', $searchTerm);
            });
        }

        $models = $query
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn(PostModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findScheduledReady(): array
    {
        $models = PostModel::scheduledReady()->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function save(Post $post): Post
    {
        $data = [
            'title' => $post->title,
            'slug' => $post->slug,
            'content' => $post->content,
            'excerpt' => $post->excerpt,
            'featured_image' => $post->featuredImage,
            'views' => $post->views,
            'reading_time' => $post->readingTime,
            'is_published' => $post->isPublished,
            'is_featured' => $post->isFeatured,
            'author_id' => $post->authorId,
            'category_id' => $post->categoryId,
            'meta_title' => $post->metaTitle,
            'meta_description' => $post->metaDescription,
            'meta_keywords' => $post->metaKeywords,
            'published_at' => $post->publishedAt?->format('Y-m-d H:i:s'),
            'scheduled_at' => $post->scheduledAt?->format('Y-m-d H:i:s'),
        ];

        if ($post->id === null) {
            // Create new post
            $model = PostModel::create($data);

            return $post->withId($model->id);
        }

        // Update existing post
        PostModel::where('id', $post->id)->update($data);

        return $post;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Post $post): bool
    {
        if ($post->id === null) {
            return false;
        }

        return PostModel::destroy($post->id) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function incrementViews(int $postId): void
    {
        PostModel::where('id', $postId)->increment('views');
    }

    /**
     * {@inheritdoc}
     */
    public function countPublished(): int
    {
        return PostModel::published()->count();
    }

    /**
     * {@inheritdoc}
     */
    public function countAll(): int
    {
        return PostModel::count();
    }

    /**
     * {@inheritdoc}
     */
    public function countByStatus(): array
    {
        return [
            'published' => PostModel::where('is_published', true)->count(),
            'draft' => PostModel::where('is_published', false)->whereNull('scheduled_at')->count(),
            'scheduled' => PostModel::where('is_published', false)->whereNotNull('scheduled_at')->count(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getTotalViews(): int
    {
        return (int) PostModel::sum('views');
    }

    /**
     * {@inheritdoc}
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->countAll(),
            'published' => $this->countPublished(),
            'by_status' => $this->countByStatus(),
            'total_views' => $this->getTotalViews(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function syncTags(int $postId, array $tagIds): void
    {
        $post = PostModel::find($postId);
        if ($post) {
            $post->tags()->sync($tagIds);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function syncCategories(int $postId, array $categoryIds): void
    {
        $post = PostModel::find($postId);
        if ($post) {
            $post->categories()->sync($categoryIds);
        }
    }

    /**
     * Map database model to domain entity.
     */
    private function toDomain(PostModel $model): Post
    {
        return new Post(
            id: $model->id,
            title: $model->title,
            slug: $model->slug ?? '',
            content: $model->content,
            excerpt: $model->excerpt,
            featuredImage: $model->featured_image,
            views: $model->views ?? 0,
            readingTime: $model->reading_time ?? 0,
            isPublished: $model->is_published ?? false,
            isFeatured: $model->is_featured ?? false,
            authorId: $model->author_id,
            categoryId: $model->category_id,
            metaTitle: $model->meta_title,
            metaDescription: $model->meta_description,
            metaKeywords: $model->meta_keywords,
            publishedAt: $model->published_at ? new \DateTimeImmutable($model->published_at) : null,
            scheduledAt: $model->scheduled_at ? new \DateTimeImmutable($model->scheduled_at) : null,
            createdAt: $model->created_at ? new \DateTimeImmutable($model->created_at) : null,
            updatedAt: $model->updated_at ? new \DateTimeImmutable($model->updated_at) : null
        );
    }
}
