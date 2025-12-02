<?php

declare(strict_types=1);

namespace Toporia\Framework\Repository\Criteria;

use Toporia\Framework\Database\ORM\ModelQueryBuilder;
use Toporia\Framework\Repository\Contracts\CriteriaInterface;
use Toporia\Framework\Repository\Contracts\RepositoryInterface;

/**
 * Criteria for WHERE BETWEEN conditions.
 *
 * @package Toporia\Framework\Repository\Criteria
 */
class WhereBetweenCriteria implements CriteriaInterface
{
    /**
     * @param string $column Column name
     * @param mixed $min Minimum value
     * @param mixed $max Maximum value
     * @param bool $not Whether to use NOT BETWEEN
     */
    public function __construct(
        protected string $column,
        protected mixed $min,
        protected mixed $max,
        protected bool $not = false
    ) {}

    /**
     * {@inheritDoc}
     */
    public function apply(ModelQueryBuilder $query, RepositoryInterface $repository): ModelQueryBuilder
    {
        return $this->not
            ? $query->whereNotBetween($this->column, [$this->min, $this->max])
            : $query->whereBetween($this->column, [$this->min, $this->max]);
    }

    /**
     * Create NOT BETWEEN criteria.
     */
    public static function notBetween(string $column, mixed $min, mixed $max): self
    {
        return new self($column, $min, $max, true);
    }

    /**
     * Create date range criteria.
     */
    public static function dateRange(string $column, string|\DateTimeInterface $start, string|\DateTimeInterface $end): self
    {
        $startDate = $start instanceof \DateTimeInterface ? $start->format('Y-m-d H:i:s') : $start;
        $endDate = $end instanceof \DateTimeInterface ? $end->format('Y-m-d H:i:s') : $end;

        return new self($column, $startDate, $endDate);
    }
}
