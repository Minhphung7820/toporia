<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Admin;

use App\Application\Services\Admin\SettingsService;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Support\Accessors\Auth;

/**
 * Settings Admin Controller - Admin settings management endpoints.
 */
final class SettingsAdminController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response,
        private readonly SettingsService $settingsService
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Get all settings.
     *
     * GET /api/admin/settings
     */
    public function index(): JsonResponseInterface
    {
        $result = $this->settingsService->getAllSettings();

        return $this->json($result);
    }

    /**
     * Get settings by group.
     *
     * GET /api/admin/settings/group/{group}
     */
    public function byGroup(string $group): JsonResponseInterface
    {
        $result = $this->settingsService->getSettingsByGroup($group);

        return $this->json($result);
    }

    /**
     * Update a single setting.
     *
     * PUT /api/admin/settings/{key}
     */
    public function update(string $key, Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $value = $data['value'] ?? null;
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->settingsService->updateSetting($key, $value, $userId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Update multiple settings.
     *
     * PUT /api/admin/settings
     */
    public function updateBatch(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $settings = $data['settings'] ?? [];
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->settingsService->updateSettings($settings, $userId);

        return $this->json($result);
    }

    /**
     * Create a new setting.
     *
     * POST /api/admin/settings
     */
    public function store(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->settingsService->createSetting($data, $userId);

        if (!$result['success']) {
            return $this->json($result, 422);
        }

        return $this->json($result, 201);
    }

    /**
     * Delete a setting.
     *
     * DELETE /api/admin/settings/{key}
     */
    public function destroy(string $key): JsonResponseInterface
    {
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->settingsService->deleteSetting($key, $userId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Get available groups.
     *
     * GET /api/admin/settings/groups
     */
    public function groups(): JsonResponseInterface
    {
        $result = $this->settingsService->getGroups();

        return $this->json($result);
    }

    /**
     * Reset settings to defaults.
     *
     * POST /api/admin/settings/reset
     */
    public function reset(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $group = $data['group'] ?? null;
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->settingsService->resetToDefaults($group, $userId);

        return $this->json($result);
    }
}
