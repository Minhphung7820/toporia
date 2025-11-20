<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\User;
use App\Domain\Contracts\Repository\UserRepository;
use App\Infrastructure\Persistence\Models\UserModel;
use Toporia\Framework\Support\Accessors\Log;

/**
 * PDO User Repository - Database persistence implementation.
 *
 * Uses UserModel (ORM) for database operations.
 * Maps between ORM Model (Infrastructure) and Domain Entity.
 * Follows Repository pattern and Clean Architecture.
 */
final class PdoUserRepository implements UserRepository
{
    public function __construct()
    {
        // UserModel uses static connection from Model::setConnection()
        // No need to inject connection here
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?User
    {
        $model = UserModel::find($id);
        if ($model === null) {
            return null;
        }

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritdoc}
     */
    public function findByEmail(string $email): ?User
    {
        $model = UserModel::query()
            ->where('email', '=', $email)
            ->getModels()
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritdoc}
     */
    public function findByToken(int $id, string $token): ?User
    {
        $model = UserModel::query()
            ->where('id', '=', $id)
            ->where('remember_token', '=', $token)
            ->getModels()
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritdoc}
     */
    public function save(User $user): User
    {
        if ($user->id === null) {
            // Create new user using Model
            // Use new + save() instead of create() to ensure proper insertion
            $model = new UserModel([
                'name' => $user->name,
                'email' => $user->email,
                'password' => $user->password,
                'remember_token' => $user->rememberToken,
            ]);

            // Save explicitly
            $saved = $model->save();
            if (!$saved) {
                throw new \RuntimeException('Failed to save model to database');
            }

            // Verify model was actually saved with ID
            if ($model->id === null) {
                throw new \RuntimeException('Model was saved but ID is null');
            }

            // Verify model exists in database by querying
            $verify = UserModel::query()
                ->where('id', '=', $model->id)
                ->where('email', '=', $user->email)
                ->getModels()
                ->first();

            if ($verify === null) {
                throw new \RuntimeException('Model was saved but not found in database (ID: ' . $model->id . ', Email: ' . $user->email . ')');
            }

            return $this->mapModelToEntity($model);
        }

        // Update existing user
        $model = UserModel::find($user->id);
        if ($model === null) {
            throw new \RuntimeException("User with ID {$user->id} not found");
        }

        $model->name = $user->name;
        $model->email = $user->email;
        $model->password = $user->password;
        $model->remember_token = $user->rememberToken;
        $model->save();

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritdoc}
     */
    public function updateRememberToken(User $user, ?string $token): User
    {
        if ($user->id === null) {
            throw new \LogicException('Cannot update remember token for user without ID');
        }

        $model = UserModel::find($user->id);
        if ($model === null) {
            throw new \RuntimeException("User with ID {$user->id} not found");
        }

        $model->remember_token = $token;
        $model->save();

        return $this->mapModelToEntity($model);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(User $user): bool
    {
        if ($user->id === null) {
            return false;
        }

        $model = UserModel::find($user->id);
        if ($model === null) {
            return false;
        }

        return $model->delete();
    }

    /**
     * Map ORM Model to Domain Entity.
     *
     * @param UserModel $model
     * @return User
     */
    private function mapModelToEntity(UserModel $model): User
    {
        $createdAt = $model->created_at !== null
            ? new \DateTimeImmutable($model->created_at)
            : new \DateTimeImmutable();

        $updatedAt = $model->updated_at !== null
            ? new \DateTimeImmutable($model->updated_at)
            : new \DateTimeImmutable();

        $user = new User(
            id: $model->id,
            email: $model->email,
            password: $model->password ?? '',
            name: $model->name,
            rememberToken: $model->remember_token,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        return $user;
    }
}
