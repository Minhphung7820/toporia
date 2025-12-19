<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api;

use App\Application\Services\{
    RegisterUserService,
    LoginService,
    ForgotPasswordService,
    ResetPasswordService,
    ChangePasswordService,
    UpdateProfileService,
    EmailVerificationService
};
use App\Domain\Contracts\Repository\UserRepository;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Session\Store;
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
        Request $request,
        Response $response,
        private readonly RegisterUserService $registerService,
        private readonly LoginService $loginService,
        private readonly ForgotPasswordService $forgotPasswordService,
        private readonly ResetPasswordService $resetPasswordService,
        private readonly ChangePasswordService $changePasswordService,
        private readonly UpdateProfileService $updateProfileService,
        private readonly EmailVerificationService $emailVerificationService,
        private readonly UserRepository $userRepository,
        private readonly Store $session
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Register new user.
     *
     * POST /api/auth/register
     */
    public function register(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $result = $this->registerService->execute($data);

        if ($result['success']) {
            $response = [
                'success' => true,
                'message' => $result['message'],
                'requires_verification' => $result['requires_verification'] ?? false,
            ];

            // Include verification URL in development for easy testing
            if (env('APP_ENV') !== 'production' && isset($result['verification_url'])) {
                $response['verification_url'] = $result['verification_url'];
            }

            return $this->json($response, 201);
        }

        return $this->json([
            'success' => false,
            'message' => $result['message'],
            'errors' => $result['errors'] ?? []
        ], 422);
    }

    /**
     * Login user and set HttpOnly cookie.
     *
     * POST /api/auth/login
     */
    public function login(Request $request): JsonResponseInterface
    {
        $credentials = $request->json();
        $result = $this->loginService->execute($credentials);

        if ($result['success']) {
            return $this->json([
                'success' => true,
                'message' => $result['message'],
                'user' => $this->formatUserResponse($result['user'])
            ], 200);
        }

        // Handle unverified email case
        if (!empty($result['requires_verification'])) {
            return $this->json([
                'success' => false,
                'message' => $result['message'],
                'requires_verification' => true,
                'email' => $result['email'] ?? null,
            ], 403);
        }

        return $this->json([
            'success' => false,
            'message' => $result['message']
        ], 401);
    }

    /**
     * Get current authenticated user.
     *
     * GET /api/auth/user
     */
    public function user(): JsonResponseInterface
    {
        if (!Auth::check()) {
            return $this->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $user = Auth::user();

        return $this->json([
            'success' => true,
            'user' => $this->formatUserResponse($user)
        ]);
    }

    /**
     * Logout user and clear HttpOnly cookie.
     *
     * POST /api/auth/logout
     */
    public function logout(): JsonResponseInterface
    {
        Auth::logout();

        return $this->json([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }

    /**
     * Refresh session (regenerate session ID).
     *
     * POST /api/auth/refresh
     */
    public function refresh(): JsonResponseInterface
    {
        if (!Auth::check()) {
            return $this->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Regenerate session ID using framework's Store (security best practice)
        $this->session->regenerate(true);

        return $this->json([
            'success' => true,
            'message' => 'Session refreshed'
        ]);
    }

    /**
     * Send password reset link.
     *
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $email = $data['email'] ?? '';

        $result = $this->forgotPasswordService->execute($email);

        $statusCode = $result['success'] ? 200 : 422;
        return $this->json($result, $statusCode);
    }

    /**
     * Reset password with token.
     *
     * POST /api/auth/reset-password
     */
    public function resetPassword(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $result = $this->resetPasswordService->execute($data);

        $statusCode = $result['success'] ? 200 : ($result['message'] === 'Invalid or expired token' ? 400 : 422);
        return $this->json($result, $statusCode);
    }

    /**
     * Change password (for authenticated users).
     *
     * POST /api/auth/change-password
     */
    public function changePassword(Request $request): JsonResponseInterface
    {
        if (!Auth::check()) {
            return $this->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $data = $request->json();
        $user = Auth::user();

        // Type check - ensure it's our User entity
        if (!$user instanceof \App\Domain\Entities\User) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid user type'
            ], 500);
        }

        $result = $this->changePasswordService->execute($user, $data);

        $statusCode = $result['success'] ? 200 : 422;
        return $this->json($result, $statusCode);
    }

    /**
     * Update profile (for authenticated users).
     *
     * PUT /api/auth/profile
     */
    public function updateProfile(Request $request): JsonResponseInterface
    {
        if (!Auth::check()) {
            return $this->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $data = $request->json();
        $user = Auth::user();

        // Type check - ensure it's our User entity
        if (!$user instanceof \App\Domain\Entities\User) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid user type'
            ], 500);
        }

        $result = $this->updateProfileService->execute($user, $data);

        if ($result['success']) {
            return $this->json([
                'success' => true,
                'message' => $result['message'],
                'user' => $this->formatUserResponse($result['user'])
            ]);
        }

        return $this->json([
            'success' => false,
            'message' => $result['message'],
            'errors' => $result['errors'] ?? []
        ], 422);
    }

    /**
     * Update avatar (for authenticated users).
     *
     * POST /api/auth/avatar
     *
     * Uses Storage abstraction for secure file handling with:
     * - Server-side MIME type validation
     * - Size limit enforcement
     * - Unique filename generation
     * - Old avatar cleanup
     */
    public function updateAvatar(Request $request): JsonResponseInterface
    {
        if (!Auth::check()) {
            return $this->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $user = Auth::user();

        // Type check - ensure it's our User entity
        if (!$user instanceof \App\Domain\Entities\User) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid user type'
            ], 500);
        }

        // Handle file upload - now returns UploadedFile instance
        $uploadedFile = $request->file('avatar');
        if (!$uploadedFile instanceof \Toporia\Framework\Storage\UploadedFile) {
            return $this->json([
                'success' => false,
                'message' => 'No avatar file provided'
            ], 422);
        }

        // Validate file was uploaded successfully
        if (!$uploadedFile->isValid()) {
            return $this->json([
                'success' => false,
                'message' => $uploadedFile->getErrorMessage() ?? 'File upload failed'
            ], 422);
        }

        // Validate MIME type using server-side detection (more secure)
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!$uploadedFile->isValidMimeType($allowedTypes, strict: false)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP'
            ], 422);
        }

        $maxSize = 2 * 1024 * 1024; // 2MB
        if ($uploadedFile->getSize() > $maxSize) {
            return $this->json([
                'success' => false,
                'message' => 'File too large. Maximum size: 2MB'
            ], 422);
        }

        // Generate unique filename with secure extension detection
        $extension = $uploadedFile->guessExtension() ?? 'jpg';
        $filename = 'avatar_' . uniqid() . '_' . time() . '.' . $extension;
        $yearMonth = date('Y/m');
        $storagePath = "avatars/{$yearMonth}/{$filename}";

        // Store file using Storage abstraction (public disk)
        $stored = $uploadedFile->store("avatars/{$yearMonth}", $filename, 'public');

        if ($stored === false) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to save avatar'
            ], 500);
        }

        // Delete old avatar if exists and is in storage
        $storage = \Toporia\Framework\Support\Accessors\Storage::disk('public');
        if ($user->avatar) {
            // Handle both old format (/uploads/avatars/...) and new format (/storage/avatars/...)
            $oldPath = null;
            if (str_starts_with($user->avatar, '/storage/')) {
                $oldPath = ltrim(str_replace('/storage/', '', $user->avatar), '/');
            } elseif (str_starts_with($user->avatar, '/uploads/avatars/')) {
                // Legacy: delete from public folder directly
                $legacyPath = public_path(ltrim($user->avatar, '/'));
                if (file_exists($legacyPath)) {
                    @unlink($legacyPath);
                }
            }

            if ($oldPath && $storage->exists($oldPath)) {
                $storage->delete($oldPath);
            }
        }

        // Build public URL
        $avatarUrl = $storage->url($storagePath);

        // Update user
        $result = $this->updateProfileService->updateAvatar($user, $avatarUrl);

        return $this->json([
            'success' => true,
            'message' => $result['message'],
            'user' => $this->formatUserResponse($result['user']),
            'avatar_url' => $avatarUrl
        ]);
    }

    /**
     * Remove avatar (for authenticated users).
     *
     * DELETE /api/auth/avatar
     *
     * Removes avatar file from storage and updates user record.
     */
    public function removeAvatar(): JsonResponseInterface
    {
        if (!Auth::check()) {
            return $this->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $user = Auth::user();

        // Type check
        if (!$user instanceof \App\Domain\Entities\User) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid user type'
            ], 500);
        }

        // Delete old avatar file if exists
        if ($user->avatar) {
            $storage = \Toporia\Framework\Support\Accessors\Storage::disk('public');

            // Handle both old format (/uploads/avatars/...) and new format (/storage/avatars/...)
            if (str_starts_with($user->avatar, '/storage/')) {
                $oldPath = ltrim(str_replace('/storage/', '', $user->avatar), '/');
                if ($storage->exists($oldPath)) {
                    $storage->delete($oldPath);
                }
            } elseif (str_starts_with($user->avatar, '/uploads/avatars/')) {
                // Legacy: delete from public folder directly
                $legacyPath = public_path(ltrim($user->avatar, '/'));
                if (file_exists($legacyPath)) {
                    @unlink($legacyPath);
                }
            }
        }

        // Update user with null avatar
        $result = $this->updateProfileService->updateAvatar($user, null);

        return $this->json([
            'success' => true,
            'message' => 'Avatar removed successfully',
            'user' => $this->formatUserResponse($result['user'])
        ]);
    }

    /**
     * Verify email with token.
     *
     * GET /api/auth/verify-email/{id}/{hash}
     */
    public function verifyEmail(Request $request, int $id, string $hash): JsonResponseInterface
    {
        $expires = (int) $request->get('expires', 0);

        $result = $this->emailVerificationService->verify($id, $hash, $expires);

        if ($result['success']) {
            return $this->json([
                'success' => true,
                'message' => $result['message'],
            ]);
        }

        $statusCode = match ($result['error'] ?? 'unknown') {
            'expired' => 410, // Gone
            'invalid' => 400, // Bad Request
            'not_found' => 404,
            'already_verified' => 200,
            default => 400,
        };

        return $this->json([
            'success' => $result['error'] === 'already_verified',
            'message' => $result['message'],
            'error' => $result['error'] ?? 'unknown',
        ], $statusCode);
    }

    /**
     * Resend verification email.
     *
     * POST /api/auth/resend-verification
     */
    public function resendVerification(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $email = $data['email'] ?? '';

        if (empty($email)) {
            return $this->json([
                'success' => false,
                'message' => 'Email is required',
            ], 422);
        }

        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            // Don't reveal if email exists - return success anyway
            return $this->json([
                'success' => true,
                'message' => 'If your email is registered, you will receive a verification link shortly.',
            ]);
        }

        if ($user->emailVerifiedAt !== null) {
            return $this->json([
                'success' => true,
                'message' => 'Your email is already verified. You can log in.',
            ]);
        }

        $result = $this->emailVerificationService->sendVerificationEmail($user);

        $response = [
            'success' => true,
            'message' => 'Verification email sent. Please check your inbox.',
        ];

        // Include verification URL in development for easy testing
        if (env('APP_ENV') !== 'production' && isset($result['verification_url'])) {
            $response['verification_url'] = $result['verification_url'];
        }

        return $this->json($response);
    }

    /**
     * Format user data for API response.
     *
     * Returns essential user info including role and avatar for FE auth checks.
     *
     * @param mixed $user User entity
     * @return array<string, mixed>
     */
    private function formatUserResponse(mixed $user): array
    {
        return [
            'id' => $user->getAuthIdentifier(),
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'avatar' => $user->avatar,
            'bio' => $user->bio,
            'website' => $user->website,
            'email_verified_at' => $user->emailVerifiedAt?->format('Y-m-d H:i:s'),
            'created_at' => $user->createdAt?->format('Y-m-d H:i:s'),
        ];
    }
}
