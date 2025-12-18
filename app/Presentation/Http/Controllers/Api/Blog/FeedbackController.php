<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Blog;

use App\Application\Services\Blog\FeedbackService;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Support\Accessors\Auth;

/**
 * Feedback Controller - Public feedback endpoints.
 */
final class FeedbackController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response,
        private readonly FeedbackService $feedbackService
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Submit new feedback.
     *
     * POST /api/feedback
     */
    public function store(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $data['user_agent'] = $request->header('User-Agent');

        $userId = Auth::check() ? Auth::user()->getAuthIdentifier() : null;
        $ipAddress = $request->getClientIp();

        $result = $this->feedbackService->submitFeedback($data, $userId, $ipAddress);

        if (!$result['success']) {
            return $this->json($result, 422);
        }

        return $this->json($result, 201);
    }

    /**
     * Get current user's feedback submissions.
     *
     * GET /api/feedback/my
     */
    public function myFeedback(Request $request): JsonResponseInterface
    {
        if (!Auth::check()) {
            return $this->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $userId = Auth::user()->getAuthIdentifier();
        $page = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 10), 50);

        $result = $this->feedbackService->getUserFeedback($userId, $page, $perPage);

        return $this->json($result);
    }

    /**
     * Get feedback status.
     *
     * GET /api/feedback/{id}/status
     */
    public function status(int $id): JsonResponseInterface
    {
        $userId = Auth::check() ? Auth::user()->getAuthIdentifier() : null;

        $result = $this->feedbackService->getFeedbackStatus($id, $userId);

        if (!$result['success']) {
            return $this->json($result, $result['message'] === 'Access denied' ? 403 : 404);
        }

        return $this->json($result);
    }
}
