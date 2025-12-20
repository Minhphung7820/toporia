<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Blog;

use App\Infrastructure\Persistence\Models\SiteSettingModel;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Settings Controller - Public settings endpoints.
 *
 * Exposes public site settings to frontend (is_public = true only).
 */
final class SettingsController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Get all public settings.
     *
     * GET /api/blog/settings
     */
    public function index(): JsonResponseInterface
    {
        $settings = SiteSettingModel::getPublicAsArray();

        return $this->json([
            'success' => true,
            'data' => $settings,
            'message' => 'Settings retrieved successfully',
        ]);
    }

    /**
     * Get a single public setting by key.
     *
     * GET /api/blog/settings/{key}
     */
    public function show(string $key): JsonResponseInterface
    {
        /** @var SiteSettingModel|null $setting */
        $setting = SiteSettingModel::query()
            ->where('key', $key)
            ->where('is_public', true)
            ->first();

        if ($setting === null) {
            return $this->json([
                'success' => false,
                'message' => 'Setting not found or not public',
            ], 404);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'key' => $setting->key,
                'value' => $setting->getCastedValue(),
            ],
            'message' => 'Setting retrieved successfully',
        ]);
    }
}
