<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * AdminActivityLog ORM Model for audit trail.
 *
 * @property int $id
 * @property int $user_id
 * @property string $action
 * @property string|null $description
 * @property string|null $model_type
 * @property int|null $model_id
 * @property array|null $old_values
 * @property array|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $url
 * @property string $created_at
 * @property string $updated_at
 */
class AdminActivityLogModel extends Model
{
    protected static string $table = 'admin_activity_logs';

    protected static array $fillable = [
        'user_id',
        'action',
        'description',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
    ];

    protected static array $casts = [
        'user_id' => 'int',
        'model_id' => 'int',
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    // Common action types
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_PUBLISH = 'publish';
    public const ACTION_UNPUBLISH = 'unpublish';
    public const ACTION_APPROVE = 'approve';
    public const ACTION_REJECT = 'reject';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';

    /**
     * Activity belongs to user (admin).
     */
    public function user()
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Get activities by user.
     */
    public static function byUser(int $userId)
    {
        return static::query()->where('user_id', $userId);
    }

    /**
     * Get activities by action.
     */
    public static function byAction(string $action)
    {
        return static::query()->where('action', $action);
    }

    /**
     * Get activities for a specific model.
     */
    public static function forModel(string $modelType, ?int $modelId = null)
    {
        $query = static::query()->where('model_type', $modelType);
        if ($modelId !== null) {
            $query->where('model_id', $modelId);
        }
        return $query;
    }

    /**
     * Get recent activities.
     */
    public static function recent(int $limit = 10)
    {
        return static::query()
            ->orderBy('created_at', 'desc')
            ->limit($limit);
    }

    // =========================================================================
    // Static Helper Methods
    // =========================================================================

    /**
     * Log an activity.
     */
    public static function log(
        int $userId,
        string $action,
        ?string $description = null,
        ?string $modelType = null,
        ?int $modelId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): self {
        return static::create([
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'url' => $_SERVER['REQUEST_URI'] ?? null,
        ]);
    }

    /**
     * Get the foreign key name for this model.
     */
    protected function getForeignKey(): string
    {
        return 'activity_log_id';
    }
}
