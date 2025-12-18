<?php

declare(strict_types=1);

namespace App\Application\Services\Blog;

use App\Domain\Contracts\Repository\FeedbackRepository;
use App\Domain\Entities\Feedback;

/**
 * Feedback Service - Handles user feedback operations for the public site.
 *
 * Following Service pattern with consistent return format.
 */
final class FeedbackService
{
    public function __construct(
        private readonly FeedbackRepository $feedbackRepository
    ) {}

    /**
     * Submit new feedback.
     *
     * @param array $data Feedback data
     * @param int|null $userId Authenticated user ID
     * @param string|null $ipAddress Client IP address
     * @return array{success: bool, data?: array, message: string}
     */
    public function submitFeedback(array $data, ?int $userId = null, ?string $ipAddress = null): array
    {
        // Validate required fields
        if (empty($data['title'])) {
            return ['success' => false, 'message' => 'Title is required'];
        }

        if (empty($data['content'])) {
            return ['success' => false, 'message' => 'Feedback content is required'];
        }

        // For guest feedback, require email
        if ($userId === null && (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))) {
            return ['success' => false, 'message' => 'Valid email is required'];
        }

        // Validate feedback type
        $validTypes = [
            Feedback::TYPE_SUGGESTION,
            Feedback::TYPE_BUG_REPORT,
            Feedback::TYPE_QUESTION,
            Feedback::TYPE_COMPLAINT,
            Feedback::TYPE_PRAISE,
            Feedback::TYPE_OTHER,
        ];
        $type = $data['type'] ?? Feedback::TYPE_SUGGESTION;
        if (!in_array($type, $validTypes)) {
            $type = Feedback::TYPE_OTHER;
        }

        $feedback = new Feedback(
            id: null,
            title: $data['title'],
            content: strip_tags($data['content']),
            type: $type,
            status: Feedback::STATUS_PENDING,
            priority: Feedback::PRIORITY_NORMAL,
            userId: $userId,
            email: $data['email'] ?? null,
            assignedTo: null,
            adminNotes: null,
            ipAddress: $ipAddress,
            userAgent: $data['user_agent'] ?? null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $savedFeedback = $this->feedbackRepository->save($feedback);

        return [
            'success' => true,
            'data' => [
                'feedback' => [
                    'id' => $savedFeedback->id,
                    'title' => $savedFeedback->title,
                    'type' => $savedFeedback->type,
                    'status' => $savedFeedback->status,
                ],
            ],
            'message' => 'Thank you for your feedback! We will review it shortly.',
        ];
    }

    /**
     * Get user's own feedback submissions.
     *
     * @param int $userId User ID
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array{success: bool, data?: array, message: string}
     */
    public function getUserFeedback(int $userId, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $feedback = $this->feedbackRepository->findByUser($userId, $perPage, $offset);

        return [
            'success' => true,
            'data' => [
                'feedback' => array_map(fn($item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'type' => $item->type,
                    'status' => $item->status,
                    'created_at' => $item->createdAt->format('Y-m-d H:i:s'),
                ], $feedback),
            ],
            'message' => 'Feedback retrieved successfully',
        ];
    }

    /**
     * Get feedback status for public display.
     *
     * @param int $feedbackId Feedback ID
     * @param int|null $userId User ID (for verification)
     * @return array{success: bool, data?: array, message: string}
     */
    public function getFeedbackStatus(int $feedbackId, ?int $userId = null): array
    {
        $feedback = $this->feedbackRepository->findById($feedbackId);

        if (!$feedback) {
            return ['success' => false, 'message' => 'Feedback not found'];
        }

        // Only show status if user owns the feedback or is authenticated admin
        if ($feedback->userId !== null && $feedback->userId !== $userId) {
            return ['success' => false, 'message' => 'Access denied'];
        }

        return [
            'success' => true,
            'data' => [
                'id' => $feedback->id,
                'title' => $feedback->title,
                'type' => $feedback->type,
                'status' => $feedback->status,
                'status_label' => $this->getStatusLabel($feedback->status),
                'created_at' => $feedback->createdAt->format('Y-m-d H:i:s'),
            ],
            'message' => 'Feedback status retrieved successfully',
        ];
    }

    /**
     * Get human-readable status label.
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            Feedback::STATUS_PENDING => 'Pending Review',
            Feedback::STATUS_REVIEWING => 'Under Review',
            Feedback::STATUS_IN_PROGRESS => 'In Progress',
            Feedback::STATUS_RESOLVED => 'Resolved',
            Feedback::STATUS_CLOSED => 'Closed',
            Feedback::STATUS_WONT_FIX => 'Will Not Be Addressed',
            default => 'Unknown',
        };
    }
}
