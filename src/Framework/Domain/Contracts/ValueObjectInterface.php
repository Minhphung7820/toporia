<?php

declare(strict_types=1);

namespace Toporia\Framework\Domain\Contracts;


/**
 * Interface ValueObjectInterface
 *
 * Contract defining the interface for ValueObjectInterface implementations
 * in the Domain layer of the Toporia Framework.
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 * @package     toporia/framework
 * @subpackage  Domain\Contracts
 * @since       2025-01-10
 *
 * @link        https://github.com/Minhphung7820/toporia
 */
interface ValueObjectInterface
{
    /**
     * Check if this value object equals another.
     *
     * Value objects are equal if all their attributes are equal.
     *
     * @param ValueObjectInterface $other Another value object.
     * @return bool
     */
    public function equals(ValueObjectInterface $other): bool;

    /**
     * Get string representation of the value object.
     *
     * @return string
     */
    public function __toString(): string;

    /**
     * Get the value object as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
