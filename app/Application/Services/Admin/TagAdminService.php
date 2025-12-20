<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Infrastructure\Repository\Admin\TagAdminRepository;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;
use Toporia\Framework\Support\Str;

/**
 * Tag Admin Service
 *
 * Handles admin tag operations with repository pattern.
 */
final class TagAdminService
{
    public function __construct(
        private readonly TagAdminRepository $tagRepository
    ) {}

    /**
     * Get paginated tags with filters.
     */
    public function getPaginated(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $paginator = $this->tagRepository->getPaginated($filters, $perPage, $page);

        return [
            'success' => true,
            'data' => $paginator->toArray(),
            'message' => 'Tags retrieved successfully',
        ];
    }

    /**
     * Get a single tag.
     */
    public function getTag(int $tagId): array
    {
        $tag = $this->tagRepository->find($tagId);

        if (!$tag) {
            return ['success' => false, 'message' => 'Tag not found'];
        }

        return [
            'success' => true,
            'data' => $tag->toArray(),
            'message' => 'Tag retrieved successfully',
        ];
    }

    /**
     * Create a new tag.
     */
    public function createTag(array $data, int $userId): array
    {
        if (empty($data['name'])) {
            return ['success' => false, 'message' => 'Name is required'];
        }

        $slug = $data['slug'] ?? Str::slug($data['name']);

        if ($this->tagRepository->slugExists($slug)) {
            return ['success' => false, 'message' => 'A tag with this name already exists'];
        }

        $tag = $this->tagRepository->create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? '#6366f1',
        ]);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_CREATE,
            "Created tag: {$tag->name}",
            'Tag',
            $tag->id
        );

        return [
            'success' => true,
            'data' => $tag->toArray(),
            'message' => 'Tag created successfully',
        ];
    }

    /**
     * Update an existing tag.
     */
    public function updateTag(int $tagId, array $data, int $userId): array
    {
        $existingTag = $this->tagRepository->find($tagId);

        if (!$existingTag) {
            return ['success' => false, 'message' => 'Tag not found'];
        }

        if (empty($data['name'])) {
            return ['success' => false, 'message' => 'Name is required'];
        }

        $slug = $data['slug'] ?? $existingTag->slug;
        if ($slug !== $existingTag->slug && $this->tagRepository->slugExists($slug, $tagId)) {
            return ['success' => false, 'message' => 'A tag with this slug already exists'];
        }

        $tag = $this->tagRepository->update($tagId, [
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? $existingTag->description,
            'color' => $data['color'] ?? $existingTag->color,
        ]);

        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Updated tag: {$tag->name}",
            'Tag',
            $tagId
        );

        return [
            'success' => true,
            'data' => $tag->toArray(),
            'message' => 'Tag updated successfully',
        ];
    }

    /**
     * Delete a tag.
     */
    public function deleteTag(int $tagId, int $userId): array
    {
        $tag = $this->tagRepository->find($tagId);

        if (!$tag) {
            return ['success' => false, 'message' => 'Tag not found'];
        }

        $this->tagRepository->delete($tagId);

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
     */
    public function bulkDelete(array $ids, int $userId): array
    {
        if (empty($ids)) {
            return ['success' => false, 'message' => 'No tags selected'];
        }

        $count = $this->tagRepository->deleteWhere(['id' => $ids]);

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
     * Get tag statistics.
     */
    public function getStatistics(): array
    {
        return [
            'success' => true,
            'data' => $this->tagRepository->getStatistics(),
            'message' => 'Statistics retrieved successfully',
        ];
    }

    /**
     * Clean up unused tags.
     */
    public function cleanupUnused(int $userId): array
    {
        $unusedTags = $this->tagRepository->findUnused();
        $count = count($unusedTags);

        if ($count > 0) {
            $ids = array_column($unusedTags, 'id');
            $this->tagRepository->deleteWhere(['id' => $ids]);
        }

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

    /**
     * Merge multiple tags into a target tag.
     *
     * All posts tagged with source tags will be re-tagged with target tag.
     * Source tags will be deleted after merge.
     *
     * @param int $targetTagId The tag to merge into (will be kept)
     * @param array<int> $sourceTagIds Tags to merge from (will be deleted)
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function mergeTags(int $targetTagId, array $sourceTagIds, int $userId): array
    {
        // Validate target tag exists
        $targetTag = $this->tagRepository->find($targetTagId);
        if (!$targetTag) {
            return ['success' => false, 'message' => 'Target tag not found'];
        }

        // Filter out target from sources
        $sourceTagIds = array_filter($sourceTagIds, fn($id) => $id !== $targetTagId);

        if (empty($sourceTagIds)) {
            return ['success' => false, 'message' => 'No source tags to merge'];
        }

        // Validate all source tags exist
        $sourceTags = $this->tagRepository->getTagsWithCounts($sourceTagIds);
        if (count($sourceTags) !== count($sourceTagIds)) {
            return ['success' => false, 'message' => 'Some source tags not found'];
        }

        // Perform merge
        $result = $this->tagRepository->mergeTags($targetTagId, $sourceTagIds);

        // Log activity
        $sourceNames = implode(', ', array_column($sourceTags, 'name'));
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Merged tags [{$sourceNames}] into [{$targetTag->name}]"
        );

        return [
            'success' => true,
            'data' => [
                'target_tag' => $targetTag->toArray(),
                'merged_count' => $result['merged_count'],
                'deleted_tags' => $result['deleted_tags'],
            ],
            'message' => "Successfully merged {$result['deleted_tags']} tags into {$targetTag->name}",
        ];
    }
}
