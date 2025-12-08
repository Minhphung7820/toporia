<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

class PermissionModel extends Model
{
    protected static string $table = 'permissions';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'name', 'display_name', 'description', 'resource', 'action', 'is_active'
    ];
    protected static array $casts = [
        'is_active' => 'bool',
    ];

    public function roles()
    {
        return $this->belongsToMany(
            RoleModel::class,
            'permission_role',
            'permission_id',
            'role_id'
        )->withTimestamps();
    }
}

