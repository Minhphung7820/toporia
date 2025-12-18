<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * SiteSetting ORM Model for dynamic site configuration.
 *
 * @property int $id
 * @property string $key
 * @property string $group
 * @property string|null $value
 * @property string $type
 * @property string|null $label
 * @property string|null $description
 * @property bool $is_public
 * @property bool $is_readonly
 * @property string $created_at
 * @property string $updated_at
 */
class SiteSettingModel extends Model
{
    protected static string $table = 'site_settings';

    protected static array $fillable = [
        'key',
        'group',
        'value',
        'type',
        'label',
        'description',
        'is_public',
        'is_readonly',
    ];

    protected static array $casts = [
        'is_public' => 'bool',
        'is_readonly' => 'bool',
    ];

    // Setting groups
    public const GROUP_GENERAL = 'general';
    public const GROUP_BLOG = 'blog';
    public const GROUP_SEO = 'seo';
    public const GROUP_SOCIAL = 'social';
    public const GROUP_MAIL = 'mail';

    // Value types
    public const TYPE_STRING = 'string';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_FLOAT = 'float';
    public const TYPE_JSON = 'json';
    public const TYPE_ARRAY = 'array';

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Get settings by group.
     */
    public static function byGroup(string $group)
    {
        return static::query()->where('group', $group);
    }

    /**
     * Get public settings.
     */
    public static function publicSettings()
    {
        return static::query()->where('is_public', true);
    }

    /**
     * Get editable settings (not readonly).
     */
    public static function editable()
    {
        return static::query()->where('is_readonly', false);
    }

    // =========================================================================
    // Static Helper Methods
    // =========================================================================

    /**
     * Get a setting value by key.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();
        if (!$setting) {
            return $default;
        }

        return $setting->getCastedValue();
    }

    /**
     * Set a setting value by key.
     */
    public static function setValue(string $key, mixed $value): bool
    {
        $setting = static::query()->where('key', $key)->first();
        if (!$setting) {
            return false;
        }

        if ($setting->is_readonly) {
            return false;
        }

        $setting->value = is_array($value) || is_object($value)
            ? json_encode($value)
            : (string) $value;
        return $setting->save();
    }

    /**
     * Get all settings as key-value pairs.
     */
    public static function getAllAsArray(): array
    {
        $settings = static::all();
        $result = [];

        foreach ($settings as $setting) {
            $result[$setting->key] = $setting->getCastedValue();
        }

        return $result;
    }

    /**
     * Get public settings as key-value pairs.
     */
    public static function getPublicAsArray(): array
    {
        $settings = static::publicSettings()->get();
        $result = [];

        foreach ($settings as $setting) {
            $result[$setting->key] = $setting->getCastedValue();
        }

        return $result;
    }

    /**
     * Get settings grouped by group name.
     */
    public static function getAllGrouped(): array
    {
        $settings = static::all();
        $result = [];

        foreach ($settings as $setting) {
            $group = $setting->group ?? 'general';
            if (!isset($result[$group])) {
                $result[$group] = [];
            }
            $result[$group][$setting->key] = [
                'value' => $setting->getCastedValue(),
                'label' => $setting->label,
                'description' => $setting->description,
                'type' => $setting->type,
                'is_readonly' => $setting->is_readonly,
            ];
        }

        return $result;
    }

    // =========================================================================
    // Instance Methods
    // =========================================================================

    /**
     * Get the casted value based on type.
     */
    public function getCastedValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            self::TYPE_BOOLEAN => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_INTEGER => (int) $this->value,
            self::TYPE_FLOAT => (float) $this->value,
            self::TYPE_JSON, self::TYPE_ARRAY => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * Get the foreign key name for this model.
     */
    protected function getForeignKey(): string
    {
        return 'setting_id';
    }
}
