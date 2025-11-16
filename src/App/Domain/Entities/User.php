<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\Contracts\Auth\AuthenticatableInterface;

/**
 * User Entity - Domain model for users.
 *
 * Pure domain entity with ZERO framework dependencies.
 * Implements domain AuthenticatableInterface for authentication.
 * Immutable entity following Clean Architecture principles.
 *
 * Clean Architecture:
 * - Domain layer (innermost circle)
 * - No dependencies on outer layers (Framework, Infrastructure)
 * - Infrastructure adapters bridge to Framework authentication
 *
 * SOLID Principles:
 * - Single Responsibility: User business logic only
 * - Immutability: All properties readonly, with* methods for changes
 */
final class User implements AuthenticatableInterface
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
     * Returns new immutable instance with updated token (Clean Architecture).
     */
    public function setRememberToken(?string $token): self
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
}
