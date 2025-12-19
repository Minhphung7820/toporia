<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Contracts\Repository\UserRepository;
use App\Domain\Entities\User;

/**
 * Update Profile Service
 *
 * Handles profile updates for authenticated users.
 * Allows updating name, bio, website, and avatar.
 * Email cannot be changed for security reasons.
 */
final class UpdateProfileService
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {}

    /**
     * Update profile for authenticated user.
     *
     * @param User $user Current authenticated user
     * @param array{name?: string, bio?: string, website?: string, avatar?: string} $data
     * @return array{success: bool, message: string, user?: User, errors?: array<string, string>}
     */
    public function execute(User $user, array $data): array
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

        // Build updated user with new values
        $updatedUser = new User(
            id: $user->id,
            name: $data['name'] ?? $user->name,
            email: $user->email, // Email cannot be changed
            password: $user->password,
            role: $user->role,
            emailVerifiedAt: $user->emailVerifiedAt,
            avatar: $data['avatar'] ?? $user->avatar,
            bio: array_key_exists('bio', $data) ? ($data['bio'] ?: null) : $user->bio,
            website: array_key_exists('website', $data) ? ($data['website'] ?: null) : $user->website,
            rememberToken: $user->rememberToken,
            createdAt: $user->createdAt,
            updatedAt: new \DateTimeImmutable()
        );

        $savedUser = $this->userRepository->save($updatedUser);

        return [
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => $savedUser
        ];
    }

    /**
     * Update avatar for authenticated user.
     *
     * @param User $user Current authenticated user
     * @param string|null $avatarPath Path to avatar image
     * @return array{success: bool, message: string, user?: User}
     */
    public function updateAvatar(User $user, ?string $avatarPath): array
    {
        $updatedUser = new User(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            password: $user->password,
            role: $user->role,
            emailVerifiedAt: $user->emailVerifiedAt,
            avatar: $avatarPath,
            bio: $user->bio,
            website: $user->website,
            rememberToken: $user->rememberToken,
            createdAt: $user->createdAt,
            updatedAt: new \DateTimeImmutable()
        );

        $savedUser = $this->userRepository->save($updatedUser);

        return [
            'success' => true,
            'message' => 'Avatar updated successfully',
            'user' => $savedUser
        ];
    }

    /**
     * Validate profile update data.
     *
     * @param array $data
     * @return array<string, string>
     */
    private function validate(array $data): array
    {
        $errors = [];

        if (isset($data['name'])) {
            $name = trim($data['name']);
            if (empty($name)) {
                $errors['name'] = 'Name is required';
            } elseif (strlen($name) < 2) {
                $errors['name'] = 'Name must be at least 2 characters';
            } elseif (strlen($name) > 100) {
                $errors['name'] = 'Name must not exceed 100 characters';
            }
        }

        if (isset($data['bio']) && strlen($data['bio']) > 500) {
            $errors['bio'] = 'Bio must not exceed 500 characters';
        }

        if (isset($data['website']) && !empty($data['website'])) {
            $website = $data['website'];
            // Add https:// if no protocol specified
            if (!preg_match('/^https?:\/\//', $website)) {
                $website = 'https://' . $website;
            }
            if (!filter_var($website, FILTER_VALIDATE_URL)) {
                $errors['website'] = 'Please enter a valid website URL';
            }
        }

        return $errors;
    }
}
