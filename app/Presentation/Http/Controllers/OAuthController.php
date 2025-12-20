<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use Toporia\Framework\Http\{Request, RedirectResponse};
use Toporia\Framework\Session\Store;
use Toporia\Framework\Support\Accessors\Auth;
use App\Infrastructure\Persistence\Models\UserModel;
use App\Domain\Entities\User;

/**
 * OAuth Controller
 *
 * Handles OAuth authentication via Socialite (Google, Facebook, GitHub, etc.)
 * Uses Auth facade for consistent authentication flow with AuthController.
 */
final class OAuthController
{
    public function __construct(
        private Store $session
    ) {}

    /**
     * Handle successful OAuth authentication
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function success(Request $request): RedirectResponse
    {
        // Get OAuth user data from session (set by SocialiteController)
        $socialiteUser = $this->session->get('socialite_user');
        $provider = $this->session->get('socialite_provider');

        if (!$socialiteUser) {
            return new RedirectResponse('/login');
        }

        // Find or create user
        $userModel = $this->findOrCreateUser($socialiteUser, $provider);

        // Convert ORM model to Domain entity for Auth
        $userEntity = $this->modelToEntity($userModel);

        // Use Auth facade to login (consistent with AuthController)
        Auth::login($userEntity);

        // Clear socialite session data
        $this->session->remove('socialite_user');
        $this->session->remove('socialite_provider');

        // Redirect to dashboard or intended page
        return new RedirectResponse('/');
    }

    /**
     * Handle OAuth authentication error
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function error(Request $request): RedirectResponse
    {
        $error = $this->session->get('socialite_error', 'Authentication failed');
        $this->session->remove('socialite_error');

        // Redirect back to login with error message
        $this->session->flash('error', $error);
        return new RedirectResponse('/login');
    }

    /**
     * Find or create user from OAuth data
     *
     * @param array $socialiteUser OAuth user data
     * @param string $provider OAuth provider (google, facebook, github, etc.)
     * @return UserModel
     */
    private function findOrCreateUser(array $socialiteUser, string $provider): UserModel
    {
        $email = $socialiteUser['email'] ?? null;

        if (!$email) {
            throw new \RuntimeException('Email not provided by OAuth provider');
        }

        // Check if OAuth provider verified this email
        $providerVerifiedEmail = $this->isEmailVerifiedByProvider($socialiteUser, $provider);

        // Try to find existing user by email using ORM
        $user = UserModel::where('email', $email)->first();

        if ($user) {
            $needsSave = false;

            // Update avatar if provided by OAuth
            if (isset($socialiteUser['avatar']) && $socialiteUser['avatar']) {
                $user->avatar = $socialiteUser['avatar'];
                $needsSave = true;
            }

            // Auto-verify email if:
            // 1. User hasn't verified yet
            // 2. OAuth provider confirms email is verified
            if (!$user->email_verified_at && $providerVerifiedEmail) {
                $user->email_verified_at = now()->toDateTimeString();
                $needsSave = true;
            }

            if ($needsSave) {
                $user->save();
            }

            return $user;
        }

        // Create new user using ORM
        // Only auto-verify if OAuth provider confirms email is verified
        return UserModel::create([
            'name' => $socialiteUser['name'] ?? $socialiteUser['email'],
            'email' => $email,
            'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), // Random password
            'email_verified_at' => $providerVerifiedEmail ? now()->toDateTimeString() : null,
            'avatar' => $socialiteUser['avatar'] ?? null,
            'role' => 'user',
            'is_active' => true,
        ]);
    }

    /**
     * Check if email is verified by OAuth provider.
     *
     * Different providers return email_verified in different ways:
     * - Google: 'email_verified' => true/false (boolean)
     * - GitHub: If email is returned, it's verified (GitHub only returns verified emails)
     * - Facebook: 'email' is only returned if verified
     *
     * @param array $socialiteUser OAuth user data
     * @param string $provider OAuth provider name
     * @return bool
     */
    private function isEmailVerifiedByProvider(array $socialiteUser, string $provider): bool
    {
        return match ($provider) {
            'google' => ($socialiteUser['email_verified'] ?? false) === true,
            // GitHub only returns email if it's verified and primary
            'github' => isset($socialiteUser['email']) && !empty($socialiteUser['email']),
            // Facebook only returns email if verified
            'facebook' => isset($socialiteUser['email']) && !empty($socialiteUser['email']),
            // Default: check for explicit email_verified field, otherwise false
            default => ($socialiteUser['email_verified'] ?? false) === true,
        };
    }

    /**
     * Convert ORM UserModel to Domain User entity
     *
     * @param UserModel $model ORM model
     * @return User Domain entity
     */
    private function modelToEntity(UserModel $model): User
    {
        return new User(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            password: $model->password,
            role: $model->role ?? 'user',
            emailVerifiedAt: $model->email_verified_at
                ? new \DateTimeImmutable($model->email_verified_at)
                : null,
            avatar: $model->avatar,
            bio: $model->bio,
            website: $model->website,
            rememberToken: $model->remember_token,
            createdAt: $model->created_at
                ? new \DateTimeImmutable($model->created_at)
                : null,
            updatedAt: $model->updated_at
                ? new \DateTimeImmutable($model->updated_at)
                : null,
        );
    }
}
