<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Persistence\Models\PostModel;
use App\Infrastructure\Persistence\Models\CommentModel;

/**
 * Authorization service for role-based access control.
 *
 * Role hierarchy:
 * - Admin: Full system access
 * - Moderator: Manage own posts and comments on own posts
 * - User: Public site only
 */
final class AuthorizationService
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_USER = 'user';

    private const ADMIN_ROLES = [self::ROLE_ADMIN, self::ROLE_MODERATOR];

    /**
     * Check if user has admin panel access (admin or moderator).
     */
    public function canAccessAdminPanel(mixed $user): bool
    {
        return $this->hasRole($user, self::ADMIN_ROLES);
    }

    /**
     * Check if user is a full admin.
     */
    public function isAdmin(mixed $user): bool
    {
        return $this->hasRole($user, [self::ROLE_ADMIN]);
    }

    /**
     * Check if user is a moderator.
     */
    public function isModerator(mixed $user): bool
    {
        return $this->hasRole($user, [self::ROLE_MODERATOR]);
    }

    // ========== Post Permissions ==========

    /**
     * Check if user can view posts list.
     * Admin: all posts, Editor: own posts only
     */
    public function canViewPosts(mixed $user): bool
    {
        return $this->canAccessAdminPanel($user);
    }

    /**
     * Check if user can create posts.
     */
    public function canCreatePost(mixed $user): bool
    {
        return $this->canAccessAdminPanel($user);
    }

    /**
     * Check if user can view a specific post.
     */
    public function canViewPost(mixed $user, PostModel|int $post): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if ($this->isModerator($user)) {
            $postModel = $post instanceof PostModel ? $post : PostModel::find($post);
            return $postModel && $postModel->author_id === $this->getUserId($user);
        }

        return false;
    }

    /**
     * Check if user can update a specific post.
     */
    public function canUpdatePost(mixed $user, PostModel|int $post): bool
    {
        return $this->canViewPost($user, $post);
    }

    /**
     * Check if user can delete a specific post.
     */
    public function canDeletePost(mixed $user, PostModel|int $post): bool
    {
        return $this->canViewPost($user, $post);
    }

    /**
     * Check if user can publish/unpublish a post.
     */
    public function canPublishPost(mixed $user, PostModel|int $post): bool
    {
        return $this->canViewPost($user, $post);
    }

    /**
     * Check if user can toggle featured status.
     * Only admin can feature posts.
     */
    public function canFeaturePost(mixed $user): bool
    {
        return $this->isAdmin($user);
    }

    // ========== Comment Permissions ==========

    /**
     * Check if user can view comments list.
     */
    public function canViewComments(mixed $user): bool
    {
        return $this->canAccessAdminPanel($user);
    }

    /**
     * Check if user can moderate a comment (approve/reject).
     * Admin: all comments, Editor: comments on own posts only
     */
    public function canModerateComment(mixed $user, CommentModel|int $comment): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if ($this->isModerator($user)) {
            $commentModel = $comment instanceof CommentModel ? $comment : CommentModel::find($comment);
            if (!$commentModel) {
                return false;
            }

            // Check if comment is on moderator's post
            if ($commentModel->commentable_type === 'Post') {
                $post = PostModel::find($commentModel->commentable_id);
                return $post && $post->author_id === $this->getUserId($user);
            }

            return false;
        }

        return false;
    }

    /**
     * Check if user can delete a comment.
     */
    public function canDeleteComment(mixed $user, CommentModel|int $comment): bool
    {
        return $this->canModerateComment($user, $comment);
    }

    // ========== Category Permissions ==========

    /**
     * Check if user can view categories (for post creation).
     */
    public function canViewCategories(mixed $user): bool
    {
        return $this->canAccessAdminPanel($user);
    }

    /**
     * Check if user can manage categories (CRUD).
     */
    public function canManageCategories(mixed $user): bool
    {
        return $this->isAdmin($user);
    }

    // ========== Tag Permissions ==========

    /**
     * Check if user can view tags.
     */
    public function canViewTags(mixed $user): bool
    {
        return $this->canAccessAdminPanel($user);
    }

    /**
     * Check if user can manage tags (CRUD).
     */
    public function canManageTags(mixed $user): bool
    {
        return $this->isAdmin($user);
    }

    // ========== User Permissions ==========

    /**
     * Check if user can manage users.
     */
    public function canManageUsers(mixed $user): bool
    {
        return $this->isAdmin($user);
    }

    // ========== Settings Permissions ==========

    /**
     * Check if user can manage settings.
     */
    public function canManageSettings(mixed $user): bool
    {
        return $this->isAdmin($user);
    }

    // ========== Feedback Permissions ==========

    /**
     * Check if user can manage feedback.
     */
    public function canManageFeedback(mixed $user): bool
    {
        return $this->isAdmin($user);
    }

    // ========== Dashboard Permissions ==========

    /**
     * Check if user can view full dashboard (admin-level).
     */
    public function canViewFullDashboard(mixed $user): bool
    {
        return $this->isAdmin($user);
    }

    // ========== Helper Methods ==========

    /**
     * Get user ID from various user object types.
     */
    public function getUserId(mixed $user): ?int
    {
        if (method_exists($user, 'getAuthIdentifier')) {
            return $user->getAuthIdentifier();
        }

        if (property_exists($user, 'id')) {
            return $user->id;
        }

        if ($user instanceof \ArrayAccess && isset($user['id'])) {
            return $user['id'];
        }

        return null;
    }

    /**
     * Get user role from various user object types.
     */
    public function getUserRole(mixed $user): ?string
    {
        if (method_exists($user, 'getRole')) {
            return $user->getRole();
        }

        if (property_exists($user, 'role')) {
            return $user->role;
        }

        if ($user instanceof \ArrayAccess && isset($user['role'])) {
            return $user['role'];
        }

        return null;
    }

    /**
     * Check if user has one of the specified roles.
     */
    private function hasRole(mixed $user, array $roles): bool
    {
        $userRole = $this->getUserRole($user);
        return $userRole !== null && in_array($userRole, $roles, true);
    }
}
