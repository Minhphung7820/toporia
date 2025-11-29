<?php

declare(strict_types=1);

namespace Toporia\Framework\Socialite\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Social Account Model
 *
 * Links OAuth provider accounts to application users.
 */
final class SocialAccount extends Model
{
    protected $table = 'social_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'provider_token',
        'provider_refresh_token',
        'provider_expires_at',
        'name',
        'email',
        'avatar',
        'nickname',
        'metadata',
    ];

    protected $casts = [
        'provider_expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Relationship to user.
     *
     * @return \Toporia\Framework\Database\ORM\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(\Toporia\Framework\Auth\Authenticatable::class, 'user_id');
    }

    /**
     * Find social account by provider.
     *
     * @param string $provider Provider name
     * @param string $providerId Provider user ID
     * @return self|null
     */
    public static function findByProvider(string $provider, string $providerId): ?self
    {
        return static::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();
    }
}

