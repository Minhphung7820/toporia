<?php

declare(strict_types=1);

namespace App\Infrastructure\Transformer;

use App\Domain\Entities\User;
use App\Infrastructure\Transformer\Resource;

/**
 * User Transformer
 *
 * Transforms User domain entities to API resources.
 * Hides sensitive data like passwords.
 *
 * Clean Architecture:
 * - Infrastructure layer implementation
 * - Transforms Domain entities to Presentation resources
 *
 * SOLID Principles:
 * - Single Responsibility: Transforms User entities only
 * - Security: Hides sensitive data
 */
final class UserTransformer extends BaseTransformer
{
    /**
     * {@inheritdoc}
     */
    public function getEntityClass(): string
    {
        return User::class;
    }

    /**
     * {@inheritdoc}
     */
    protected function transformEntity(mixed $entity, array $context = []): Resource
    {
        /** @var User $entity */
        $data = [
            'id' => $entity->id,
            'email' => $entity->email,
            'name' => $entity->name,
            'created_at' => $entity->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $entity->updatedAt?->format('Y-m-d H:i:s'),
        ];

        // Include additional fields based on context
        if (isset($context['include']) && in_array('remember_token', $context['include'], true)) {
            $data['remember_token'] = $entity->rememberToken;
        }

        // Never expose password
        // Password is always hidden for security

        return Resource::make($data);
    }
}

