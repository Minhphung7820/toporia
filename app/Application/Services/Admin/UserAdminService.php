<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Domain\Contracts\Repository\UserRepository;
use App\Domain\Entities\User;
use App\Infrastructure\Persistence\Models\AdminActivityLogModel;

/**
 * User Admin Service - Handles admin user management.
 *
 * Following Service pattern with consistent return format.
 */
final class UserAdminService
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {}

    /**
     * Get all users with pagination.
     *
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $perPage Users per page
     * @return array{success: bool, data?: array, message: string}
     */
    public function getAllUsers(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $users = $this->userRepository->findWithFilters($filters, $perPage, $offset);
        $total = $this->userRepository->countAll();

        return [
            'success' => true,
            'data' => [
                'users' => array_map(fn($user) => $this->formatUserForAdmin($user), $users),
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ],
            'message' => 'Users retrieved successfully',
        ];
    }

    /**
     * Get a single user for editing.
     *
     * @param int $userId User ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function getUser(int $userId): array
    {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        return [
            'success' => true,
            'data' => [
                'user' => $this->formatUserForAdmin($user),
            ],
            'message' => 'User retrieved successfully',
        ];
    }

    /**
     * Create a new user.
     *
     * @param array $data User data
     * @param int $adminId Admin user ID performing action
     * @return array{success: bool, data?: array, message: string}
     */
    public function createUser(array $data, int $adminId): array
    {
        // Validate required fields
        if (empty($data['name'])) {
            return ['success' => false, 'message' => 'Name is required'];
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Valid email is required'];
        }

        if (empty($data['password']) || strlen($data['password']) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }

        // Check email uniqueness
        if ($this->userRepository->findByEmail($data['email'])) {
            return ['success' => false, 'message' => 'Email already exists'];
        }

        $user = new User(
            id: null,
            name: $data['name'],
            email: $data['email'],
            password: hash_make($data['password']),
            role: $data['role'] ?? 'user',
            emailVerifiedAt: isset($data['email_verified']) && $data['email_verified'] ? new \DateTimeImmutable() : null,
            avatar: $data['avatar'] ?? null,
            bio: $data['bio'] ?? null,
            website: $data['website'] ?? null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $savedUser = $this->userRepository->save($user);

        // Log activity
        AdminActivityLogModel::log(
            $adminId,
            AdminActivityLogModel::ACTION_CREATE,
            "Created user: {$savedUser->email}",
            'User',
            $savedUser->id
        );

        return [
            'success' => true,
            'data' => ['user' => $this->formatUserForAdmin($savedUser)],
            'message' => 'User created successfully',
        ];
    }

    /**
     * Update an existing user.
     *
     * @param int $userId User ID to update
     * @param array $data User data
     * @param int $adminId Admin user ID performing action
     * @return array{success: bool, data?: array, message: string}
     */
    public function updateUser(int $userId, array $data, int $adminId): array
    {
        $existingUser = $this->userRepository->findById($userId);

        if (!$existingUser) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // Validate required fields
        if (empty($data['name'])) {
            return ['success' => false, 'message' => 'Name is required'];
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Valid email is required'];
        }

        // Check email uniqueness (if changed)
        if ($data['email'] !== $existingUser->email) {
            if ($this->userRepository->findByEmail($data['email'])) {
                return ['success' => false, 'message' => 'Email already exists'];
            }
        }

        // Handle password update
        $password = $existingUser->password;
        if (!empty($data['password'])) {
            if (strlen($data['password']) < 8) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters'];
            }
            $password = hash_make($data['password']);
        }

        // Handle email verification
        $emailVerifiedAt = $existingUser->emailVerifiedAt;
        if (isset($data['email_verified'])) {
            if ($data['email_verified'] && !$emailVerifiedAt) {
                $emailVerifiedAt = new \DateTimeImmutable();
            } elseif (!$data['email_verified']) {
                $emailVerifiedAt = null;
            }
        }

        $updatedUser = new User(
            id: $userId,
            name: $data['name'],
            email: $data['email'],
            password: $password,
            role: $data['role'] ?? $existingUser->role,
            emailVerifiedAt: $emailVerifiedAt,
            avatar: $data['avatar'] ?? $existingUser->avatar,
            bio: $data['bio'] ?? $existingUser->bio,
            website: $data['website'] ?? $existingUser->website,
            createdAt: $existingUser->createdAt,
            updatedAt: new \DateTimeImmutable()
        );

        $savedUser = $this->userRepository->save($updatedUser);

        // Log activity
        AdminActivityLogModel::log(
            $adminId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Updated user: {$savedUser->email}",
            'User',
            $userId
        );

        return [
            'success' => true,
            'data' => ['user' => $this->formatUserForAdmin($savedUser)],
            'message' => 'User updated successfully',
        ];
    }

    /**
     * Delete a user.
     *
     * @param int $userId User ID
     * @param int $adminId Admin user ID performing action
     * @return array{success: bool, message: string}
     */
    public function deleteUser(int $userId, int $adminId): array
    {
        // Prevent self-deletion
        if ($userId === $adminId) {
            return ['success' => false, 'message' => 'Cannot delete your own account'];
        }

        $user = $this->userRepository->findById($userId);

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $this->userRepository->delete($user);

        // Log activity
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
     *
     * @param int $userId User ID
     * @param string $role New role
     * @param int $adminId Admin user ID performing action
     * @return array{success: bool, data?: array, message: string}
     */
    public function changeRole(int $userId, string $role, int $adminId): array
    {
        // Prevent self-demotion
        if ($userId === $adminId && $role !== 'admin') {
            return ['success' => false, 'message' => 'Cannot change your own role'];
        }

        $user = $this->userRepository->findById($userId);

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $validRoles = ['user', 'editor', 'admin'];
        if (!in_array($role, $validRoles)) {
            return ['success' => false, 'message' => 'Invalid role'];
        }

        $this->userRepository->updateRole($userId, $role);

        // Log activity
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
     *
     * @return array{success: bool, data?: array, message: string}
     */
    public function getStatistics(): array
    {
        return [
            'success' => true,
            'data' => [
                'total' => $this->userRepository->countAll(),
                'verified' => $this->userRepository->countVerified(),
                'by_role' => $this->userRepository->countByRole(),
                'recent' => $this->userRepository->countRecentRegistrations(30),
            ],
            'message' => 'Statistics retrieved successfully',
        ];
    }

    /**
     * Format user data for admin display (without password).
     */
    private function formatUserForAdmin(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified' => $user->emailVerifiedAt !== null,
            'email_verified_at' => $user->emailVerifiedAt?->format('Y-m-d H:i:s'),
            'avatar' => $user->avatar,
            'bio' => $user->bio,
            'website' => $user->website,
            'created_at' => $user->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $user->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
