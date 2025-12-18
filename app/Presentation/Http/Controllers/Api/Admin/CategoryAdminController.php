<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Admin;

use App\Application\Services\Admin\CategoryAdminService;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Support\Accessors\Auth;

/**
 * Category Admin Controller - Admin category management endpoints.
 */
final class CategoryAdminController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response,
        private readonly CategoryAdminService $categoryAdminService
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Get all categories.
     *
     * GET /api/admin/categories
     */
    public function index(Request $request): JsonResponseInterface
    {
        $filters = [
            'search' => $request->query('search'),
            'parent_id' => $request->query('parent_id'),
        ];
        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');

        $result = $this->categoryAdminService->getAllCategories($filters);

        return $this->json($result);
    }

    /**
     * Get a single category.
     *
     * GET /api/admin/categories/{id}
     */
    public function show(int $id): JsonResponseInterface
    {
        $result = $this->categoryAdminService->getCategory($id);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Create a new category.
     *
     * POST /api/admin/categories
     */
    public function store(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->categoryAdminService->createCategory($data, $userId);

        if (!$result['success']) {
            return $this->json($result, 422);
        }

        return $this->json($result, 201);
    }

    /**
     * Update a category.
     *
     * PUT /api/admin/categories/{id}
     */
    public function update(int $id, Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->categoryAdminService->updateCategory($id, $data, $userId);

        if (!$result['success']) {
            $statusCode = $result['message'] === 'Category not found' ? 404 : 422;
            return $this->json($result, $statusCode);
        }

        return $this->json($result);
    }

    /**
     * Delete a category.
     *
     * DELETE /api/admin/categories/{id}
     */
    public function destroy(int $id): JsonResponseInterface
    {
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->categoryAdminService->deleteCategory($id, $userId);

        if (!$result['success']) {
            $statusCode = $result['message'] === 'Category not found' ? 404 : 422;
            return $this->json($result, $statusCode);
        }

        return $this->json($result);
    }

    /**
     * Toggle category active status.
     *
     * POST /api/admin/categories/{id}/toggle-active
     */
    public function toggleActive(int $id): JsonResponseInterface
    {
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->categoryAdminService->toggleActive($id, $userId);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Reorder categories.
     *
     * POST /api/admin/categories/reorder
     */
    public function reorder(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $order = $data['order'] ?? [];
        $userId = Auth::user()->getAuthIdentifier();

        $result = $this->categoryAdminService->reorderCategories($order, $userId);

        return $this->json($result);
    }

    /**
     * Get categories for select dropdown.
     *
     * GET /api/admin/categories/select
     */
    public function selectOptions(Request $request): JsonResponseInterface
    {
        $excludeId = $request->query('exclude') ? (int) $request->query('exclude') : null;

        $result = $this->categoryAdminService->getCategoriesForSelect($excludeId);

        return $this->json($result);
    }
}
