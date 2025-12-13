<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Contracts\Repository\UserRepository;
use Toporia\Framework\Database\Contracts\ConnectionInterface;

/**
 * Reset Password Service
 *
 * Handles password reset with token.
 */
final class ResetPasswordService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ConnectionInterface $db
    ) {}

    /**
     * Reset password with token.
     *
     * @param array{email: string, token: string, password: string, password_confirmation: string} $data
     * @return array{success: bool, message: string, errors?: array<string, string>}
     */
    public function execute(array $data): array
    {
        // Validate input
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors
            ];
        }

        // Verify token
        $result = $this->db->query(
            "SELECT * FROM password_resets WHERE email = ? AND token = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$data['email'], $data['token']]
        );

        if (empty($result)) {
            return [
                'success' => false,
                'message' => 'Invalid or expired token'
            ];
        }

        // Get user
        $user = $this->userRepository->findByEmail($data['email']);
        if ($user === null) {
            return [
                'success' => false,
                'message' => 'User not found'
            ];
        }

        // Update password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $updatedUser = $user->withPassword($hashedPassword);
        $this->userRepository->save($updatedUser);

        // Delete used token
        $this->db->query(
            "DELETE FROM password_resets WHERE email = ? AND token = ?",
            [$data['email'], $data['token']]
        );

        return [
            'success' => true,
            'message' => 'Password reset successful'
        ];
    }

    /**
     * Validate reset password data.
     *
     * @param array $data
     * @return array<string, string>
     */
    private function validate(array $data): array
    {
        $errors = [];

        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        }

        if (empty($data['token'])) {
            $errors['token'] = 'Token is required';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        if (empty($data['password_confirmation'])) {
            $errors['password_confirmation'] = 'Password confirmation is required';
        } elseif ($data['password'] !== $data['password_confirmation']) {
            $errors['password_confirmation'] = 'Passwords do not match';
        }

        return $errors;
    }
}

