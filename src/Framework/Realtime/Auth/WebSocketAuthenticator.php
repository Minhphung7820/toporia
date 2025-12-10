<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Auth;

use Toporia\Framework\Session\Contracts\SessionInterface;

/**
 * Class WebSocketAuthenticator
 *
 * Authenticates WebSocket connections using JWT tokens or session cookies.
 * Prevents unauthorized access to realtime features.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Realtime\Auth
 * @since       2025-12-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class WebSocketAuthenticator
{
    /**
     * @param SessionInterface|null $session Session manager
     * @param string $jwtSecret JWT secret key
     */
    public function __construct(
        private readonly ?SessionInterface $session = null,
        private readonly string $jwtSecret = ''
    ) {}

    /**
     * Authenticate WebSocket connection.
     *
     * Supports multiple authentication methods:
     * 1. JWT Token from query string: ws://host?token=JWT_TOKEN
     * 2. Session cookie: PHPSESSID or custom session cookie
     * 3. Bearer token from Authorization header (if available)
     *
     * @param mixed $request Swoole request object
     * @return array|null User data if authenticated, null otherwise
     */
    public function authenticate(mixed $request): ?array
    {
        // Method 1: JWT Token from query string
        $token = $request->get['token'] ?? null;
        if ($token && !empty($this->jwtSecret)) {
            $userData = $this->verifyJWT($token);
            if ($userData) {
                return $userData;
            }
        }

        // Method 2: Session cookie
        if ($this->session !== null) {
            $sessionId = $request->cookie['PHPSESSID'] ??
                         $request->cookie['session_id'] ??
                         null;

            if ($sessionId) {
                $userData = $this->verifySession($sessionId);
                if ($userData) {
                    return $userData;
                }
            }
        }

        // Method 3: Bearer token from custom header (if WebSocket supports it)
        $authHeader = $request->header['authorization'] ?? null;
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $userData = $this->verifyJWT($token);
            if ($userData) {
                return $userData;
            }
        }

        return null; // Not authenticated
    }

    /**
     * Verify JWT token.
     *
     * @param string $token JWT token
     * @return array|null User data if valid, null otherwise
     */
    private function verifyJWT(string $token): ?array
    {
        try {
            // Simple JWT verification (for production, use proper JWT library)
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            [$header, $payload, $signature] = $parts;

            // Verify signature
            $validSignature = hash_hmac(
                'sha256',
                "{$header}.{$payload}",
                $this->jwtSecret,
                true
            );
            $validSignature = rtrim(strtr(base64_encode($validSignature), '+/', '-_'), '=');

            if (!hash_equals($validSignature, $signature)) {
                return null; // Invalid signature
            }

            // Decode payload
            $payloadData = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

            if (!$payloadData) {
                return null;
            }

            // Check expiration
            if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
                return null; // Token expired
            }

            // Return user data
            return [
                'user_id' => $payloadData['sub'] ?? $payloadData['user_id'] ?? null,
                'username' => $payloadData['name'] ?? $payloadData['username'] ?? null,
                'email' => $payloadData['email'] ?? null,
                'roles' => $payloadData['roles'] ?? [],
                'authenticated_at' => time(),
                'auth_method' => 'jwt',
            ];
        } catch (\Throwable $e) {
            error_log("JWT verification failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Verify session cookie.
     *
     * @param string $sessionId Session ID
     * @return array|null User data if valid, null otherwise
     */
    private function verifySession(string $sessionId): ?array
    {
        try {
            if ($this->session === null) {
                return null;
            }

            // Get session data
            $sessionData = $this->session->get($sessionId);

            if (!$sessionData || !is_array($sessionData)) {
                return null;
            }

            // Check if session has user data
            $userId = $sessionData['user_id'] ?? $sessionData['id'] ?? null;
            if ($userId === null) {
                return null; // Not logged in
            }

            // Return user data
            return [
                'user_id' => $userId,
                'username' => $sessionData['username'] ?? $sessionData['name'] ?? null,
                'email' => $sessionData['email'] ?? null,
                'roles' => $sessionData['roles'] ?? [],
                'authenticated_at' => time(),
                'auth_method' => 'session',
            ];
        } catch (\Throwable $e) {
            error_log("Session verification failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Check if authentication is required.
     *
     * @param array $config Realtime configuration
     * @return bool
     */
    public static function isRequired(array $config): bool
    {
        return (bool) ($config['security']['require_auth'] ?? true);
    }
}

