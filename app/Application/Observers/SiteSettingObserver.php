<?php

declare(strict_types=1);

namespace App\Application\Observers;

use App\Application\Services\SiteSettingsService;
use App\Infrastructure\Persistence\Models\SiteSettingModel;

/**
 * Site Setting Observer - Clears settings cache on any change.
 *
 * Ensures that changes to settings are immediately reflected
 * across the application without stale cached values.
 */
final class SiteSettingObserver
{
    /**
     * Handle the "created" event.
     */
    public function created(SiteSettingModel $setting): void
    {
        $this->clearCache();
    }

    /**
     * Handle the "updated" event.
     */
    public function updated(SiteSettingModel $setting): void
    {
        $this->clearCache();
    }

    /**
     * Handle the "deleted" event.
     */
    public function deleted(SiteSettingModel $setting): void
    {
        $this->clearCache();
    }

    /**
     * Clear the settings cache.
     */
    private function clearCache(): void
    {
        SiteSettingsService::getInstance()->clearCache();
    }
}
