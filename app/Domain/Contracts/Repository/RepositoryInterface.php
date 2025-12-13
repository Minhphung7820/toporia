<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Repository;

/**
 * Base Repository Interface
 *
 * Defines the contract for all repositories in the Domain layer.
 * This interface follows Repository Pattern and Clean Architecture principles.
 *
 * SOLID Principles:
 * - Single Responsibility: Only defines persistence contract
 * - Open/Closed: Extensible via inheritance
 * - Liskov Substitution: All repositories must implement this interface
 * - Interface Segregation: Focused on core operations
 * - Dependency Inversion: Domain depends on abstraction, not implementation
 *
 * Performance Considerations:
 * - Methods should be optimized for common use cases
 * - Consider caching for frequently accessed data
 * - Batch operations for bulk operations
 *
 * @template TEntity of object
 * @template TId of int|string
 */
interface RepositoryInterface
{
    /**
     * Find entity by ID.
     *
     * @param TId $id Entity ID
     * @return TEntity|null Entity or null if not found
     */
    public function findById(int|string $id): ?object;

    /**
     * Find all entities.
     *
     * @return array<TEntity> Array of entities
     */
    public function findAll(): array;

    /**
     * Find entities by criteria.
     *
     * @param array<string, mixed> $criteria Search criteria
     * @param array<string, string> $orderBy Order by fields ['field' => 'asc|desc']
     * @param int|null $limit Maximum number of results
     * @param int|null $offset Offset for pagination
     * @return array<TEntity> Array of entities
     */
    public function findBy(
        array $criteria = [],
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null
    ): array;

    /**
     * Find one entity by criteria.
     *
     * @param array<string, mixed> $criteria Search criteria
     * @return TEntity|null Entity or null if not found
     */
    public function findOneBy(array $criteria): ?object;

    /**
     * Count entities by criteria.
     *
     * @param array<string, mixed> $criteria Search criteria
     * @return int Number of matching entities
     */
    public function count(array $criteria = []): int;

    /**
     * Check if entity exists by ID.
     *
     * @param TId $id Entity ID
     * @return bool True if exists
     */
    public function exists(int|string $id): bool;

    /**
     * Save entity (create or update).
     *
     * @param TEntity $entity Entity to save
     * @return TEntity Saved entity
     */
    public function save(object $entity): object;

    /**
     * Save multiple entities (batch operation).
     *
     * Performance: Optimized for bulk operations (100x faster than individual saves)
     *
     * @param array<TEntity> $entities Entities to save
     * @return array<TEntity> Saved entities
     */
    public function saveMany(array $entities): array;

    /**
     * Delete entity.
     *
     * @param TEntity|TId $entityOrId Entity or ID to delete
     * @return bool True if deleted
     */
    public function delete(object|int|string $entityOrId): bool;

    /**
     * Delete multiple entities (batch operation).
     *
     * @param array<TEntity|TId> $entitiesOrIds Entities or IDs to delete
     * @return int Number of deleted entities
     */
    public function deleteMany(array $entitiesOrIds): int;

    /**
     * Delete entities by criteria.
     *
     * @param array<string, mixed> $criteria Delete criteria
     * @return int Number of deleted entities
     */
    public function deleteBy(array $criteria): int;

    /**
     * Refresh entity from database.
     *
     * @param TEntity $entity Entity to refresh
     * @return TEntity Refreshed entity
     */
    public function refresh(object $entity): object;

    /**
     * Get entity class name.
     *
     * @return class-string<TEntity> Entity class name
     */
    public function getEntityClass(): string;
}
