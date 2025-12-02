<?php

declare(strict_types=1);

namespace Toporia\Framework\Repository\Criteria;

use Toporia\Framework\Database\ORM\ModelQueryBuilder;
use Toporia\Framework\Repository\Contracts\CriteriaInterface;
use Toporia\Framework\Repository\Contracts\RepositoryInterface;

/**
 * Criteria for limiting query results.
 *
 * @package Toporia\Framework\Repository\Criteria
 */
class LimitCriteria implements CriteriaInterface
{
    /**
     * @param int $limit Maximum records
     * @param int $offset Records to skip
     */
    public function __construct(
        protected int $limit,
        protected int $offset = 0
    ) {}

    /**
     * {@inheritDoc}
     */
    public function apply(ModelQueryBuilder $query, RepositoryInterface $repository): ModelQueryBuilder
    {
        $query->limit($this->limit);

        if ($this->offset > 0) {
            $query->offset($this->offset);
        }

        return $query;
    }

    /**
     * Create take criteria (alias for limit without offset).
     */
    public static function take(int $count): self
    {
        return new self($count);
    }

    /**
     * Create skip+take criteria.
     */
    public static function skipAndTake(int $skip, int $take): self
    {
        return new self($take, $skip);
    }
}
