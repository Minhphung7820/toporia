<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use Toporia\Framework\Http\{Request, Response, RedirectResponse};
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
     * @param string $provider OAuth provider (google, facebook, etc.)
     * @return UserModel
     */
    private function findOrCreateUser(array $socialiteUser, string $provider): UserModel
    {
        $email = $socialiteUser['email'] ?? null;

        if (!$email) {
            throw new \RuntimeException('Email not provided by OAuth provider');
        }

        // Try to find existing user by email using ORM
        $user = UserModel::where('email', $email)->first();

        if ($user) {
            // Update avatar if provided by OAuth
            if (isset($socialiteUser['avatar']) && $socialiteUser['avatar']) {
                $user->avatar = $socialiteUser['avatar'];
                $user->save();
            }
            return $user;
        }

        // Create new user using ORM
        return UserModel::create([
            'name' => $socialiteUser['name'] ?? $socialiteUser['email'],
            'email' => $email,
            'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), // Random password
            'email_verified_at' => date('Y-m-d H:i:s'), // Auto-verify OAuth users
            'avatar' => $socialiteUser['avatar'] ?? null,
            'role' => 'user',
            'is_active' => true,
        ]);
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
            email: $model->email,
            password: $model->password,
            name: $model->name,
            rememberToken: null,
            createdAt: $model->created_at ? new \DateTimeImmutable($model->created_at) : null,
            updatedAt: $model->updated_at ? new \DateTimeImmutable($model->updated_at) : null
        );
    }
}
