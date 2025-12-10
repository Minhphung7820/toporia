<?php

declare(strict_types=1);

namespace Toporia\Framework\Realtime\Auth;

/**
 * Connection Authenticator
 *
 * Authenticates WebSocket connections using JWT tokens or session cookies.
 *
 * Supports multiple authentication methods:
 * 1. JWT from query string: ws://host:6001?token=xxx
 * 2. JWT from Authorization header: Authorization: Bearer xxx
 * 3. Session from cookie: Cookie: session_id=xxx
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Realtime
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
final class ConnectionAuthenticator
{
    /**
     * @param mixed $session Session manager (optional)
     */
    public function __construct(
        private readonly mixed $session = null
    ) {}

    /**
     * Authenticate connection from Swoole HTTP request.
     *
     * Tries multiple authentication methods in order:
     * 1. JWT from query string (?token=xxx)
     * 2. JWT from Authorization header
     * 3. Session from cookie
     *
     * @param \Swoole\Http\Request $request Swoole request object
     * @return array{user_id: int, username: string|null, roles: array<string>, authenticated_at: int}|null
     */
    public function authenticateFromRequest(\Swoole\Http\Request $request): ?array
    {
        // Method 1: JWT from query string (ws://host:6001?token=xxx)
        if ($token = $request->get['token'] ?? null) {
            return $this->authenticateJWT($token);
        }

        // Method 2: JWT from Authorization header (WebSocket headers during handshake)
        if ($auth = $request->header['authorization'] ?? null) {
            $token = str_replace('Bearer ', '', $auth);
            return $this->authenticateJWT($token);
        }

        // Method 3: Session from cookie
        if ($sessionId = $request->cookie['session_id'] ?? null) {
            return $this->authenticateSession($sessionId);
        }

        return null;
    }

    /**
     * Authenticate using JWT token.
     *
     * @param string $token JWT token
     * @return array{user_id: int, username: string|null, roles: array<string>, authenticated_at: int}|null
     */
    public function authenticateJWT(string $token): ?array
    {
        try {
            // Simple JWT decode (without verification for now)
            // TODO: Use proper JWT service with signature verification
            $parts = explode('.', $token);

            if (count($parts) !== 3) {
                return null;
            }

            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            if (!$payload || !isset($payload['sub'])) {
                return null;
            }

            return [
                'user_id' => (int) $payload['sub'],
                'username' => $payload['name'] ?? $payload['username'] ?? null,
                'roles' => $payload['roles'] ?? [],
                'authenticated_at' => time(),
            ];
        } catch (\Throwable $e) {
            error_log("JWT authentication failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Authenticate using session ID.
     *
     * @param string $sessionId Session ID from cookie
     * @return array{user_id: int, username: string|null, roles: array<string>, authenticated_at: int}|null
     */
    public function authenticateSession(string $sessionId): ?array
    {
        if ($this->session === null) {
            return null;
        }

        try {
            // Try to get session (duck typing - works with any session implementation)
            if (method_exists($this->session, 'get')) {
                $session = $this->session->get($sessionId);

                if ($session === null) {
                    return null;
                }

                // Check if session has required methods
                if (method_exists($session, 'isExpired') && $session->isExpired()) {
                    return null;
                }

                $userId = method_exists($session, 'getUserId') ? $session->getUserId() : null;

                if ($userId === null) {
                    return null;
                }

                return [
                    'user_id' => $userId,
                    'username' => method_exists($session, 'get') ? $session->get('username') : null,
                    'roles' => method_exists($session, 'get') ? $session->get('roles', []) : [],
                    'authenticated_at' => time(),
                ];
            }

            return null;
        } catch (\Throwable $e) {
            error_log("Session authentication failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Authenticate from token string (used in 'auth' message).
     *
     * @param string $token Token string
     * @return array{user_id: int, username: string|null, roles: array<string>, authenticated_at: int}|null
     */
    public function authenticateToken(string $token): ?array
    {
        return $this->authenticateJWT($token);
    }
}
