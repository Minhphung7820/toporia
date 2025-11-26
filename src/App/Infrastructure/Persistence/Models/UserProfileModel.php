<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * UserProfile ORM Model.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $bio
 * @property string|null $website
 * @property string|null $twitter
 * @property string|null $facebook
 * @property string|null $linkedin
 * @property string|null $github
 * @property string|null $birth_date
 * @property string|null $gender
 * @property array|null $preferences
 * @property array|null $settings
 * @property string $created_at
 * @property string $updated_at
 */
class UserProfileModel extends Model
{
    protected static string $table = 'user_profiles';

    protected static array $fillable = [
        'user_id',
        'bio',
        'website',
        'twitter',
        'facebook',
        'linkedin',
        'github',
        'birth_date',
        'gender',
        'preferences',
        'settings',
    ];

    protected static array $casts = [
        'user_id' => 'int',
        'preferences' => 'array',
        'settings' => 'array',
        'birth_date' => 'date',
    ];

    /**
     * User this profile belongs to.
     */
    public function user()
    {
        return $this->belongsTo(UserModel::class);
    }

    /**
     * Get age from birth date.
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->birth_date) {
            return null;
        }

        return (int) date_diff(date_create($this->birth_date), date_create('now'))->y;
    }

    /**
     * Check if profile is complete.
     */
    public function isComplete(): bool
    {
        return !empty($this->bio) && !empty($this->birth_date);
    }
}

