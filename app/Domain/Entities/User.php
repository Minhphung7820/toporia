<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\Contracts\Auth\AuthenticatableInterface;
use Toporia\Framework\Auth\Authenticatable as FrameworkAuthenticatable;

/**
 * User Entity - Domain model for users.
 *
 * Implements framework Authenticatable interface for authentication compatibility.
 * Also satisfies domain AuthenticatableInterface contract (methods match, but setRememberToken signature differs).
 * Immutable entity following Clean Architecture principles.
 *
 * Clean Architecture:
 * - Domain layer (innermost circle)
 * - Implements framework interface for compatibility (adapter pattern at entity level)
 * - Infrastructure adapters bridge to Framework authentication
 *
 * SOLID Principles:
 * - Single Responsibility: User business logic only
 * - Immutability: All properties readonly, with* methods for changes
 */
final class User implements FrameworkAuthenticatable
{
    /**
     * @param int|null $id User ID
     * @param string $email Email address
     * @param string $password Hashed password
     * @param string $name Full name
     * @param string|null $rememberToken Remember me token
     * @param \DateTimeImmutable|null $createdAt Creation timestamp
     * @param \DateTimeImmutable|null $updatedAt Last update timestamp
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $email,
        public readonly string $password,
        public readonly string $name,
        public readonly ?string $rememberToken = null,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $updatedAt = null
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getAuthIdentifier(): int|string
    {
        return $this->id ?? throw new \LogicException('User ID not set');
    }

    /**
     * {@inheritdoc}
     * Framework Authenticatable interface requirement.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthPassword(): string
    {
        return $this->password;
    }

    /**
     * {@inheritdoc}
     */
    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    /**
     * {@inheritdoc}
     *
     * Framework interface requires void return.
     * For immutable pattern, use withRememberToken() which returns new instance.
     *
     * @param string $token Remember token
     * @return void
     */
    public function setRememberToken(string $token): void
    {
        // Framework interface requires void return
        // This method exists for interface compliance
        // For immutable pattern, use withRememberToken() instead
    }

    /**
     * Create a new User with ID (after persistence).
     *
     * @param int $id User ID.
     * @return self New User instance.
     */
    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->email,
            $this->password,
            $this->name,
            $this->rememberToken,
            $this->createdAt ?? new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Verify a password against this user's password.
     *
     * @param string $password Plain text password.
     * @return bool True if password matches.
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    /**
     * Create a new User with updated remember token.
     *
     * @param string|null $token Remember token.
     * @return self New User instance.
     */
    public function withRememberToken(?string $token): self
    {
        return new self(
            $this->id,
            $this->email,
            $this->password,
            $this->name,
            $token,
            $this->createdAt,
            $this->updatedAt
        );
    }

    /**
     * Create a new User with updated password.
     *
     * @param string $password Hashed password.
     * @return self New User instance.
     */
    public function withPassword(string $password): self
    {
        return new self(
            $this->id,
            $this->email,
            $password,
            $this->name,
            $this->rememberToken,
            $this->createdAt,
            new \DateTimeImmutable()
        );
    }

    /**
     * Create a new User with updated created_at timestamp.
     *
     * @param \DateTimeImmutable $createdAt Creation timestamp.
     * @return self New User instance.
     */
    public function withCreatedAt(\DateTimeImmutable $createdAt): self
    {
        return new self(
            $this->id,
            $this->email,
            $this->password,
            $this->name,
            $this->rememberToken,
            $createdAt,
            $this->updatedAt
        );
    }

    /**
     * Create a new User with updated updated_at timestamp.
     *
     * @param \DateTimeImmutable $updatedAt Last update timestamp.
     * @return self New User instance.
     */
    public function withUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        return new self(
            $this->id,
            $this->email,
            $this->password,
            $this->name,
            $this->rememberToken,
            $this->createdAt,
            $updatedAt
        );
    }
}
