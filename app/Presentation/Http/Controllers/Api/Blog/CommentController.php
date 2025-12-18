<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Blog;

use App\Application\Services\Blog\CommentService;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Support\Accessors\Auth;

/**
 * Comment Controller - Public comment endpoints.
 */
final class CommentController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response,
        private readonly CommentService $commentService
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Get comments for a post.
     *
     * GET /api/blog/posts/{postId}/comments
     */
    public function index(int $postId): JsonResponseInterface
    {
        $result = $this->commentService->getPostComments($postId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Create a new comment.
     *
     * POST /api/blog/posts/{postId}/comments
     */
    public function store(int $postId, Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $data['post_id'] = $postId;

        $userId = Auth::check() ? Auth::user()->getAuthIdentifier() : null;
        $ipAddress = $request->ip();

        $result = $this->commentService->createComment($data, $userId, $ipAddress);

        if (!$result['success']) {
            return $this->json($result, 422);
        }

        return $this->json($result, 201);
    }

    /**
     * Reply to a comment.
     *
     * POST /api/blog/comments/{commentId}/reply
     */
    public function reply(int $commentId, Request $request): JsonResponseInterface
    {
        $data = $request->json();

        $userId = Auth::check() ? Auth::user()->getAuthIdentifier() : null;
        $ipAddress = $request->ip();

        $result = $this->commentService->createReply($commentId, $data, $userId, $ipAddress);

        if (!$result['success']) {
            return $this->json($result, 422);
        }

        return $this->json($result, 201);
    }

    /**
     * Like a comment.
     *
     * POST /api/blog/comments/{commentId}/like
     */
    public function like(int $commentId): JsonResponseInterface
    {
        $result = $this->commentService->likeComment($commentId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }
}
