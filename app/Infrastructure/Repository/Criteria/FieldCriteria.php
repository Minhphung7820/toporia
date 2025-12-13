<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Criteria;

use App\Domain\Contracts\Repository\Criteria\CriteriaInterface;
use App\Domain\Contracts\Repository\QueryBuilderInterface;
use Toporia\Framework\Database\Query\QueryBuilder;

/**
 * Field Criteria Implementation
 *
 * Infrastructure implementation of field-based criteria.
 * Bridges Domain CriteriaInterface with Framework QueryBuilder.
 *
 * Clean Architecture:
 * - Implements Domain CriteriaInterface
 * - Uses Framework QueryBuilder (Infrastructure concern)
 * - Adapter pattern to bridge Domain and Framework
 */
final class FieldCriteria implements CriteriaInterface
{
    public const OPERATOR_EQUALS = '=';
    public const OPERATOR_NOT_EQUALS = '!=';
    public const OPERATOR_GREATER_THAN = '>';
    public const OPERATOR_GREATER_THAN_OR_EQUAL = '>=';
    public const OPERATOR_LESS_THAN = '<';
    public const OPERATOR_LESS_THAN_OR_EQUAL = '<=';
    public const OPERATOR_LIKE = 'LIKE';
    public const OPERATOR_NOT_LIKE = 'NOT LIKE';
    public const OPERATOR_IN = 'IN';
    public const OPERATOR_NOT_IN = 'NOT IN';
    public const OPERATOR_IS_NULL = 'IS NULL';
    public const OPERATOR_IS_NOT_NULL = 'IS NOT NULL';
    public const OPERATOR_BETWEEN = 'BETWEEN';

    /**
     * @param string $field Field name
     * @param string $operator Operator
     * @param mixed $value Value(s)
     */
    public function __construct(
        private readonly string $field,
        private readonly string $operator,
        private readonly mixed $value = null
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function apply(QueryBuilderInterface $queryBuilder): QueryBuilderInterface
    {
        // Type check: Framework QueryBuilder implements our interface
        if (!$queryBuilder instanceof QueryBuilder) {
            throw new \InvalidArgumentException('QueryBuilder must be Framework QueryBuilder instance');
        }

        // Apply criteria using Framework QueryBuilder
        // Framework QueryBuilder methods return QueryBuilder, which is compatible with QueryBuilderInterface
        $result = match ($this->operator) {
            self::OPERATOR_EQUALS => $queryBuilder->where($this->field, '=', $this->value),
            self::OPERATOR_NOT_EQUALS => $queryBuilder->where($this->field, '!=', $this->value),
            self::OPERATOR_GREATER_THAN => $queryBuilder->where($this->field, '>', $this->value),
            self::OPERATOR_GREATER_THAN_OR_EQUAL => $queryBuilder->where($this->field, '>=', $this->value),
            self::OPERATOR_LESS_THAN => $queryBuilder->where($this->field, '<', $this->value),
            self::OPERATOR_LESS_THAN_OR_EQUAL => $queryBuilder->where($this->field, '<=', $this->value),
            self::OPERATOR_LIKE => $queryBuilder->where($this->field, 'LIKE', $this->value),
            self::OPERATOR_NOT_LIKE => $queryBuilder->where($this->field, 'NOT LIKE', $this->value),
            self::OPERATOR_IN => $queryBuilder->whereIn($this->field, (array) $this->value),
            self::OPERATOR_NOT_IN => $queryBuilder->whereNotIn($this->field, (array) $this->value),
            self::OPERATOR_IS_NULL => $queryBuilder->whereNull($this->field),
            self::OPERATOR_IS_NOT_NULL => $queryBuilder->whereNotNull($this->field),
            self::OPERATOR_BETWEEN => $queryBuilder->whereBetween($this->field, $this->value[0], $this->value[1]),
            default => throw new \InvalidArgumentException("Unsupported operator: {$this->operator}"),
        };

        // Type assertion: Framework QueryBuilder is compatible with Domain QueryBuilderInterface
        // This is safe because Framework QueryBuilder implements all methods from our interface
        /** @var QueryBuilderInterface $result */
        return $result;
    }

    /**
     * Create equals criteria.
     */
    public static function equals(string $field, mixed $value): self
    {
        return new self($field, self::OPERATOR_EQUALS, $value);
    }

    /**
     * Create not equals criteria.
     */
    public static function notEquals(string $field, mixed $value): self
    {
        return new self($field, self::OPERATOR_NOT_EQUALS, $value);
    }

    /**
     * Create greater than criteria.
     */
    public static function greaterThan(string $field, mixed $value): self
    {
        return new self($field, self::OPERATOR_GREATER_THAN, $value);
    }

    /**
     * Create less than criteria.
     */
    public static function lessThan(string $field, mixed $value): self
    {
        return new self($field, self::OPERATOR_LESS_THAN, $value);
    }

    /**
     * Create like criteria.
     */
    public static function like(string $field, string $value): self
    {
        return new self($field, self::OPERATOR_LIKE, $value);
    }

    /**
     * Create in criteria.
     */
    public static function in(string $field, array $values): self
    {
        return new self($field, self::OPERATOR_IN, $values);
    }

    /**
     * Create not in criteria.
     */
    public static function notIn(string $field, array $values): self
    {
        return new self($field, self::OPERATOR_NOT_IN, $values);
    }

    /**
     * Create is null criteria.
     */
    public static function isNull(string $field): self
    {
        return new self($field, self::OPERATOR_IS_NULL);
    }

    /**
     * Create is not null criteria.
     */
    public static function isNotNull(string $field): self
    {
        return new self($field, self::OPERATOR_IS_NOT_NULL);
    }
}

