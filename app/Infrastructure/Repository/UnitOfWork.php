<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Infrastructure\Repository\Transaction\TransactionManager;

/**
 * Unit of Work
 *
 * Coordinates changes across multiple repositories in a single transaction.
 */
final class UnitOfWork
{
    public function __construct(
        private TransactionManager $transactionManager
    ) {}

    /**
     * Execute a callback within a database transaction.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws \Throwable
     */
    public function transaction(callable $callback): mixed
    {
        $this->transactionManager->begin();

        try {
            $result = $callback();
            $this->transactionManager->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            throw $e;
        }
    }

    /**
     * Begin a transaction manually.
     */
    public function begin(): void
    {
        $this->transactionManager->begin();
    }

    /**
     * Commit the current transaction.
     */
    public function commit(): void
    {
        $this->transactionManager->commit();
    }

    /**
     * Rollback the current transaction.
     */
    public function rollback(): void
    {
        $this->transactionManager->rollback();
    }
}
