<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Contracts\Repository\UserRepository;
use Toporia\Framework\Support\Accessors\Auth;

/**
 * Login Service
 *
 * Application Service for user authentication.
 */
final class LoginService
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {}

    /**
     * Attempt to login user.
     *
     * @param array{email: string, password: string, remember?: bool} $credentials
     * @return array{success: bool, user?: \App\Domain\Entities\User, message: string, requires_verification?: bool, email?: string}
     */
    public function execute(array $credentials): array
    {
        // Validate input
        if (empty($credentials['email']) || empty($credentials['password'])) {
            return [
                'success' => false,
                'message' => 'Email and password are required'
            ];
        }

        // Check if user exists and verify email status BEFORE authentication
        $user = $this->userRepository->findByEmail($credentials['email']);

        if ($user !== null && $user->emailVerifiedAt === null) {
            // Check if password is correct first (don't reveal if email exists without correct password)
            if ($user->verifyPassword($credentials['password'])) {
                return [
                    'success' => false,
                    'message' => 'Please verify your email address before logging in.',
                    'requires_verification' => true,
                    'email' => $user->email,
                ];
            }
        }

        // Attempt authentication
        $authenticated = Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'remember' => $credentials['remember'] ?? false
        ]);

        if (!$authenticated) {
            return [
                'success' => false,
                'message' => 'Invalid credentials'
            ];
        }

        $user = Auth::user();

        return [
            'success' => true,
            'user' => $user,
            'message' => 'Login successful'
        ];
    }
}
