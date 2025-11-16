<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Transaction;

use Toporia\Framework\Database\Contracts\ConnectionInterface;
use Toporia\Framework\Support\Accessors\Log;

/**
 * Transaction Manager
 *
 * Manages database transactions across multiple repositories.
 * Implements Unit of Work pattern for atomic operations.
 *
 * SOLID Principles:
 * - Single Responsibility: Manages transactions only
 * - Open/Closed: Extensible via callbacks
 * - Dependency Inversion: Depends on ConnectionInterface
 *
 * Performance:
 * - Reduces database round-trips
 * - Ensures data consistency
 * - Supports nested transactions (savepoints)
 */
final class TransactionManager
{
    /**
     * @var array<callable> Callbacks to execute on commit
     */
    private array $onCommitCallbacks = [];

    /**
     * @var array<callable> Callbacks to execute on rollback
     */
    private array $onRollbackCallbacks = [];

    /**
     * @var int Transaction nesting level
     */
    private int $nestingLevel = 0;

    /**
     * @param ConnectionInterface $connection Database connection
     */
    public function __construct(
        private readonly ConnectionInterface $connection
    ) {}

    /**
     * Execute callback within a transaction.
     *
     * @param callable $callback Callback to execute
     * @return mixed Return value from callback
     * @throws \Throwable
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Begin transaction.
     *
     * @return void
     */
    public function beginTransaction(): void
    {
        if ($this->nestingLevel === 0) {
            $this->connection->beginTransaction();
        } else {
            // Nested transaction: use savepoint
            $savepoint = 'sp_' . $this->nestingLevel;
            $this->connection->query()->statement("SAVEPOINT {$savepoint}");
        }

        $this->nestingLevel++;
    }

    /**
     * Commit transaction.
     *
     * @return void
     */
    public function commit(): void
    {
        if ($this->nestingLevel === 0) {
            throw new \RuntimeException('No active transaction to commit');
        }

        $this->nestingLevel--;

        if ($this->nestingLevel === 0) {
            $this->connection->commit();

            // Execute commit callbacks
            foreach ($this->onCommitCallbacks as $callback) {
                try {
                    $callback();
                } catch (\Throwable $e) {
                    Log::error('Transaction commit callback failed', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            $this->onCommitCallbacks = [];
            $this->onRollbackCallbacks = [];
        }
    }

    /**
     * Rollback transaction.
     *
     * @return void
     */
    public function rollback(): void
    {
        if ($this->nestingLevel === 0) {
            throw new \RuntimeException('No active transaction to rollback');
        }

        $this->nestingLevel--;

        if ($this->nestingLevel === 0) {
            $this->connection->rollback();

            // Execute rollback callbacks
            foreach ($this->onRollbackCallbacks as $callback) {
                try {
                    $callback();
                } catch (\Throwable $e) {
                    Log::error('Transaction rollback callback failed', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            $this->onCommitCallbacks = [];
            $this->onRollbackCallbacks = [];
        } else {
            // Nested transaction: rollback to savepoint
            $savepoint = 'sp_' . $this->nestingLevel;
            $this->connection->query()->statement("ROLLBACK TO SAVEPOINT {$savepoint}");
        }
    }

    /**
     * Register callback to execute on commit.
     *
     * @param callable $callback Callback
     * @return self
     */
    public function onCommit(callable $callback): self
    {
        $this->onCommitCallbacks[] = $callback;
        return $this;
    }

    /**
     * Register callback to execute on rollback.
     *
     * @param callable $callback Callback
     * @return self
     */
    public function onRollback(callable $callback): self
    {
        $this->onRollbackCallbacks[] = $callback;
        return $this;
    }

    /**
     * Check if transaction is active.
     *
     * @return bool True if transaction is active
     */
    public function isActive(): bool
    {
        return $this->nestingLevel > 0;
    }

    /**
     * Get nesting level.
     *
     * @return int Nesting level
     */
    public function getNestingLevel(): int
    {
        return $this->nestingLevel;
    }
}
