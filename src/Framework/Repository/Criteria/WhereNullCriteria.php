<?php

declare(strict_types=1);

namespace Toporia\Framework\Repository\Criteria;

use Toporia\Framework\Database\ORM\ModelQueryBuilder;
use Toporia\Framework\Repository\Contracts\CriteriaInterface;
use Toporia\Framework\Repository\Contracts\RepositoryInterface;

/**
 * Criteria for WHERE NULL/NOT NULL conditions.
 *
 * @package Toporia\Framework\Repository\Criteria
 */
class WhereNullCriteria implements CriteriaInterface
{
    /**
     * @param string $column Column name
     * @param bool $not Whether to use NOT NULL
     */
    public function __construct(
        protected string $column,
        protected bool $not = false
    ) {}

    /**
     * {@inheritDoc}
     */
    public function apply(ModelQueryBuilder $query, RepositoryInterface $repository): ModelQueryBuilder
    {
        return $this->not
            ? $query->whereNotNull($this->column)
            : $query->whereNull($this->column);
    }

    /**
     * Create NOT NULL criteria.
     */
    public static function notNull(string $column): self
    {
        return new self($column, true);
    }
}
