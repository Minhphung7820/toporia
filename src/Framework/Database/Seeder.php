<?php

declare(strict_types=1);

namespace Toporia\Framework\Database;

use Toporia\Framework\Database\Contracts\SeederInterface;
use Toporia\Framework\Database\Contracts\FactoryInterface;
use Toporia\Framework\Database\DatabaseManager;
use Toporia\Framework\Database\ORM\Model;


/**
 * Abstract Class Seeder
 *
 * Abstract base class for Seeder implementations in the Database query
 * building and ORM layer providing common functionality and contracts.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Database
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 *
 * @internal    This class is a core component and should not be extended
 *              directly unless you know what you're doing.
 */
abstract class Seeder implements SeederInterface
{
    /**
     * Database manager instance.
     *
     * @var DatabaseManager|null
     */
    protected ?DatabaseManager $db = null;

    /**
     * Connection name to use for seeding.
     *
     * @var string|null
     */
    protected ?string $connection = null;

    /**
     * Whether to use transaction for this seeder.
     *
     * @var bool
     */
    protected bool $useTransaction = true;

    /**
     * Batch size for bulk operations.
     *
     * @var int
     */
    protected int $batchSize = 100;

    /**
     * Progress callback for tracking seeding progress.
     *
     * @var callable(string, int, int): void|null
     */
    protected $progressCallback = null;

    /**
     * Constructor.
     *
     * @param DatabaseManager|null $db Database manager (injected via container)
     */
    public function __construct(?DatabaseManager $db = null)
    {
        $this->db = $db ?? $this->resolveDatabaseManager();
    }

    /**
     * Run the database seeds.
     *
     * Wrapped in transaction if useTransaction() returns true.
     *
     * @return void
     */
    public function run(): void
    {
        if ($this->useTransaction()) {
            $this->runInTransaction();
        } else {
            $this->seed();
        }
    }

    /**
     * Execute seeding logic.
     *
     * Must be implemented by child classes.
     *
     * @return void
     */
    abstract protected function seed(): void;

    /**
     * Run seed in transaction.
     *
     * @return void
     */
    protected function runInTransaction(): void
    {
        $connection = $this->getConnection();

        try {
            $connection->beginTransaction();
            $this->seed();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Call another seeder class.
     *
     * @param class-string<Seeder> $seederClass
     * @return void
     */
    protected function call(string $seederClass): void
    {
        if (!is_subclass_of($seederClass, Seeder::class)) {
            throw new \InvalidArgumentException(
                "Seeder class [{$seederClass}] must extend " . Seeder::class
            );
        }

        /** @var Seeder $seeder */
        $seeder = new $seederClass($this->db);
        $seeder->run();
    }

    /**
     * Call multiple seeders.
     *
     * @param array<int, class-string<Seeder>> $seeders
     * @return void
     */
    protected function callMany(array $seeders): void
    {
        foreach ($seeders as $seederClass) {
            $this->call($seederClass);
        }
    }

    /**
     * Create models using factory.
     *
     * @param FactoryInterface<TModel> $factory Factory instance
     * @param int $count Number of models to create
     * @param array<string, mixed> $attributes Additional attributes
     * @return array<int, Model>
     * @template TModel of Model
     */
    protected function factory(
        FactoryInterface $factory,
        int $count = 1,
        array $attributes = []
    ): array {
        if ($count <= 0) {
            return [];
        }

        if ($count === 1) {
            return [$factory->create($attributes)];
        }

        return $factory->createMany($count, $attributes);
    }

    /**
     * Create models using factory with progress tracking.
     *
     * @param FactoryInterface<TModel> $factory Factory instance
     * @param int $count Number of models to create
     * @param array<string, mixed> $attributes Additional attributes
     * @return array<int, Model>
     * @template TModel of Model
     */
    protected function factoryWithProgress(
        FactoryInterface $factory,
        int $count,
        array $attributes = []
    ): array {
        $total = $count;
        $created = 0;
        $models = [];

        // Create in batches for progress tracking
        while ($created < $total) {
            $batchSize = min($this->batchSize, $total - $created);

            $batch = $factory->createMany($batchSize, $attributes);
            $models = array_merge($models, $batch);

            $created += $batchSize;

            // Report progress
            if ($this->progressCallback) {
                ($this->progressCallback)(
                    static::class,
                    $created,
                    $total
                );
            }
        }

        return $models;
    }

    /**
     * Insert raw data directly into database.
     *
     * More performant than using models for large datasets.
     *
     * @param string $table Table name
     * @param array<int, array<string, mixed>> $data Data to insert
     * @return void
     */
    protected function insert(string $table, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $connection = $this->getConnection();

        // Insert in batches
        foreach (array_chunk($data, $this->batchSize) as $chunk) {
            $columns = array_keys($chunk[0]);
            $placeholders = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
            $values = array_fill(0, count($chunk), $placeholders);

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES %s',
                $connection->getPdo()->quote($table),
                implode(',', array_map(fn($col) => $connection->getPdo()->quote($col), $columns)),
                implode(',', $values)
            );

            $bindings = [];
            foreach ($chunk as $row) {
                foreach ($columns as $column) {
                    $bindings[] = $row[$column] ?? null;
                }
            }

            $connection->execute($sql, $bindings);
        }
    }

    /**
     * Truncate table(s).
     *
     * @param string|array<int, string> $tables Table name(s)
     * @return void
     */
    protected function truncate(string|array $tables): void
    {
        $tables = is_array($tables) ? $tables : [$tables];
        $connection = $this->getConnection();

        // Disable foreign key checks temporarily
        $connection->execute('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($tables as $table) {
                $connection->execute("TRUNCATE TABLE {$table}");
            }
        } finally {
            // Re-enable foreign key checks
            $connection->execute('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * Get database connection.
     *
     * @return \Toporia\Framework\Database\Contracts\ConnectionInterface
     */
    protected function getConnection(): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        return $this->db->connection($this->connection);
    }

    /**
     * Set progress callback.
     *
     * @param callable(string, int, int): void $callback
     * @return static
     */
    public function setProgressCallback(callable $callback): static
    {
        $this->progressCallback = $callback;
        return $this;
    }

    /**
     * Set batch size for bulk operations.
     *
     * @param int $size
     * @return static
     */
    public function setBatchSize(int $size): static
    {
        $this->batchSize = max(1, $size);
        return $this;
    }

    /**
     * Set connection name.
     *
     * @param string|null $connection
     * @return static
     */
    public function setConnection(?string $connection): static
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Get seeder dependencies.
     *
     * @return array<string> Array of seeder class names
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * Whether to use transaction for this seeder.
     *
     * @return bool
     */
    public function useTransaction(): bool
    {
        return $this->useTransaction;
    }

    /**
     * Resolve database manager from container.
     *
     * @return DatabaseManager
     */
    protected function resolveDatabaseManager(): DatabaseManager
    {
        // Try to resolve from container
        if (function_exists('app') && app()->has('db')) {
            return app()->get('db');
        }

        // Fallback: create from config
        $config = config('database', []);
        return new DatabaseManager($config);
    }
}
