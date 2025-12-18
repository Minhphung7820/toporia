<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Admin;

use App\Application\Services\Admin\PostAdminService;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Support\Accessors\Auth;

/**
 * Post Admin Controller - Admin post management endpoints.
 */
final class PostAdminController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response,
        private readonly PostAdminService $postAdminService
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Get all posts with filters.
     *
     * GET /api/admin/posts
     */
    public function index(Request $request): JsonResponseInterface
    {
        $filters = [
            'status' => $request->query('status'),
            'category_id' => $request->query('category_id'),
            'author_id' => $request->query('author_id'),
            'search' => $request->query('search'),
        ];
        $filters = array_filter($filters);

        $page = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 20), 100);

        $result = $this->postAdminService->getAllPosts($filters, $page, $perPage);

        return $this->json($result);
    }

    /**
     * Get a single post for editing.
     *
     * GET /api/admin/posts/{id}
     */
    public function show(int $id): JsonResponseInterface
    {
        $result = $this->postAdminService->getPost($id);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Create a new post.
     *
     * POST /api/admin/posts
     */
    public function store(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $authorId = Auth::user()->getAuthIdentifier();

        $result = $this->postAdminService->createPost($data, $authorId);

        if (!$result['success']) {
            return $this->json($result, 422);
        }

        return $this->json($result, 201);
    }

    /**
     * Update an existing post.
     *
     * PUT /api/admin/posts/{id}
     */
    public function update(int $id, Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->postAdminService->updatePost($id, $data, $userId);

        if (!$result['success']) {
            $statusCode = $result['message'] === 'Post not found' ? 404 : 422;
            return $this->json($result, $statusCode);
        }

        return $this->json($result);
    }

    /**
     * Delete a post.
     *
     * DELETE /api/admin/posts/{id}
     */
    public function destroy(int $id): JsonResponseInterface
    {
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->postAdminService->deletePost($id, $userId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Publish a post.
     *
     * POST /api/admin/posts/{id}/publish
     */
    public function publish(int $id): JsonResponseInterface
    {
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->postAdminService->publishPost($id, $userId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Unpublish a post.
     *
     * POST /api/admin/posts/{id}/unpublish
     */
    public function unpublish(int $id): JsonResponseInterface
    {
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->postAdminService->unpublishPost($id, $userId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Schedule a post.
     *
     * POST /api/admin/posts/{id}/schedule
     */
    public function schedule(int $id, Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $scheduledAt = $data['scheduled_at'] ?? '';
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->postAdminService->schedulePost($id, $scheduledAt, $userId);

        if (!$result['success']) {
            $statusCode = $result['message'] === 'Post not found' ? 404 : 422;
            return $this->json($result, $statusCode);
        }

        return $this->json($result);
    }

    /**
     * Toggle featured status.
     *
     * POST /api/admin/posts/{id}/toggle-featured
     */
    public function toggleFeatured(int $id): JsonResponseInterface
    {
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->postAdminService->toggleFeatured($id, $userId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }
}
