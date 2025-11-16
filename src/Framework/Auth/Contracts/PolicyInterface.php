<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Contracts;

/**
 * Policy Interface
 *
 * Base contract for all authorization policies.
 *
 * Policies encapsulate authorization logic for a specific resource type.
 * Each method represents an action that can be performed on the resource.
 *
 * Clean Architecture:
 * - Domain layer interface
 * - Resource-specific authorization logic
 *
 * SOLID Principles:
 * - Single Responsibility: One policy per resource type
 * - Open/Closed: Extend via subclassing, closed to modification
 *
 * Example:
 * ```php
 * class PostPolicy implements PolicyInterface {
 *     public function view($user, $post): bool {
 *         return $post->published || $user->id === $post->author_id;
 *     }
 *
 *     public function update($user, $post): bool {
 *         return $user->id === $post->author_id;
 *     }
 *
 *     public function delete($user, $post): bool {
 *         return $user->isAdmin() || $user->id === $post->author_id;
 *     }
 * }
 * ```
 *
 * @package Toporia\Framework\Auth\Contracts
 */
interface PolicyInterface
{
    /**
     * Perform pre-authorization checks (runs before all policy methods).
     *
     * If this method returns a non-null value, it will be used as the
     * authorization result and no other methods will be called.
     *
     * Use cases:
     * - Grant admin users full access
     * - Block banned users from all actions
     * - Skip expensive checks for super users
     *
     * @param mixed $user Authenticated user
     * @param string $ability Ability name
     * @return bool|null True = allow, False = deny, Null = continue to ability method
     */
    public function before(mixed $user, string $ability): ?bool;
}
