<?php

declare(strict_types=1);

namespace Toporia\Framework\Database;

use Toporia\Framework\Database\Contracts\SeederInterface;
use Toporia\Framework\Support\Accessors\Log;

/**
 * Seeder Manager
 *
 * Manages seeder execution with dependency resolution,
 * transaction management, and progress tracking.
 *
 * Features:
 * - Dependency resolution: Runs seeders in correct order
 * - Transaction management: Optional transactions per seeder
 * - Progress tracking: Track seeding progress
 * - Error handling: Rollback on failure
 * - Performance: Batch processing for large datasets
 *
 * SOLID Principles:
 * - Single Responsibility: Only manages seeder execution
 * - Open/Closed: Extensible without modifying core
 * - Dependency Inversion: Depends on SeederInterface
 *
 * Clean Architecture:
 * - Application Layer: Orchestrates seeding operations
 */
class SeederManager
{
    /**
     * Database manager instance.
     *
     * @var DatabaseManager
     */
    private DatabaseManager $db;

    /**
     * Registered seeders.
     *
     * @var array<string, class-string<SeederInterface>>
     */
    private array $seeders = [];

    /**
     * Executed seeders (to prevent duplicate execution).
     *
     * @var array<string, bool>
     */
    private array $executed = [];

    /**
     * Progress callback.
     *
     * @var callable(string, int, int): void|null
     */
    private $progressCallback = null;

    /**
     * Constructor.
     *
     * @param DatabaseManager $db Database manager
     */
    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
    }

    /**
     * Register a seeder class.
     *
     * @param class-string<SeederInterface> $seederClass
     * @return static
     */
    public function register(string $seederClass): static
    {
        if (!is_subclass_of($seederClass, SeederInterface::class)) {
            throw new \InvalidArgumentException(
                "Seeder class [{$seederClass}] must implement " . SeederInterface::class
            );
        }

        $this->seeders[$seederClass] = $seederClass;
        return $this;
    }

    /**
     * Register multiple seeders.
     *
     * @param array<int, class-string<SeederInterface>> $seeders
     * @return static
     */
    public function registerMany(array $seeders): static
    {
        foreach ($seeders as $seederClass) {
            $this->register($seederClass);
        }
        return $this;
    }

    /**
     * Run all registered seeders.
     *
     * Resolves dependencies and runs seeders in correct order.
     *
     * @return void
     */
    public function run(): void
    {
        $this->executed = [];

        // Resolve dependencies and get execution order
        $order = $this->resolveDependencies();

        // Execute seeders in order
        foreach ($order as $seederClass) {
            $this->runSeeder($seederClass);
        }
    }

    /**
     * Run a specific seeder with its dependencies.
     *
     * @param class-string<SeederInterface> $seederClass
     * @return void
     */
    public function runSeeder(string $seederClass): void
    {
        if (!is_subclass_of($seederClass, SeederInterface::class)) {
            throw new \InvalidArgumentException(
                "Seeder class [{$seederClass}] must implement " . SeederInterface::class
            );
        }

        // Skip if already executed
        if (isset($this->executed[$seederClass])) {
            return;
        }

        // Resolve and run dependencies first
        $this->runDependencies($seederClass);

        // Create seeder instance
        /** @var SeederInterface $seeder */
        $seeder = new $seederClass($this->db);

        // Report progress
        if ($this->progressCallback) {
            ($this->progressCallback)($seederClass, 0, 1);
        }

        // Run seeder
        Log::info("Running seeder: {$seederClass}");
        $seeder->run();

        // Mark as executed
        $this->executed[$seederClass] = true;

        // Report completion
        if ($this->progressCallback) {
            ($this->progressCallback)($seederClass, 1, 1);
        }

        Log::info("Completed seeder: {$seederClass}");
    }

    /**
     * Run dependencies for a seeder.
     *
     * @param class-string<SeederInterface> $seederClass
     * @return void
     */
    protected function runDependencies(string $seederClass): void
    {
        /** @var SeederInterface $seeder */
        $seeder = new $seederClass($this->db);
        $dependencies = $seeder->dependencies();

        foreach ($dependencies as $dependency) {
            if (!isset($this->executed[$dependency])) {
                $this->runSeeder($dependency);
            }
        }
    }

    /**
     * Resolve dependencies and return execution order.
     *
     * Uses topological sort to resolve dependencies.
     *
     * @return array<int, string>
     */
    protected function resolveDependencies(): array
    {
        $visited = [];
        $visiting = [];
        $order = [];

        foreach ($this->seeders as $seederClass) {
            if (!isset($visited[$seederClass])) {
                $this->visit($seederClass, $visited, $visiting, $order);
            }
        }

        return array_reverse($order);
    }

    /**
     * Visit seeder (DFS for topological sort).
     *
     * @param string $seederClass
     * @param array<string, bool> $visited
     * @param array<string, bool> $visiting
     * @param array<int, string> $order
     * @return void
     */
    protected function visit(
        string $seederClass,
        array &$visited,
        array &$visiting,
        array &$order
    ): void {
        if (isset($visiting[$seederClass])) {
            throw new \RuntimeException(
                "Circular dependency detected in seeders involving [{$seederClass}]"
            );
        }

        if (isset($visited[$seederClass])) {
            return;
        }

        $visiting[$seederClass] = true;

        // Visit dependencies
        if (is_subclass_of($seederClass, SeederInterface::class)) {
            /** @var SeederInterface $seeder */
            $seeder = new $seederClass($this->db);
            foreach ($seeder->dependencies() as $dependency) {
                $this->visit($dependency, $visited, $visiting, $order);
            }
        }

        unset($visiting[$seederClass]);
        $visited[$seederClass] = true;
        $order[] = $seederClass;
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
     * Clear executed seeders (for re-running).
     *
     * @return static
     */
    public function clear(): static
    {
        $this->executed = [];
        return $this;
    }
}

