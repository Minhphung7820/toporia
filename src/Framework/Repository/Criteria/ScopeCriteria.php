<?php

declare(strict_types=1);

namespace Toporia\Framework\Repository\Criteria;

use Closure;
use Toporia\Framework\Database\ORM\ModelQueryBuilder;
use Toporia\Framework\Repository\Contracts\CriteriaInterface;
use Toporia\Framework\Repository\Contracts\RepositoryInterface;

/**
 * Criteria that applies a custom scope callback.
 *
 * @package Toporia\Framework\Repository\Criteria
 */
class ScopeCriteria implements CriteriaInterface
{
    /**
     * @param Closure $scope Scope callback receiving query builder
     */
    public function __construct(
        protected Closure $scope
    ) {}

    /**
     * {@inheritDoc}
     */
    public function apply(ModelQueryBuilder $query, RepositoryInterface $repository): ModelQueryBuilder
    {
        ($this->scope)($query, $repository);
        return $query;
    }

    /**
     * Create criteria from callable.
     */
    public static function from(callable $callback): self
    {
        return new self(Closure::fromCallable($callback));
    }
}
