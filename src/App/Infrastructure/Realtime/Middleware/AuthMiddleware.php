<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime\Middleware;

use Toporia\Framework\Auth\Contracts\AuthManagerInterface;
use Toporia\Framework\Realtime\Middleware\ChannelMiddlewareInterface;
use Toporia\Framework\Realtime\Contracts\ConnectionInterface;

/**
 * Authentication Middleware for Realtime Channels
 *
 * Verifies that the WebSocket/Socket.IO connection is authenticated
 * using the framework's auth system (JWT token verification).
 *
 * Authentication Flow:
 * 1. Client connects with token (query param or auth event)
 * 2. Server extracts token and verifies via AuthManager
 * 3. If valid, user data is populated into connection metadata
 * 4. This middleware checks if connection has valid user_id
 *
 * Usage in routes/channels.php:
 *   ChannelRoute::channel('private-chat', fn($conn) => true)
 *       ->middleware(['auth']);
 *
 * @package App\Infrastructure\Realtime\Middleware
 */
final class AuthMiddleware implements ChannelMiddlewareInterface
{
    public function __construct(
        private readonly ?AuthManagerInterface $authManager = null
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function handle(ConnectionInterface $connection, string $channelName, callable $next): bool
    {
        // Check if user is authenticated
        if (!$connection->isAuthenticated()) {
            error_log("[Auth Middleware] Denied: Connection {$connection->getId()} not authenticated for channel '{$channelName}'");
            return false;
        }

        // Optional: Additional validation with AuthManager
        if ($this->authManager !== null) {
            $userId = $connection->getUserId();

            // Validate user still exists and is active
            try {
                $guard = $this->authManager->guard('api');
                // Note: In WebSocket context, we can't use Request-based guard
                // User should already be verified during connection handshake
            } catch (\Throwable $e) {
                error_log("[Auth Middleware] Auth validation error: {$e->getMessage()}");
                // Continue if AuthManager is not available in this context
            }
        }

        // Pass to next middleware
        return $next($connection, $channelName);
    }

    /**
     * Verify JWT token and return user data.
     *
     * This method can be called during WebSocket handshake to
     * authenticate the connection before subscribing to channels.
     *
     * Supports guard-specific secrets:
     *   JWT_SECRET_API=xxx
     *   JWT_SECRET_ADMIN=xxx
     *
     * @param string $token JWT token from client
     * @param string|null $expectedGuard Expected guard (optional, validates if token has guard claim)
     * @return array|null User data or null if invalid
     */
    public static function verifyToken(string $token, ?string $expectedGuard = null): ?array
    {
        try {
            // Decode JWT (same logic as TokenGuard)
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

            // Decode payload first to check guard claim
            $payload = json_decode(
                base64_decode(strtr($payloadEncoded, '-_', '+/')),
                true
            );

            if (!is_array($payload)) {
                return null;
            }

            // Determine guard from token or expected
            $tokenGuard = $payload['guard'] ?? 'api';
            $guardToUse = $expectedGuard ?? $tokenGuard;

            // Get guard-specific secret
            $secret = self::getJwtSecretForGuard($guardToUse);
            if ($secret === null || strlen($secret) < 32) {
                error_log("[Auth Middleware] JWT_SECRET not configured or too short for guard '{$guardToUse}'");
                return null;
            }

            // Verify signature
            $expectedSignature = hash_hmac(
                'sha256',
                "$headerEncoded.$payloadEncoded",
                $secret,
                true
            );
            $expectedSignatureEncoded = rtrim(strtr(base64_encode($expectedSignature), '+/', '-_'), '=');

            if (!hash_equals($expectedSignatureEncoded, $signatureEncoded)) {
                error_log('[Auth Middleware] Invalid JWT signature');
                return null;
            }

            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                error_log('[Auth Middleware] JWT token expired');
                return null;
            }

            // Validate guard matches if expectedGuard is specified
            if ($expectedGuard !== null && $tokenGuard !== $expectedGuard) {
                error_log("[Auth Middleware] Token guard '{$tokenGuard}' does not match expected '{$expectedGuard}'");
                return null;
            }

            // Return user data
            return [
                'user_id' => $payload['sub'] ?? null,
                'username' => $payload['username'] ?? $payload['name'] ?? null,
                'email' => $payload['email'] ?? null,
                'roles' => $payload['roles'] ?? [],
                'issued_at' => $payload['iat'] ?? null,
                'expires_at' => $payload['exp'] ?? null,
                'guard' => $tokenGuard,
            ];
        } catch (\Throwable $e) {
            error_log("[Auth Middleware] Token verification failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Get JWT secret for a specific guard.
     *
     * Lookup priority:
     * 1. JWT_SECRET_{GUARD} env variable
     * 2. JWT_SECRET env variable (default)
     *
     * @param string $guardName Guard name
     * @return string|null
     */
    private static function getJwtSecretForGuard(string $guardName): ?string
    {
        $guardUpper = strtoupper($guardName);

        // 1. Guard-specific env
        $secret = $_ENV["JWT_SECRET_{$guardUpper}"] ?? getenv("JWT_SECRET_{$guardUpper}");
        if (!empty($secret) && is_string($secret)) {
            return $secret;
        }

        // 2. Default secret
        return $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: null;
    }

    /**
     * Authenticate a connection using JWT token.
     *
     * Call this method during WebSocket handshake or 'auth' event.
     *
     * Example usage in SocketIOGateway:
     *   $userData = AuthMiddleware::authenticateConnection($connection, $token);
     *   if ($userData) {
     *       $connection->setUserId($userData['user_id']);
     *   }
     *
     * @param ConnectionInterface $connection Connection to authenticate
     * @param string $token JWT token
     * @return array|null User data if authenticated, null otherwise
     */
    public static function authenticateConnection(ConnectionInterface $connection, string $token): ?array
    {
        $userData = self::verifyToken($token);

        if ($userData === null || $userData['user_id'] === null) {
            return null;
        }

        // Set user data on connection
        $connection->setUserId($userData['user_id']);
        $connection->set('token_issued_at', $userData['issued_at']);
        $connection->set('token_expires_at', $userData['expires_at']);
        $connection->set('auth_guard', $userData['guard']);

        // Optionally load full user from database
        // $user = UserModel::find($userData['user_id']);
        // if ($user) {
        //     $connection->setUser($user->toArray());
        // }

        error_log("[Auth Middleware] Connection {$connection->getId()} authenticated as user {$userData['user_id']}");

        return $userData;
    }
}
