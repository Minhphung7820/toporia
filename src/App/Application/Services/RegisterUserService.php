<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Contracts\Repository\UserRepository;
use App\Domain\Entities\User;
use Toporia\Framework\Support\Accessors\Auth;

/**
 * Register User Service
 *
 * Application Service for user registration.
 * Follows Clean Architecture - Application layer handles use cases.
 */
final class RegisterUserService
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {}

    /**
     * Register a new user.
     *
     * @param array{name: string, email: string, password: string, password_confirmation: string} $data
     * @return array{success: bool, user?: User, errors?: array<string, string>}
     */
    public function execute(array $data): array
    {
        // Validate input
        $errors = $this->validate($data);
        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
                'message' => 'Validation failed'
            ];
        }

        // Check if user already exists
        $existingUser = $this->userRepository->findByEmail($data['email']);
        if ($existingUser !== null) {
            return [
                'success' => false,
                'errors' => ['email' => 'Email already registered'],
                'message' => 'Email already registered'
            ];
        }

        // Create new user
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $user = new User(
            id: null,
            email: $data['email'],
            password: $hashedPassword,
            name: $data['name']
        );

        $savedUser = $this->userRepository->save($user);

        // Auto login after registration
        Auth::login($savedUser);

        return [
            'success' => true,
            'user' => $savedUser,
            'message' => 'Registration successful'
        ];
    }

    /**
     * Validate registration data.
     *
     * @param array $data
     * @return array<string, string>
     */
    private function validate(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        }

        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
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
