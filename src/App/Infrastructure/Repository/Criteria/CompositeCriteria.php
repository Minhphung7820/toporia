<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Criteria;

use App\Domain\Contracts\Repository\Criteria\CriteriaInterface;
use App\Domain\Contracts\Repository\QueryBuilderInterface;
use Toporia\Framework\Database\Query\QueryBuilder;

/**
 * Composite Criteria Implementation
 *
 * Infrastructure implementation of composite criteria.
 * Combines multiple criteria with AND/OR logic.
 *
 * Clean Architecture:
 * - Implements Domain CriteriaInterface
 * - Uses Framework QueryBuilder (Infrastructure concern)
 * - Adapter pattern to bridge Domain and Framework
 */
final class CompositeCriteria implements CriteriaInterface
{
    public const OPERATOR_AND = 'AND';
    public const OPERATOR_OR = 'OR';

    /**
     * @param array<CriteriaInterface> $criteria Array of criteria
     * @param string $operator Operator (AND or OR)
     */
    public function __construct(
        private readonly array $criteria,
        private readonly string $operator = self::OPERATOR_AND
    ) {}

    /**
     * {@inheritdoc}
     */
    public function apply(QueryBuilderInterface $queryBuilder): QueryBuilderInterface
    {
        // Type check: Framework QueryBuilder implements our interface
        if (!$queryBuilder instanceof QueryBuilder) {
            throw new \InvalidArgumentException('QueryBuilder must be Framework QueryBuilder instance');
        }

        if ($this->operator === self::OPERATOR_AND) {
            foreach ($this->criteria as $criterion) {
                $queryBuilder = $criterion->apply($queryBuilder);
            }
        } else {
            // OR logic: wrap in where group
            // Framework QueryBuilder supports callable where for grouping
            $queryBuilder->where(function (QueryBuilder $q) {
                foreach ($this->criteria as $index => $criterion) {
                    if ($index === 0) {
                        // Type assertion: Framework QueryBuilder is compatible with Domain interface
                        /** @var QueryBuilderInterface $q */
                        $criterion->apply($q);
                    } else {
                        $q->orWhere(function (QueryBuilder $subQ) use ($criterion) {
                            // Type assertion: Framework QueryBuilder is compatible with Domain interface
                            /** @var QueryBuilderInterface $subQ */
                            $criterion->apply($subQ);
                        });
                    }
                }
            });
        }

        // Type assertion: Framework QueryBuilder is compatible with Domain QueryBuilderInterface
        /** @var QueryBuilderInterface $queryBuilder */
        return $queryBuilder;
    }

    /**
     * Create AND composite criteria.
     *
     * @param CriteriaInterface ...$criteria Criteria to combine
     * @return self
     */
    public static function and(CriteriaInterface ...$criteria): self
    {
        return new self($criteria, self::OPERATOR_AND);
    }

    /**
     * Create OR composite criteria.
     *
     * @param CriteriaInterface ...$criteria Criteria to combine
     * @return self
     */
    public static function or(CriteriaInterface ...$criteria): self
    {
        return new self($criteria, self::OPERATOR_OR);
    }
}
