<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Contracts\Repository\UserRepository;
use App\Infrastructure\Jobs\SendPasswordResetEmailJob;
use Toporia\Framework\Database\Contracts\ConnectionInterface;

/**
 * Forgot Password Service
 *
 * Handles password reset request.
 * Generates token and sends email via queue.
 */
final class ForgotPasswordService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ConnectionInterface $db
    ) {}

    /**
     * Send password reset link.
     *
     * @param string $email
     * @return array{success: bool, message: string}
     */
    public function execute(string $email): array
    {
        // Validate email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Valid email is required'
            ];
        }

        // Check if user exists
        $user = $this->userRepository->findByEmail($email);

        // Always return success to prevent email enumeration
        // But only send email if user exists
        if ($user === null) {
            return [
                'success' => true,
                'message' => 'If the email exists, a password reset link has been sent'
            ];
        }

        // Generate reset token
        $token = bin2hex(random_bytes(32));

        // Store token in password_resets table
        $this->db->query(
            "INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE token = ?, created_at = NOW()",
            [$email, $token, $token]
        );

        // Queue email job (async)
        dispatch(new SendPasswordResetEmailJob($email, $token));

        return [
            'success' => true,
            'message' => 'If the email exists, a password reset link has been sent'
        ];
    }
}

