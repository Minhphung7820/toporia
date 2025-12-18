<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Contracts\Repository\CommentRepository;
use App\Domain\Entities\Comment;
use App\Infrastructure\Persistence\Models\CommentModel;

/**
 * PDO Comment Repository Implementation
 *
 * Uses Toporia ORM Model for database persistence.
 * Maps between domain Comment entity and database CommentModel.
 */
final class PdoCommentRepository implements CommentRepository
{
    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Comment
    {
        $model = CommentModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByPost(int $postId, bool $approvedOnly = true): array
    {
        $query = CommentModel::query()
            ->where('commentable_type', 'Post')
            ->where('commentable_id', $postId)
            ->whereNull('parent_id');

        if ($approvedOnly) {
            $query->where('is_approved', true);
        }

        $models = $query->orderBy('created_at', 'desc')->get();

        return $models->map(fn(CommentModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findReplies(int $parentId, bool $approvedOnly = true): array
    {
        $query = CommentModel::query()
            ->where('parent_id', $parentId);

        if ($approvedOnly) {
            $query->where('is_approved', true);
        }

        $models = $query->orderBy('created_at', 'asc')->get();

        return $models->map(fn(CommentModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findPending(int $limit = 20, int $offset = 0): array
    {
        $models = CommentModel::pending()
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findApproved(int $limit = 20, int $offset = 0): array
    {
        $models = CommentModel::approved()
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findByUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $models = CommentModel::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn(CommentModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findRecent(int $limit = 10): array
    {
        $models = CommentModel::recent($limit)->get();

        return $models->map(fn($model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function findWithFilters(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $query = CommentModel::query();

        if (isset($filters['is_approved'])) {
            $query->where('is_approved', $filters['is_approved']);
        }

        if (isset($filters['commentable_type'])) {
            $query->where('commentable_type', $filters['commentable_type']);
        }

        if (isset($filters['commentable_id'])) {
            $query->where('commentable_id', $filters['commentable_id']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['is_root'])) {
            if ($filters['is_root']) {
                $query->whereNull('parent_id');
            } else {
                $query->whereNotNull('parent_id');
            }
        }

        $models = $query
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn(CommentModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function save(Comment $comment): Comment
    {
        $data = [
            'content' => $comment->content,
            'commentable_type' => $comment->commentableType,
            'commentable_id' => $comment->commentableId,
            'user_id' => $comment->userId,
            'parent_id' => $comment->parentId,
            'author_name' => $comment->authorName,
            'author_email' => $comment->authorEmail,
            'author_ip' => $comment->authorIp,
            'is_approved' => $comment->isApproved,
            'likes_count' => $comment->likesCount,
            'depth' => $comment->depth,
        ];

        if ($comment->id === null) {
            // Create new comment
            $model = CommentModel::create($data);

            return $comment->withId($model->id);
        }

        // Update existing comment
        CommentModel::where('id', $comment->id)->update($data);

        return $comment;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Comment $comment): bool
    {
        if ($comment->id === null) {
            return false;
        }

        // Delete replies first
        CommentModel::where('parent_id', $comment->id)->delete();

        return CommentModel::destroy($comment->id) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function approve(int $commentId): bool
    {
        return CommentModel::where('id', $commentId)->update(['is_approved' => true]) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function reject(int $commentId): bool
    {
        return CommentModel::where('id', $commentId)->update(['is_approved' => false]) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function bulkApprove(array $ids): int
    {
        return CommentModel::whereIn('id', $ids)->update(['is_approved' => true]);
    }

    /**
     * {@inheritdoc}
     */
    public function bulkReject(array $ids): int
    {
        return CommentModel::whereIn('id', $ids)->update(['is_approved' => false]);
    }

    /**
     * {@inheritdoc}
     */
    public function bulkDelete(array $ids): int
    {
        // Delete replies first
        CommentModel::whereIn('parent_id', $ids)->delete();

        return CommentModel::whereIn('id', $ids)->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function incrementLikes(int $commentId): void
    {
        CommentModel::where('id', $commentId)->increment('likes_count');
    }

    /**
     * {@inheritdoc}
     */
    public function countPending(): int
    {
        return CommentModel::pending()->count();
    }

    /**
     * {@inheritdoc}
     */
    public function countApproved(): int
    {
        return CommentModel::approved()->count();
    }

    /**
     * {@inheritdoc}
     */
    public function countAll(): int
    {
        return CommentModel::count();
    }

    /**
     * {@inheritdoc}
     */
    public function countByPost(int $postId, bool $approvedOnly = true): int
    {
        $query = CommentModel::query()
            ->where('commentable_type', 'Post')
            ->where('commentable_id', $postId);

        if ($approvedOnly) {
            $query->where('is_approved', true);
        }

        return $query->count();
    }

    /**
     * Map database model to domain entity.
     */
    private function toDomain(CommentModel $model): Comment
    {
        return new Comment(
            id: $model->id,
            content: $model->content,
            commentableType: $model->commentable_type ?? 'Post',
            commentableId: $model->commentable_id ?? 0,
            userId: $model->user_id,
            parentId: $model->parent_id,
            authorName: $model->author_name,
            authorEmail: $model->author_email,
            authorIp: $model->author_ip,
            isApproved: $model->is_approved ?? false,
            likesCount: $model->likes_count ?? 0,
            depth: $model->depth ?? 0,
            createdAt: $model->created_at ? new \DateTimeImmutable($model->created_at) : null,
            updatedAt: $model->updated_at ? new \DateTimeImmutable($model->updated_at) : null
        );
    }
}
