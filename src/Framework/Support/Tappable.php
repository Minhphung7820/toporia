<?php

declare(strict_types=1);

namespace Toporia\Framework\Support;

/**
 * Trait Tappable
 *
 * Provides simple tap functionality for side effects.
 * Use this when you only need tap() without full Conditionable.
 *
 * Performance:
 * - O(1) - single callback invocation
 * - No overhead when not used
 *
 * Example:
 * ```php
 * $user->fill($data)
 *     ->tap(fn($user) => Log::info("Updating user {$user->id}"))
 *     ->save();
 * ```
 */
trait Tappable
{
    /**
     * Call the given callback with this instance then return the instance.
     *
     * @param callable(static): void $callback
     * @return static
     */
    public function tap(callable $callback): static
    {
        $callback($this);

        return $this;
    }
}
