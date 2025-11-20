<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Contracts\Repository\UserRepository;
use App\Domain\Entities\User;

/**
 * Change Password Service
 *
 * Handles password change for authenticated users.
 */
final class ChangePasswordService
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {}

    /**
     * Change password for authenticated user.
     *
     * @param User $user Current authenticated user
     * @param array{current_password: string, password: string, password_confirmation: string} $data
     * @return array{success: bool, message: string, errors?: array<string, string>}
     */
    public function execute(User $user, array $data): array
    {
        // Validate input
        $errors = $this->validate($user, $data);
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors
            ];
        }

        // Update password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $updatedUser = $user->withPassword($hashedPassword);
        $this->userRepository->save($updatedUser);

        return [
            'success' => true,
            'message' => 'Password changed successfully'
        ];
    }

    /**
     * Validate change password data.
     *
     * @param User $user
     * @param array $data
     * @return array<string, string>
     */
    private function validate(User $user, array $data): array
    {
        $errors = [];

        if (empty($data['current_password'])) {
            $errors['current_password'] = 'Current password is required';
        } elseif (!$user->verifyPassword($data['current_password'])) {
            $errors['current_password'] = 'Current password is incorrect';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'New password is required';
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
