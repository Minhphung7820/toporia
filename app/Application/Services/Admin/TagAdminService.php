<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Domain\Contracts\Repository\TagRepository;
use App\Domain\Entities\Tag;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;
use Toporia\Framework\Support\Str;

/**
 * Tag Admin Service - Handles admin tag operations.
 *
 * Following Service pattern with consistent return format.
 */
final class TagAdminService
{
    public function __construct(
        private readonly TagRepository $tagRepository
    ) {}

    /**
     * Get all tags with pagination.
     *
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $perPage Tags per page
     * @return array{success: bool, data?: array, message: string}
     */
    public function getAllTags(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $offset = ($page - 1) * $perPage;
        $tags = $this->tagRepository->findWithFilters($filters, $perPage, $offset);
        $total = $this->tagRepository->countAll();

        return [
            'success' => true,
            'data' => [
                'tags' => array_map(fn($tag) => $tag->toArray(), $tags),
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ],
            'message' => 'Tags retrieved successfully',
        ];
    }

    /**
     * Get a single tag for editing.
     *
     * @param int $tagId Tag ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function getTag(int $tagId): array
    {
        $tag = $this->tagRepository->findById($tagId);

        if (!$tag) {
            return ['success' => false, 'message' => 'Tag not found'];
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
     * Create a new tag.
     *
     * @param array $data Tag data
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function createTag(array $data, int $userId): array
    {
        // Validate required fields
        if (empty($data['name'])) {
            return ['success' => false, 'message' => 'Name is required'];
        }

        // Generate slug if not provided
        $slug = $data['slug'] ?? Str::slug($data['name']);

        // Check slug uniqueness
        if ($this->tagRepository->findBySlug($slug)) {
            return ['success' => false, 'message' => 'A tag with this name already exists'];
        }

        $tag = new Tag(
            id: null,
            name: $data['name'],
            slug: $slug,
            description: $data['description'] ?? null,
            color: $data['color'] ?? '#6366f1',
            isActive: $data['is_active'] ?? true,
            usageCount: 0,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $savedTag = $this->tagRepository->save($tag);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_CREATE,
            "Created tag: {$savedTag->name}",
            'Tag',
            $savedTag->id
        );

        return [
            'success' => true,
            'data' => ['tag' => $savedTag->toArray()],
            'message' => 'Tag created successfully',
        ];
    }

    /**
     * Update an existing tag.
     *
     * @param int $tagId Tag ID
     * @param array $data Tag data
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function updateTag(int $tagId, array $data, int $userId): array
    {
        $existingTag = $this->tagRepository->findById($tagId);

        if (!$existingTag) {
            return ['success' => false, 'message' => 'Tag not found'];
        }

        // Validate required fields
        if (empty($data['name'])) {
            return ['success' => false, 'message' => 'Name is required'];
        }

        // Handle slug update
        $slug = $data['slug'] ?? $existingTag->slug;
        if ($slug !== $existingTag->slug) {
            $existing = $this->tagRepository->findBySlug($slug);
            if ($existing && $existing->id !== $tagId) {
                return ['success' => false, 'message' => 'A tag with this slug already exists'];
            }
        }

        $updatedTag = new Tag(
            id: $tagId,
            name: $data['name'],
            slug: $slug,
            description: $data['description'] ?? $existingTag->description,
            color: $data['color'] ?? $existingTag->color,
            isActive: $data['is_active'] ?? $existingTag->isActive,
            usageCount: $existingTag->usageCount,
            createdAt: $existingTag->createdAt,
            updatedAt: new \DateTimeImmutable()
        );

        $savedTag = $this->tagRepository->save($updatedTag);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Updated tag: {$savedTag->name}",
            'Tag',
            $tagId
        );

        return [
            'success' => true,
            'data' => ['tag' => $savedTag->toArray()],
            'message' => 'Tag updated successfully',
        ];
    }

    /**
     * Delete a tag.
     *
     * @param int $tagId Tag ID
     * @param int $userId Admin user ID
     * @return array{success: bool, message: string}
     */
    public function deleteTag(int $tagId, int $userId): array
    {
        $tag = $this->tagRepository->findById($tagId);

        if (!$tag) {
            return ['success' => false, 'message' => 'Tag not found'];
        }

        $this->tagRepository->delete($tag);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_DELETE,
            "Deleted tag: {$tag->name}",
            'Tag',
            $tagId
        );

        return [
            'success' => true,
            'message' => 'Tag deleted successfully',
        ];
    }

    /**
     * Bulk delete tags.
     *
     * @param array $ids Tag IDs
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function bulkDelete(array $ids, int $userId): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => 'No tags selected'];
        }

        $count = $this->tagRepository->bulkDelete($ids);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_DELETE,
            "Bulk deleted {$count} tags"
        );

        return [
            'success' => true,
            'data' => ['count' => $count],
            'message' => "{$count} tags deleted successfully",
        ];
    }

    /**
     * Merge tags (combine into one).
     *
     * @param int $targetId Target tag ID to keep
     * @param array $sourceIds Source tag IDs to merge
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function mergeTags(int $targetId, array $sourceIds, int $userId): array
    {
        $targetTag = $this->tagRepository->findById($targetId);

        if (!$targetTag) {
            return ['success' => false, 'message' => 'Target tag not found'];
        }

        if (empty($sourceIds)) {
            return ['success' => false, 'message' => 'No source tags selected'];
        }

        // Remove target from source ids if present
        $sourceIds = array_filter($sourceIds, fn($id) => (int) $id !== $targetId);

        if (empty($sourceIds)) {
            return ['success' => false, 'message' => 'Cannot merge tag into itself'];
        }

        $mergedCount = $this->tagRepository->mergeTags($targetId, $sourceIds);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Merged {$mergedCount} tags into: {$targetTag->name}",
            'Tag',
            $targetId
        );

        return [
            'success' => true,
            'data' => [
                'target_tag' => $targetTag->toArray(),
                'merged_count' => $mergedCount,
            ],
            'message' => "{$mergedCount} tags merged successfully",
        ];
    }

    /**
     * Get tag statistics.
     *
     * @return array{success: bool, data?: array, message: string}
     */
    public function getStatistics(): array
    {
        return [
            'success' => true,
            'data' => [
                'total' => $this->tagRepository->countAll(),
                'used' => $this->tagRepository->countUsed(),
                'unused' => $this->tagRepository->countUnused(),
            ],
            'message' => 'Statistics retrieved successfully',
        ];
    }

    /**
     * Clean up unused tags.
     *
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function cleanupUnused(int $userId): array
    {
        $count = $this->tagRepository->deleteUnused();

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_DELETE,
            "Cleaned up {$count} unused tags"
        );

        return [
            'success' => true,
            'data' => ['count' => $count],
            'message' => "{$count} unused tags deleted successfully",
        ];
    }
}
