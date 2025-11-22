<?php

declare(strict_types=1);

namespace Toporia\Framework\Domain;

use Toporia\Framework\Domain\Contracts\EntityInterface;

/**
 * Abstract Class Entity
 *
 * Abstract base class for Entity implementations in the Domain layer
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
abstract class Entity implements EntityInterface
{
    /**
     * @var array<string, mixed> Entity attributes.
     */
    protected array $attributes = [];

    /**
     * {@inheritdoc}
     */
    abstract public function getId(): mixed;

    /**
     * {@inheritdoc}
     */
    public function equals(EntityInterface $other): bool
    {
        // Entities are equal if they are of the same class and have the same ID
        if (!($other instanceof static)) {
            return false;
        }

        return $this->getId() === $other->getId() && $this->getId() !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Get an attribute value.
     *
     * @param string $key Attribute key.
     * @return mixed
     */
    protected function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Set an attribute value.
     *
     * @param string $key Attribute key.
     * @param mixed $value Attribute value.
     * @return void
     */
    protected function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Check if attribute exists.
     *
     * @param string $key Attribute key.
     * @return bool
     */
    protected function hasAttribute(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Magic getter for attributes.
     *
     * @param string $key Attribute key.
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Magic isset for attributes.
     *
     * @param string $key Attribute key.
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return $this->hasAttribute($key);
    }
}
