<?php

declare(strict_types=1);

namespace App\Application\Services\Blog;

use App\Domain\Contracts\Repository\TagRepository;

/**
 * Tag Service - Handles tag operations for the public site.
 *
 * Following Service pattern with consistent return format.
 */
final class TagService
{
    public function __construct(
        private readonly TagRepository $tagRepository
    ) {}

    /**
     * Get all tags.
     *
     * @return array{success: bool, data?: array, message: string}
     */
    public function getAllTags(): array
    {
        $tags = $this->tagRepository->findAll();

        return [
            'success' => true,
            'data' => [
                'tags' => array_map(fn($tag) => $tag->toArray(), $tags),
            ],
            'message' => 'Tags retrieved successfully',
        ];
    }

    /**
     * Get popular tags.
     *
     * @param int $limit Number of tags
     * @return array{success: bool, data?: array, message: string}
     */
    public function getPopularTags(int $limit = 20): array
    {
        $tags = $this->tagRepository->findPopular($limit);

        return [
            'success' => true,
            'data' => [
                'tags' => array_map(fn($tag) => $tag->toArray(), $tags),
            ],
            'message' => 'Popular tags retrieved successfully',
        ];
    }

    /**
     * Get tag by slug.
     *
     * @param string $slug Tag slug
     * @return array{success: bool, data?: array, message: string}
     */
    public function getTagBySlug(string $slug): array
    {
        $tag = $this->tagRepository->findBySlug($slug);

        if (!$tag) {
            return [
                'success' => false,
                'message' => 'Tag not found',
            ];
        }

        return [
            'success' => true,
            'data' => [
                'tag' => $tag->toArray(),
            ],
            'message' => 'Tag retrieved successfully',
        ];
    }

    /**
     * Get tags for tag cloud.
     *
     * @param int $limit Number of tags
     * @return array{success: bool, data?: array, message: string}
     */
    public function getTagCloud(int $limit = 50): array
    {
        $tags = $this->tagRepository->findPopular($limit);

        // Calculate tag weights for cloud display
        if (empty($tags)) {
            return [
                'success' => true,
                'data' => ['tags' => []],
                'message' => 'Tag cloud retrieved successfully',
            ];
        }

        $maxCount = max(array_map(fn($tag) => $tag->usageCount, $tags));
        $minCount = min(array_map(fn($tag) => $tag->usageCount, $tags));
        $range = max(1, $maxCount - $minCount);

        $cloudTags = [];
        foreach ($tags as $tag) {
            $weight = 1 + (($tag->usageCount - $minCount) / $range) * 4; // 1-5 scale
            $cloudTags[] = [
                'tag' => $tag->toArray(),
                'weight' => round($weight, 1),
            ];
        }

        return [
            'success' => true,
            'data' => [
                'tags' => $cloudTags,
            ],
            'message' => 'Tag cloud retrieved successfully',
        ];
    }

    /**
     * Search tags by name.
     *
     * @param string $query Search query
     * @param int $limit Number of results
     * @return array{success: bool, data?: array, message: string}
     */
    public function searchTags(string $query, int $limit = 10): array
    {
        if (strlen(trim($query)) < 1) {
            return [
                'success' => false,
                'message' => 'Search query too short',
            ];
        }

        $tags = $this->tagRepository->search($query, $limit);

        return [
            'success' => true,
            'data' => [
                'query' => $query,
                'tags' => array_map(fn($tag) => $tag->toArray(), $tags),
            ],
            'message' => 'Tags found successfully',
        ];
    }
}
