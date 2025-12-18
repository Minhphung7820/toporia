<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository\Admin;

use App\Infrastructure\Persistence\Models\UserModel;
use Toporia\Framework\Repository\BaseRepository;
use Toporia\Framework\Support\Pagination\Paginator;

/**
 * User Admin Repository
 *
 * Extends framework BaseRepository for admin user management.
 */
final class UserAdminRepository extends BaseRepository
{
    protected string $model = UserModel::class;

    /**
     * Get paginated users with optional filters.
     */
    public function getPaginated(array $filters = [], int $perPage = 20, int $page = 1): Paginator
    {
        return $this
            ->applyFilters($filters)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, $page);
    }

    protected function applyFilters(array $filters): static
    {
        if (!empty($filters)) {
            $this->scope(function ($query) use ($filters) {
                if (!empty($filters['search'])) {
                    $search = $filters['search'];
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                          ->orWhere('email', 'LIKE', "%{$search}%");
                    });
                }

                if (!empty($filters['role'])) {
                    $query->where('role', $filters['role']);
                }

                if (isset($filters['verified']) && $filters['verified'] !== '') {
                    if ($filters['verified']) {
                        $query->whereNotNull('email_verified_at');
                    } else {
                        $query->whereNull('email_verified_at');
                    }
                }
            });
        }
        return $this;
    }

    public function findByEmail(string $email): ?UserModel
    {
        return $this->findBy('email', $email);
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = $this->getQuery()->where('email', $email);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->exists();
    }

    public function getStatistics(): array
    {
        return [
            'total' => $this->count(),
            'admins' => $this->countWhere(['role' => 'admin']),
            'users' => $this->countWhere(['role' => 'user']),
            'verified' => $this->raw(fn($q) => $q->whereNotNull('email_verified_at')->count()),
            'unverified' => $this->raw(fn($q) => $q->whereNull('email_verified_at')->count()),
        ];
    }

    public function changeRole(int $id, string $role): ?UserModel
    {
        $user = $this->find($id);
        if ($user) {
            return $this->update($id, ['role' => $role]);
        }
        return null;
    }
}
