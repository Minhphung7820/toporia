<?php

declare(strict_types=1);

namespace Toporia\Framework\Repository\Criteria;

use Toporia\Framework\Database\ORM\ModelQueryBuilder;
use Toporia\Framework\Repository\Contracts\CriteriaInterface;
use Toporia\Framework\Repository\Contracts\RepositoryInterface;

/**
 * Criteria for WHERE IN conditions.
 *
 * @package Toporia\Framework\Repository\Criteria
 */
class WhereInCriteria implements CriteriaInterface
{
    /**
     * @param string $column Column name
     * @param array<mixed> $values Array of values
     * @param bool $not Whether to use NOT IN
     */
    public function __construct(
        protected string $column,
        protected array $values,
        protected bool $not = false
    ) {}

    /**
     * {@inheritDoc}
     */
    public function apply(ModelQueryBuilder $query, RepositoryInterface $repository): ModelQueryBuilder
    {
        if (empty($this->values)) {
            // Empty array: IN returns no results, NOT IN returns all
            return $this->not ? $query : $query->whereRaw('1 = 0');
        }

        return $this->not
            ? $query->whereNotIn($this->column, $this->values)
            : $query->whereIn($this->column, $this->values);
    }

    /**
     * Create NOT IN criteria.
     */
    public static function notIn(string $column, array $values): self
    {
        return new self($column, $values, true);
    }
}
