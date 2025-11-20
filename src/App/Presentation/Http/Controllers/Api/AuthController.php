<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api;

use App\Application\Services\{
    RegisterUserService,
    LoginService,
    ForgotPasswordService,
    ResetPasswordService,
    ChangePasswordService
};
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Support\Accessors\Auth;

/**
 * Authentication Controller for API
 *
 * Handles authentication endpoints using Application Services.
 * Follows Clean Architecture - Controller delegates to Services.
 */
final class AuthController extends BaseController
{
    public function __construct(
        private readonly RegisterUserService $registerService,
        private readonly LoginService $loginService,
        private readonly ForgotPasswordService $forgotPasswordService,
        private readonly ResetPasswordService $resetPasswordService,
        private readonly ChangePasswordService $changePasswordService
    ) {}

    /**
     * Register new user.
     *
     * POST /api/auth/register
     */
    public function register(Request $request): void
    {
        $data = $request->json();
        $result = $this->registerService->execute($data);

        if ($result['success']) {
            $this->json([
                'success' => true,
                'message' => $result['message'],
                'user' => [
                    'id' => $result['user']->getAuthIdentifier(),
                    'email' => $result['user']->email,
                    'name' => $result['user']->name,
                ]
            ], 201);
        } else {
            $this->json([
                'success' => false,
                'message' => $result['message'],
                'errors' => $result['errors'] ?? []
            ], 422);
        }
    }

    /**
     * Login user and set HttpOnly cookie.
     *
     * POST /api/auth/login
     */
    public function login(Request $request): void
    {
        $credentials = $request->json();
        $result = $this->loginService->execute($credentials);

        if ($result['success']) {
            $this->json([
                'success' => true,
                'message' => $result['message'],
                'user' => [
                    'id' => $result['user']->getAuthIdentifier(),
                    'email' => $result['user']->email,
                    'name' => $result['user']->name,
                ]
            ], 200);
        } else {
            $this->json([
                'success' => false,
                'message' => $result['message']
            ], 401);
        }
    }

    /**
     * Get current authenticated user.
     *
     * GET /api/auth/user
     */
    public function user(): void
    {
        if (!Auth::check()) {
            $this->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
            return;
        }

        $user = Auth::user();

        $this->json([
            'success' => true,
            'user' => [
                'id' => $user->getAuthIdentifier(),
                'email' => $user->email,
                'name' => $user->name,
            ]
        ]);
    }

    /**
     * Logout user and clear HttpOnly cookie.
     *
     * POST /api/auth/logout
     */
    public function logout(): void
    {
        Auth::logout();

        $this->json([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }

    /**
     * Refresh session (regenerate session ID).
     *
     * POST /api/auth/refresh
     */
    public function refresh(): void
    {
        if (!Auth::check()) {
            $this->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
            return;
        }

        // Regenerate session ID (security best practice)
        session_regenerate_id(true);

        $this->json([
            'success' => true,
            'message' => 'Session refreshed'
        ]);
    }

    /**
     * Send password reset link.
     *
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(Request $request): void
    {
        $data = $request->json();
        $email = $data['email'] ?? '';

        $result = $this->forgotPasswordService->execute($email);

        $statusCode = $result['success'] ? 200 : 422;
        $this->json($result, $statusCode);
    }

    /**
     * Reset password with token.
     *
     * POST /api/auth/reset-password
     */
    public function resetPassword(Request $request): void
    {
        $data = $request->json();
        $result = $this->resetPasswordService->execute($data);

        $statusCode = $result['success'] ? 200 : ($result['message'] === 'Invalid or expired token' ? 400 : 422);
        $this->json($result, $statusCode);
    }

    /**
     * Change password (for authenticated users).
     *
     * POST /api/auth/change-password
     */
    public function changePassword(Request $request): void
    {
        if (!Auth::check()) {
            $this->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
            return;
        }

        $data = $request->json();
        $user = Auth::user();

        // Type check - ensure it's our User entity
        if (!$user instanceof \App\Domain\Entities\User) {
            $this->json([
                'success' => false,
                'message' => 'Invalid user type'
            ], 500);
            return;
        }

        $result = $this->changePasswordService->execute($user, $data);

        $statusCode = $result['success'] ? 200 : 422;
        $this->json($result, $statusCode);
    }
}
