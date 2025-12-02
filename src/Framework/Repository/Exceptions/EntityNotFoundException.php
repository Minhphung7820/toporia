<?php

declare(strict_types=1);

namespace Toporia\Framework\Repository\Exceptions;

/**
 * Exception thrown when an entity is not found.
 *
 * @package Toporia\Framework\Repository\Exceptions
 */
class EntityNotFoundException extends RepositoryException
{
    /**
     * @param string $model Model class name
     * @param int|string|array<int|string> $id Entity ID(s)
     */
    public function __construct(
        public readonly string $model,
        public readonly int|string|array $id
    ) {
        $ids = is_array($id) ? implode(', ', $id) : $id;
        parent::__construct("Entity [{$model}] not found for ID(s): {$ids}");
    }

    /**
     * Get the model class name.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the missing ID(s).
     */
    public function getId(): int|string|array
    {
        return $this->id;
    }
}
