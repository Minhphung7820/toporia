<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * User ORM Model.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $email_verified_at
 * @property string $password
 * @property string|null $avatar
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property string|null $postal_code
 * @property string $role
 * @property bool $is_active
 * @property string|null $last_login_at
 * @property string $created_at
 * @property string $updated_at
 */
class UserModel extends Model
{
    protected static string $table = 'users';

    protected static array $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'avatar',
        'phone',
        'address',
        'city',
        'country',
        'postal_code',
        'role',
        'is_active',
        'last_login_at',
    ];

    protected static array $hidden = [
        'password',
    ];

    protected static array $casts = [
        'is_active' => 'bool',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    /**
     * Reviews written by this user.
     */
    public function reviews()
    {
        return $this->hasMany(ReviewModel::class);
    }

    /**
     * Orders placed by this user.
     */
    public function orders()
    {
        return $this->hasMany(OrderModel::class);
    }

    /**
     * Products favorited by this user (many-to-many).
     */
    public function favoriteProducts()
    {
        return $this->belongsToMany(
            ProductModel::class,
            'user_favorites',
            'user_id',
            'product_id'
        )->withTimestamps();
    }

    /**
     * User's profile (one-to-one).
     */
    public function profile()
    {
        return $this->hasOne(UserProfileModel::class);
    }

    /**
     * Roles assigned to this user (many-to-many).
     */
    public function roles()
    {
        return $this->belongsToMany(
            RoleModel::class,
            'role_user',
            'user_id',
            'role_id'
        )->withPivot('assigned_at', 'assigned_by', 'expires_at', 'is_active')
          ->withTimestamps();
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is verified.
     */
    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Scope: Active users.
     */
    public static function active()
    {
        return static::query()->where('is_active', true);
    }

    /**
     * Scope: Admin users.
     */
    public static function admins()
    {
        return static::query()->where('role', 'admin');
    }
}