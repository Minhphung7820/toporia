<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Transaction;

use Toporia\Framework\Database\Contracts\ConnectionInterface;

/**
 * Transaction Manager
 *
 * Manages database transactions for Unit of Work pattern.
 */
final class TransactionManager
{
    private bool $inTransaction = false;

    public function __construct(
        private ConnectionInterface $connection
    ) {}

    /**
     * Begin a database transaction.
     */
    public function begin(): void
    {
        if (!$this->inTransaction) {
            $this->connection->beginTransaction();
            $this->inTransaction = true;
        }
    }

    /**
     * Commit the current transaction.
     */
    public function commit(): void
    {
        if ($this->inTransaction) {
            $this->connection->commit();
            $this->inTransaction = false;
        }
    }

    /**
     * Rollback the current transaction.
     */
    public function rollback(): void
    {
        if ($this->inTransaction) {
            $this->connection->rollBack();
            $this->inTransaction = false;
        }
    }

    /**
     * Check if currently in a transaction.
     */
    public function isInTransaction(): bool
    {
        return $this->inTransaction;
    }
}
