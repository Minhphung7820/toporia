<?php

declare(strict_types=1);

namespace App\Application\Services\Admin;

use App\Infrastructure\Persistence\Models\AdminActivityLogModel;
use App\Infrastructure\Persistence\Models\SiteSettingModel;

/**
 * Settings Service - Handles site settings management.
 *
 * Following Service pattern with consistent return format.
 */
final class SettingsService
{
    /**
     * Get all settings.
     *
     * @return array{success: bool, data?: array, message: string}
     */
    public function getAllSettings(): array
    {
        $settings = SiteSettingModel::all();

        $grouped = [];
        foreach ($settings as $setting) {
            $grouped[$setting->group][] = [
                'key' => $setting->key,
                'value' => $setting->value,
                'type' => $setting->type,
                'description' => $setting->description,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'settings' => $grouped,
            ],
            'message' => 'Settings retrieved successfully',
        ];
    }

    /**
     * Get settings by group.
     *
     * @param string $group Group name
     * @return array{success: bool, data?: array, message: string}
     */
    public function getSettingsByGroup(string $group): array
    {
        $settings = SiteSettingModel::byGroup($group)->get();

        return [
            'success' => true,
            'data' => [
                'group' => $group,
                'settings' => array_map(fn($s) => [
                    'key' => $s->key,
                    'value' => $s->value,
                    'type' => $s->type,
                    'description' => $s->description,
                ], $settings->all()),
            ],
            'message' => 'Settings retrieved successfully',
        ];
    }

    /**
     * Get a single setting value.
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return SiteSettingModel::getValue($key, $default);
    }

    /**
     * Update a single setting.
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param int $userId Admin user ID
     * @return array{success: bool, message: string}
     */
    public function updateSetting(string $key, mixed $value, int $userId): array
    {
        $setting = SiteSettingModel::where('key', $key)->first();

        if (!$setting) {
            return ['success' => false, 'message' => 'Setting not found'];
        }

        // Cast value based on type
        $castedValue = $this->castValue($value, $setting->type);

        SiteSettingModel::setValue($key, $castedValue);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Updated setting: {$key}",
            'Setting'
        );

        return [
            'success' => true,
            'message' => 'Setting updated successfully',
        ];
    }

    /**
     * Update multiple settings at once.
     *
     * @param array $settings Array of [key => value]
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function updateSettings(array $settings, int $userId): array
    {
        $updated = 0;
        $errors = [];

        foreach ($settings as $key => $value) {
            $setting = SiteSettingModel::where('key', $key)->first();

            if (!$setting) {
                $errors[] = "Setting not found: {$key}";
                continue;
            }

            $castedValue = $this->castValue($value, $setting->type);
            SiteSettingModel::setValue($key, $castedValue);
            $updated++;
        }

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Updated {$updated} settings"
        );

        return [
            'success' => true,
            'data' => [
                'updated' => $updated,
                'errors' => $errors,
            ],
            'message' => "{$updated} settings updated successfully",
        ];
    }

    /**
     * Create a new setting.
     *
     * @param array $data Setting data
     * @param int $userId Admin user ID
     * @return array{success: bool, data?: array, message: string}
     */
    public function createSetting(array $data, int $userId): array
    {
        if (empty($data['key'])) {
            return ['success' => false, 'message' => 'Key is required'];
        }

        // Check key uniqueness
        if (SiteSettingModel::where('key', $data['key'])->exists()) {
            return ['success' => false, 'message' => 'Setting key already exists'];
        }

        $setting = SiteSettingModel::create([
            'key' => $data['key'],
            'value' => $data['value'] ?? null,
            'group' => $data['group'] ?? 'general',
            'type' => $data['type'] ?? 'string',
            'description' => $data['description'] ?? null,
        ]);

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_CREATE,
            "Created setting: {$data['key']}",
            'Setting',
            $setting->id
        );

        return [
            'success' => true,
            'data' => [
                'setting' => [
                    'key' => $setting->key,
                    'value' => $setting->value,
                    'group' => $setting->group,
                    'type' => $setting->type,
                ],
            ],
            'message' => 'Setting created successfully',
        ];
    }

    /**
     * Delete a setting.
     *
     * @param string $key Setting key
     * @param int $userId Admin user ID
     * @return array{success: bool, message: string}
     */
    public function deleteSetting(string $key, int $userId): array
    {
        $setting = SiteSettingModel::where('key', $key)->first();

        if (!$setting) {
            return ['success' => false, 'message' => 'Setting not found'];
        }

        $setting->delete();

        // Log activity
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_DELETE,
            "Deleted setting: {$key}",
            'Setting'
        );

        return [
            'success' => true,
            'message' => 'Setting deleted successfully',
        ];
    }

    /**
     * Get available setting groups.
     *
     * @return array{success: bool, data?: array, message: string}
     */
    public function getGroups(): array
    {
        $groups = SiteSettingModel::query()
            ->select('group')
            ->distinct()
            ->pluck('group')
            ->all();

        return [
            'success' => true,
            'data' => [
                'groups' => $groups,
            ],
            'message' => 'Groups retrieved successfully',
        ];
    }

    /**
     * Reset settings to defaults.
     *
     * @param string|null $group Group to reset (null for all)
     * @param int $userId Admin user ID
     * @return array{success: bool, message: string}
     */
    public function resetToDefaults(?string $group, int $userId): array
    {
        $defaults = $this->getDefaultSettings();

        if ($group !== null) {
            $defaults = array_filter($defaults, fn($s) => $s['group'] === $group);
        }

        foreach ($defaults as $setting) {
            SiteSettingModel::setValue($setting['key'], $setting['value']);
        }

        // Log activity
        $scope = $group ? "group: {$group}" : 'all settings';
        AdminActivityLogModel::log(
            $userId,
            AdminActivityLogModel::ACTION_UPDATE,
            "Reset {$scope} to defaults"
        );

        return [
            'success' => true,
            'message' => 'Settings reset to defaults successfully',
        ];
    }

    /**
     * Cast value to appropriate type.
     */
    private function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'array', 'json' => is_string($value) ? json_decode($value, true) : $value,
            default => (string) $value,
        };
    }

    /**
     * Get default settings.
     */
    private function getDefaultSettings(): array
    {
        return [
            ['key' => 'site_name', 'value' => 'Toporia Blog', 'group' => 'general'],
            ['key' => 'site_description', 'value' => 'A modern blog platform', 'group' => 'general'],
            ['key' => 'site_url', 'value' => '', 'group' => 'general'],
            ['key' => 'posts_per_page', 'value' => 10, 'group' => 'blog'],
            ['key' => 'comments_enabled', 'value' => true, 'group' => 'blog'],
            ['key' => 'comments_require_approval', 'value' => true, 'group' => 'blog'],
            ['key' => 'meta_title', 'value' => '', 'group' => 'seo'],
            ['key' => 'meta_description', 'value' => '', 'group' => 'seo'],
            ['key' => 'maintenance_mode', 'value' => false, 'group' => 'system'],
        ];
    }
}
