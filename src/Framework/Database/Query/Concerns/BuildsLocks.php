<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\Query\Concerns;

/**
 * Trait BuildsLocks
 *
 * Pessimistic locking builders for Query Builder.
 * Provides Laravel-compatible row locking for concurrent access control.
 *
 * Features:
 * - FOR UPDATE (exclusive lock)
 * - LOCK IN SHARE MODE (shared lock)
 * - Transaction-based locking
 *
 * Performance:
 * - Prevents race conditions in concurrent transactions
 * - Use within transactions only
 * - May cause deadlocks if not careful
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Database\Query\Concerns
 * @since       2025-01-22
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
trait BuildsLocks
{
    /**
     * Lock type for the query.
     *
     * @var string|null 'update' or 'shared'
     */
    private ?string $lock = null;

    /**
     * Add FOR UPDATE lock to the query.
     *
     * Locks selected rows for writing until the transaction commits.
     * Other transactions cannot read or write locked rows.
     *
     * Example:
     * ```php
     * DB::transaction(function() {
     *     $user = DB::table('users')
     *         ->where('id', 1)
     *         ->lockForUpdate()
     *         ->first();
     *
     *     // Update balance safely
     *     DB::table('users')
     *         ->where('id', 1)
     *         ->update(['balance' => $user['balance'] + 100]);
     * });
     * ```
     *
     * Use case:
     * - Preventing race conditions
     * - Implementing pessimistic locking
     * - Ensuring read-modify-write atomicity
     *
     * Performance:
     * - Blocks other transactions (can cause contention)
     * - Use only when necessary
     * - Keep transactions short
     *
     * IMPORTANT:
     * - Must be used within a transaction
     * - May cause deadlocks if multiple locks are acquired in different orders
     * - Not supported by all databases (fallbacks to no lock)
     *
     * @return $this
     */
    public function lockForUpdate(): self
    {
        $this->lock = 'update';
        return $this;
    }

    /**
     * Add LOCK IN SHARE MODE lock to the query.
     *
     * Locks selected rows for reading. Other transactions can read but cannot write.
     * Prevents the "read committed" isolation level anomaly.
     *
     * Example:
     * ```php
     * DB::transaction(function() {
     *     // Lock product for reading
     *     $product = DB::table('products')
     *         ->where('id', 1)
     *         ->sharedLock()
     *         ->first();
     *
     *     // Can read safely, others can also read but cannot update
     *     $canOrder = $product['stock'] > 0;
     * });
     * ```
     *
     * Use case:
     * - Ensuring consistent reads
     * - Preventing phantom reads
     * - Read-only pessimistic locking
     *
     * Performance:
     * - Less restrictive than FOR UPDATE
     * - Multiple transactions can hold shared locks
     * - Blocks writers only
     *
     * Database Support:
     * - MySQL/MariaDB: LOCK IN SHARE MODE
     * - PostgreSQL: FOR SHARE
     * - SQLite: Not supported (no-op)
     *
     * @return $this
     */
    public function sharedLock(): self
    {
        $this->lock = 'shared';
        return $this;
    }

    /**
     * Get the lock type.
     *
     * @return string|null
     */
    protected function getLock(): ?string
    {
        return $this->lock;
    }
}
