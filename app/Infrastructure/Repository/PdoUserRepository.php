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
        $data = [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $user->password,
            'role' => $user->role,
            'email_verified_at' => $user->emailVerifiedAt?->format('Y-m-d H:i:s'),
            'avatar' => $user->avatar,
            'remember_token' => $user->rememberToken,
        ];

        if ($user->id === null) {
            // Create new user
            $model = UserModel::create($data);

            return $user->withId($model->id);
        }

        // Update existing user
        UserModel::where('id', $user->id)->update($data);

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
     * {@inheritdoc}
     */
    public function findWithFilters(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $query = UserModel::query();

        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', $searchTerm)
                    ->orWhere('email', 'LIKE', $searchTerm);
            });
        }

        if (isset($filters['verified'])) {
            if ($filters['verified']) {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $models = $query
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $models->map(fn(UserModel $model) => $this->toDomain($model))->all();
    }

    /**
     * {@inheritdoc}
     */
    public function countAll(): int
    {
        return UserModel::count();
    }

    /**
     * {@inheritdoc}
     */
    public function updateRole(int $userId, string $role): void
    {
        UserModel::where('id', $userId)->update(['role' => $role]);
    }

    /**
     * {@inheritdoc}
     */
    public function countVerified(): int
    {
        return UserModel::whereNotNull('email_verified_at')->count();
    }

    /**
     * {@inheritdoc}
     */
    public function countByRole(): array
    {
        $results = UserModel::query()
            ->selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->get();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row->role ?? 'user'] = (int) $row->count;
        }

        return $counts;
    }

    /**
     * {@inheritdoc}
     */
    public function countRecentRegistrations(int $days = 30): int
    {
        $date = (new \DateTimeImmutable())->modify("-{$days} days")->format('Y-m-d H:i:s');

        return UserModel::where('created_at', '>=', $date)->count();
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
            name: $model->name,
            email: $model->email,
            password: $model->password,
            role: $model->role ?? 'user',
            emailVerifiedAt: $model->email_verified_at ? new \DateTimeImmutable($model->email_verified_at) : null,
            avatar: $model->avatar,
            bio: null,
            website: null,
            rememberToken: $model->remember_token,
            createdAt: $model->created_at ? new \DateTimeImmutable($model->created_at) : null,
            updatedAt: $model->updated_at ? new \DateTimeImmutable($model->updated_at) : null
        );
    }
}
