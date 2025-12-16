<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Contracts\Repository\UserRepository;
use App\Domain\Entities\User;
use App\Infrastructure\Persistence\Models\UserModel;

/**
 * PDO User Repository Implementation
 *
 * Uses Toporia ORM Model for database persistence.
 * Maps between domain User entity and database UserModel.
 */
final class PdoUserRepository implements UserRepository
{
    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?User
    {
        $model = UserModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByEmail(string $email): ?User
    {
        $model = UserModel::where('email', $email)->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByToken(int $id, string $token): ?User
    {
        $model = UserModel::where('id', $id)
            ->where('remember_token', $token)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function save(User $user): User
    {
        if ($user->id === null) {
            // Create new user
            $model = UserModel::create([
                'email' => $user->email,
                'password' => $user->password,
                'name' => $user->name,
                'remember_token' => $user->rememberToken,
            ]);

            return $user->withId($model->id);
        }

        // Update existing user
        UserModel::where('id', $user->id)->update([
            'email' => $user->email,
            'password' => $user->password,
            'name' => $user->name,
            'remember_token' => $user->rememberToken,
        ]);

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function updateRememberToken(User $user, ?string $token): User
    {
        if ($user->id !== null) {
            UserModel::where('id', $user->id)->update(['remember_token' => $token]);
        }

        return $user->withRememberToken($token);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(User $user): bool
    {
        if ($user->id === null) {
            return false;
        }

        return UserModel::destroy($user->id) > 0;
    }

    /**
     * Map database model to domain entity.
     *
     * @param UserModel $model Database model.
     * @return User Domain entity.
     */
    private function toDomain(UserModel $model): User
    {
        return new User(
            id: $model->id,
            email: $model->email,
            password: $model->password,
            name: $model->name,
            rememberToken: $model->remember_token,
            createdAt: $model->created_at ? new \DateTimeImmutable($model->created_at) : null,
            updatedAt: $model->updated_at ? new \DateTimeImmutable($model->updated_at) : null
        );
    }
}
