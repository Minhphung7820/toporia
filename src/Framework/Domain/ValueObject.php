<?php

declare(strict_types=1);

namespace Toporia\Framework\Domain;

use Toporia\Framework\Domain\Contracts\ValueObjectInterface;

/**
 * Abstract Class ValueObject
 *
 * Abstract base class for ValueObject implementations in the Domain layer
 * providing common functionality and contracts.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Domain
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
abstract class ValueObject implements ValueObjectInterface
{
    /**
     * {@inheritdoc}
     */
    public function equals(ValueObjectInterface $other): bool
    {
        // Value objects are equal if they are of the same class
        // and have the same string representation
        if (!($other instanceof static)) {
            return false;
        }

        return $this->__toString() === $other->__toString();
    }

    /**
     * {@inheritdoc}
     */
    abstract public function __toString(): string;

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        // Default implementation using reflection
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED);

        $array = [];
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $array[$property->getName()] = $property->getValue($this);
        }

        return $array;
    }

    /**
     * Validate the value object.
     *
     * Override this method to add validation logic.
     * Throw an exception if validation fails.
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function validate(): void
    {
        // Override in child classes
    }
}
