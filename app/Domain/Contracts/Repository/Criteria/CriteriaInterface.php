<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Repository\Criteria;

use App\Domain\Contracts\Repository\QueryBuilderInterface;

/**
 * Criteria Interface
 *
 * Specification Pattern for building complex queries.
 * Allows composition of multiple criteria for flexible querying.
 *
 * Clean Architecture:
 * - Domain layer defines the contract
 * - Uses Domain abstraction (QueryBuilderInterface), not Framework
 *
 * SOLID Principles:
 * - Single Responsibility: Defines query criteria contract
 * - Open/Closed: Extensible via new criteria implementations
 * - Liskov Substitution: All criteria must implement this interface
 * - Dependency Inversion: Depends on Domain abstraction, not Framework
 *
 * Performance:
 * - Criteria can be optimized at repository level
 * - Supports query caching
 * - Can be combined for complex queries
 */
interface CriteriaInterface
{
    /**
     * Apply criteria to query builder.
     *
     * @param QueryBuilderInterface $queryBuilder Query builder (Domain abstraction)
     * @return QueryBuilderInterface Modified query builder
     */
    public function apply(QueryBuilderInterface $queryBuilder): QueryBuilderInterface;
}
