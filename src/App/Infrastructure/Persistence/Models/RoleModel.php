<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Role Model
 *
 * Relationships demonstrated:
 * - belongsToMany: users, permissions
 */
class RoleModel extends Model
{
    protected static string $table = 'roles';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'name', 'display_name', 'description', 'level', 'is_active'
    ];
    protected static array $casts = [
        'level' => 'int',
        'is_active' => 'bool',
    ];

    /**
     * belongsToMany: A role belongs to many users
     */
    public function users()
    {
        return $this->belongsToMany(
            UserModel::class,
            'role_user',
            'role_id',
            'user_id'
        )->withPivot('assigned_at', 'assigned_by', 'expires_at', 'is_active')
          ->withTimestamps();
    }

    /**
     * belongsToMany: A role has many permissions
     */
    public function permissions()
    {
        return $this->belongsToMany(
            PermissionModel::class,
            'permission_role',
            'role_id',
            'permission_id'
        )->withTimestamps();
    }
}

