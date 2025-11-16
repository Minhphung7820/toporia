<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Domain\Entities\User;
use App\Domain\Contracts\Repository\UserRepository;
use Toporia\Framework\Auth\Contracts\AuthManagerInterface;
use Toporia\Framework\Auth\Contracts\HasApiTokensInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Authentication Controller
 *
 * Handles user authentication with multiple strategies:
 * - Web: Session-based authentication
 * - API: Sanctum-style personal access tokens
 *
 * Clean Architecture:
 * - Presentation layer controller
 * - Depends on domain repositories and framework services
 *
 * SOLID Principles:
 * - Single Responsibility: User authentication only
 * - Dependency Inversion: Depends on abstractions (interfaces)
 */
final class AuthController extends BaseController
{
    /**
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param AuthManagerInterface $auth Auth manager for multi-guard support
     * @param UserRepository $userRepository User repository for registration
     */
    public function __construct(
        Request $request,
        Response $response,
        private readonly AuthManagerInterface $auth,
        private readonly UserRepository $userRepository
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Show login form (web only).
     *
     * @return void
     */
    public function showLoginForm(): void
    {
        $this->view('auth/login', [
            'title' => 'Login',
        ]);
    }

    /**
     * Handle login request.
     *
     * Web: Returns session-based authentication
     * API: Returns Sanctum personal access token
     *
     * @return void
     */
    public function login(): void
    {
        // Validate input
        $credentials = $this->request->only(['email', 'password']);

        if (empty($credentials['email']) || empty($credentials['password'])) {
            $this->handleLoginError('Email and password are required');
            return;
        }

        // Find user
        $user = $this->userRepository->findByEmail($credentials['email']);

        if ($user === null || !password_verify($credentials['password'], $user->password)) {
            $this->handleLoginError('Invalid credentials');
            return;
        }

        // Determine authentication strategy
        if ($this->request->expectsJson()) {
            // API: Create Sanctum token
            $this->handleApiLogin($user);
        } else {
            // Web: Create session
            $this->handleWebLogin($user);
        }
    }

    /**
     * Show registration form (web only).
     *
     * @return void
     */
    public function showRegisterForm(): void
    {
        $this->view('auth/register', [
            'title' => 'Register',
        ]);
    }

    /**
     * Handle user registration.
     *
     * Supports both web and API registration.
     *
     * @return void
     */
    public function register(): void
    {
        // Validate input
        $data = $this->request->only(['name', 'email', 'password', 'password_confirmation']);

        $errors = $this->validateRegistration($data);

        if (!empty($errors)) {
            $this->handleRegistrationError($errors);
            return;
        }

        // Create user
        $user = new User(
            id: null,
            email: $data['email'],
            password: password_hash($data['password'], PASSWORD_DEFAULT),
            name: $data['name'],
            createdAt: new \DateTimeImmutable()
        );

        try {
            $savedUser = $this->userRepository->save($user);

            // Auto-login after registration
            if ($this->request->expectsJson()) {
                $this->handleApiLogin($savedUser);
            } else {
                $this->handleWebLogin($savedUser);
            }
        } catch (\Throwable $e) {
            $this->handleRegistrationError(['error' => 'Registration failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle logout request.
     *
     * Web: Destroy session
     * API: Revoke current token
     *
     * @return void
     */
    public function logout(): void
    {
        if ($this->request->expectsJson()) {
            // API: Revoke current token
            $user = $this->auth->guard('sanctum')->user();

            if ($user instanceof HasApiTokensInterface) {
                $currentToken = $user->currentAccessToken();
                if ($currentToken !== null) {
                    $currentToken->revoke();
                }
            }

            $this->response->json(['message' => 'Logged out successfully']);
        } else {
            // Web: Destroy session
            $this->auth->guard('web')->logout();
            $this->response->redirect('/login');
        }
    }

    /**
     * Get authenticated user profile.
     *
     * API endpoint to retrieve current user data.
     *
     * @return void
     */
    public function me(): void
    {
        $user = $this->auth->guard('sanctum')->user();

        if ($user === null) {
            $this->response->json(['error' => 'Unauthenticated'], 401);
            return;
        }

        if (!$user instanceof User) {
            $this->response->json(['error' => 'Invalid user type'], 500);
            return;
        }

        $this->response->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->createdAt?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Revoke a specific token.
     *
     * API endpoint to revoke a user's token by ID.
     *
     * @param int $tokenId Token ID to revoke
     * @return void
     */
    public function revokeToken(int $tokenId): void
    {
        $user = $this->auth->guard('sanctum')->user();

        if ($user === null || !$user instanceof HasApiTokensInterface) {
            $this->response->json(['error' => 'Unauthenticated'], 401);
            return;
        }

        // Find token belonging to user
        $tokens = $user->tokens();
        $token = $tokens->first(fn($t) => $t->getId() === $tokenId);

        if ($token === null) {
            $this->response->json(['error' => 'Token not found'], 404);
            return;
        }

        $token->revoke();

        $this->response->json(['message' => 'Token revoked successfully']);
    }

    /**
     * Get all tokens for the authenticated user.
     *
     * API endpoint to list user's tokens.
     *
     * @return void
     */
    public function tokens(): void
    {
        $user = $this->auth->guard('sanctum')->user();

        if ($user === null || !$user instanceof HasApiTokensInterface) {
            $this->response->json(['error' => 'Unauthenticated'], 401);
            return;
        }

        $tokens = $user->tokens()->map(function ($token) {
            return [
                'id' => $token->getId(),
                'name' => $token->getName(),
                'abilities' => $token->getAbilities(),
                'last_used_at' => $token->last_used_at ?? null,
                'expires_at' => $token->expires_at ?? null,
                'created_at' => $token->created_at ?? null,
            ];
        });

        $this->response->json(['tokens' => $tokens->all()]);
    }

    /**
     * Handle API login with Sanctum token creation.
     *
     * @param User $user Authenticated user
     * @return void
     */
    private function handleApiLogin(User $user): void
    {
        if (!$user instanceof HasApiTokensInterface) {
            $this->response->json(['error' => 'User does not support API tokens'], 500);
            return;
        }

        // Get device name from request (optional)
        $deviceName = $this->request->input('device_name') ?? 'api-client';

        // Get token abilities from request (optional)
        $abilities = $this->request->input('abilities') ?? ['*'];
        if (is_string($abilities)) {
            $abilities = explode(',', $abilities);
        }

        // Get expiration from request (optional)
        $expiresAt = null;
        if ($expiresIn = $this->request->input('expires_in')) {
            $expiresAt = new \DateTime("+{$expiresIn} seconds");
        }

        // Create token
        $newToken = $user->createToken($deviceName, $abilities, $expiresAt);

        $this->response->json([
            'token' => $newToken->getPlainTextToken(), // Only shown ONCE!
            'token_type' => 'Bearer',
            'abilities' => $abilities,
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
        ], 201);
    }

    /**
     * Handle web login with session creation.
     *
     * @param User $user Authenticated user
     * @return void
     */
    private function handleWebLogin(User $user): void
    {
        // Login via session guard
        $remember = (bool) ($this->request->input('remember') ?? false);
        $this->auth->guard('web')->login($user, $remember);

        $this->response->redirect('/dashboard');
    }

    /**
     * Validate registration data.
     *
     * @param array<string, mixed> $data Registration data
     * @return array<string, string> Validation errors
     */
    private function validateRegistration(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        }

        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        } elseif ($this->userRepository->findByEmail($data['email']) !== null) {
            $errors['email'] = 'Email already exists';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 6) {
            $errors['password'] = 'Password must be at least 6 characters';
        }

        if (empty($data['password_confirmation'])) {
            $errors['password_confirmation'] = 'Password confirmation is required';
        } elseif ($data['password'] !== $data['password_confirmation']) {
            $errors['password_confirmation'] = 'Passwords do not match';
        }

        return $errors;
    }

    /**
     * Handle login error response.
     *
     * @param string $message Error message
     * @return void
     */
    private function handleLoginError(string $message): void
    {
        if ($this->request->expectsJson()) {
            $this->response->json(['error' => $message], 401);
        } else {
            // For web, redirect back with error
            // TODO: Implement flash session messages
            $this->response->redirect('/login');
        }
    }

    /**
     * Handle registration error response.
     *
     * @param array<string, string> $errors Validation errors
     * @return void
     */
    private function handleRegistrationError(array $errors): void
    {
        if ($this->request->expectsJson()) {
            $this->response->json(['errors' => $errors], 422);
        } else {
            // For web, redirect back with errors
            // TODO: Implement flash session messages
            $this->response->redirect('/register');
        }
    }
}
