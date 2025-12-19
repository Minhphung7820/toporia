<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Infrastructure\Repository\Admin\UserAdminRepository;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;

/**
 * User Admin Service
 *
 * Handles admin user operations with repository pattern.
 */
final class UserAdminService
{
    public function __construct(
        private readonly UserAdminRepository $userRepository
    ) {}

    /**
     * Get paginated users with filters.
     */
    public function getPaginated(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $paginator = $this->userRepository->getPaginated($filters, $perPage, $page);

        return [
            'success' => true,
            'data' => $paginator->toArray(),
            'message' => 'Users retrieved successfully',
        ];
    }

    /**
     * Get a single user.
     */
    public function getUser(int $userId): array
    {
        $user = $this->userRepository->find($userId);

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        return [
            'success' => true,
            'data' => $user->toArray(),
            'message' => 'User retrieved successfully',
        ];
    }

    /**
     * Create a new user.
     */
    public function createUser(array $data, int $adminId): array
    {
        if (empty($data['name'])) {
            return ['success' => false, 'message' => 'Name is required'];
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Valid email is required'];
        }

        if (empty($data['password']) || strlen($data['password']) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }

        if ($this->userRepository->emailExists($data['email'])) {
            return ['success' => false, 'message' => 'Email already exists'];
        }

        $user = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => hash_make($data['password']),
            'role' => $data['role'] ?? 'user',
            'email_verified_at' => isset($data['email_verified']) && $data['email_verified'] ? date('Y-m-d H:i:s') : null,
            'avatar' => $data['avatar'] ?? null,
            'bio' => $data['bio'] ?? null,
            'website' => $data['website'] ?? null,
        ]);

        AdminActivityLogModel::log(
            $adminId,
            AdminActivityLogModel::ACTION_CREATE,
            "Created user: {$user->email}",
            'User',
            $user->id
        );

        return [
            'success' => true,
            'data' => $user->toArray(),
            'message' => 'User created successfully',
        ];
    }

    /**
     * Update an existing user.
     */
    public function updateUser(int $userId, array $data, int $adminId): array
    {
        $existingUser = $this->userRepository->find($userId);

        if (!$existingUser) {
            return ['success' => false, 'message' => 'User not found'];
        }

        if (empty($data['name'])) {
            return ['success' => false, 'message' => 'Name is required'];
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Valid email is required'];
        }

        if ($data['email'] !== $existingUser->email && $this->userRepository->emailExists($data['email'], $userId)) {
            return ['success' => false, 'message' => 'Email already exists'];
        }

        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'] ?? $existingUser->role,
            'avatar' => $data['avatar'] ?? $existingUser->avatar,
            'bio' => $data['bio'] ?? $existingUser->bio,
            'website' => $data['website'] ?? $existingUser->website,
        ];

        if (!empty($data['password'])) {
            if (strlen($data['password']) < 8) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters'];
            }
            $updateData['password'] = hash_make($data['password']);
        }

        if (isset($data['email_verified'])) {
            if ($data['email_verified'] && !$existingUser->email_verified_at) {
                $updateData['email_verified_at'] = date('Y-m-d H:i:s');
            } elseif (!$data['email_verified']) {
                $updateData['email_verified_at'] = null;
            }
        }

        $user = $this->userRepository->update($userId, $updateData);

        AdminActivityLogModel::log(
            $adminId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Updated user: {$user->email}",
            'User',
            $userId
        );

        return [
            'success' => true,
            'data' => $user->toArray(),
            'message' => 'User updated successfully',
        ];
    }

    /**
     * Delete a user.
     */
    public function deleteUser(int $userId, int $adminId): array
    {
        if ($userId === $adminId) {
            return ['success' => false, 'message' => 'Cannot delete your own account'];
        }

        $user = $this->userRepository->find($userId);

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $this->userRepository->delete($userId);

        AdminActivityLogModel::log(
            $adminId,
            AdminActivityLogModel::ACTION_DELETE,
            "Deleted user: {$user->email}",
            'User',
            $userId
        );

        return [
            'success' => true,
            'message' => 'User deleted successfully',
        ];
    }

    /**
     * Change user role.
     */
    public function changeRole(int $userId, string $role, int $adminId): array
    {
        if ($userId === $adminId && $role !== 'admin') {
            return ['success' => false, 'message' => 'Cannot change your own role'];
        }

        $user = $this->userRepository->find($userId);

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $validRoles = ['user', 'moderator', 'admin'];
        if (!in_array($role, $validRoles)) {
            return ['success' => false, 'message' => 'Invalid role'];
        }

        $this->userRepository->changeRole($userId, $role);

        AdminActivityLogModel::log(
            $adminId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Changed role to {$role} for user: {$user->email}",
            'User',
            $userId
        );

        return [
            'success' => true,
            'data' => ['role' => $role],
            'message' => 'User role updated successfully',
        ];
    }

    /**
     * Get user statistics.
     */
    public function getStatistics(): array
    {
        return [
            'success' => true,
            'data' => $this->userRepository->getStatistics(),
            'message' => 'Statistics retrieved successfully',
        ];
    }
}
