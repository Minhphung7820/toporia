<?php

declare(strict_types=1);

namespace App\Application\Services;

use Toporia\Framework\Support\Accessors\Auth;

/**
 * Login Service
 *
 * Application Service for user authentication.
 */
final class LoginService
{
    /**
     * Attempt to login user.
     *
     * @param array{email: string, password: string, remember?: bool} $credentials
     * @return array{success: bool, user?: \App\Domain\Entities\User, message: string}
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
