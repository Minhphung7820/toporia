<?php

declare(strict_types=1);

namespace Toporia\Framework\Domain\Contracts;


/**
 * Interface EntityInterface
 *
 * Contract defining the interface for EntityInterface implementations in
 * the Domain layer of the Toporia Framework.
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
interface EntityInterface
{
    /**
     * Get the entity's unique identifier.
     *
     * @return mixed Entity ID (int, string, UUID, etc.)
     */
    public function getId(): mixed;

    /**
     * Check if this entity is the same as another.
     *
     * @param EntityInterface $other Another entity.
     * @return bool
     */
    public function equals(EntityInterface $other): bool;

    /**
     * Get the entity as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
