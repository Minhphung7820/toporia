<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Passwords;

use Toporia\Framework\Auth\Passwords\Contracts\CanResetPasswordInterface;
use Toporia\Framework\Auth\Passwords\Contracts\TokenRepositoryInterface;
use Toporia\Framework\Database\Connection;
use Toporia\Framework\Hashing\Contracts\HasherInterface;

/**
 * Database Token Repository
 *
 * Stores password reset tokens in the database.
 *
 * Performance:
 * - Indexed lookups for token validation
 * - Automatic cleanup of expired tokens
 * - Secure token hashing with bcrypt
 *
 * Table schema:
 * ```sql
 * CREATE TABLE password_reset_tokens (
 *     email VARCHAR(255) PRIMARY KEY,
 *     token VARCHAR(255) NOT NULL,
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 * );
 * ```
 */
class DatabaseTokenRepository implements TokenRepositoryInterface
{
    /**
     * The database connection.
     *
     * @var Connection
     */
    protected Connection $connection;

    /**
     * The hasher instance.
     *
     * @var HasherInterface
     */
    protected HasherInterface $hasher;

    /**
     * The token table name.
     *
     * @var string
     */
    protected string $table;

    /**
     * The hashing key.
     *
     * @var string
     */
    protected string $hashKey;

    /**
     * Token expiration in seconds.
     *
     * @var int
     */
    protected int $expires;

    /**
     * Throttle time in seconds.
     *
     * @var int
     */
    protected int $throttle;

    /**
     * Create a new database token repository.
     *
     * @param Connection $connection
     * @param HasherInterface $hasher
     * @param string $table
     * @param string $hashKey
     * @param int $expires Token expiration in seconds (default: 60 minutes)
     * @param int $throttle Throttle time in seconds (default: 60 seconds)
     */
    public function __construct(
        Connection $connection,
        HasherInterface $hasher,
        string $table,
        string $hashKey,
        int $expires = 3600,
        int $throttle = 60
    ) {
        $this->connection = $connection;
        $this->hasher = $hasher;
        $this->table = $table;
        $this->hashKey = $hashKey;
        $this->expires = $expires;
        $this->throttle = $throttle;
    }

    /**
     * Create a new token record.
     *
     * @param CanResetPasswordInterface $user
     * @return string
     */
    public function create(CanResetPasswordInterface $user): string
    {
        $email = $user->getEmailForPasswordReset();

        $this->deleteExisting($user);

        $token = $this->createNewToken();

        $this->getTable()->insert([
            'email' => $email,
            'token' => $this->hasher->make($token),
            'created_at' => now()->toDateTimeString(),
        ]);

        return $token;
    }

    /**
     * Create a new token string.
     *
     * @return string
     */
    protected function createNewToken(): string
    {
        return hash_hmac('sha256', bin2hex(random_bytes(32)), $this->hashKey);
    }

    /**
     * Delete all existing reset tokens for user.
     *
     * @param CanResetPasswordInterface $user
     * @return void
     */
    protected function deleteExisting(CanResetPasswordInterface $user): void
    {
        $this->getTable()
            ->where('email', '=', $user->getEmailForPasswordReset())
            ->delete();
    }

    /**
     * Determine if a token record exists and is valid.
     *
     * @param CanResetPasswordInterface $user
     * @param string $token
     * @return bool
     */
    public function exists(CanResetPasswordInterface $user, string $token): bool
    {
        $record = $this->getTable()
            ->where('email', '=', $user->getEmailForPasswordReset())
            ->first();

        if ($record === null) {
            return false;
        }

        return !$this->tokenExpired($record['created_at'])
            && $this->hasher->check($token, $record['token']);
    }

    /**
     * Determine if the token has expired.
     *
     * @param string $createdAt
     * @return bool
     */
    protected function tokenExpired(string $createdAt): bool
    {
        $createdTime = \Toporia\Framework\DateTime\Chronos::parse($createdAt)->getTimestamp();

        return ($createdTime + $this->expires) < now()->getTimestamp();
    }

    /**
     * Determine if a token was recently created.
     *
     * @param CanResetPasswordInterface $user
     * @return bool
     */
    public function recentlyCreatedToken(CanResetPasswordInterface $user): bool
    {
        $record = $this->getTable()
            ->where('email', '=', $user->getEmailForPasswordReset())
            ->first();

        if ($record === null) {
            return false;
        }

        $createdTime = \Toporia\Framework\DateTime\Chronos::parse($record['created_at'])->getTimestamp();

        return ($createdTime + $this->throttle) > now()->getTimestamp();
    }

    /**
     * Delete a token record.
     *
     * @param CanResetPasswordInterface $user
     * @return void
     */
    public function delete(CanResetPasswordInterface $user): void
    {
        $this->deleteExisting($user);
    }

    /**
     * Delete expired tokens.
     *
     * @return void
     */
    public function deleteExpired(): void
    {
        $expiredAt = now()->subSeconds($this->expires)->toDateTimeString();

        $this->getTable()
            ->where('created_at', '<', $expiredAt)
            ->delete();
    }

    /**
     * Get the query builder for the password reset table.
     *
     * @return \Toporia\Framework\Database\Query\QueryBuilder
     */
    protected function getTable(): \Toporia\Framework\Database\Query\QueryBuilder
    {
        return $this->connection->table($this->table);
    }
}
