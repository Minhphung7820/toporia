<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Admin;

use App\Application\Services\Admin\UserAdminService;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;
use Toporia\Framework\Support\Accessors\Auth;

/**
 * User Admin Controller - Admin user management endpoints.
 */
final class UserAdminController extends BaseController
{
    public function __construct(
        Request $request,
        Response $response,
        private readonly UserAdminService $userAdminService
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Get all users with pagination.
     *
     * GET /api/admin/users
     */
    public function index(Request $request): JsonResponseInterface
    {
        $filters = [
            'search' => $request->query('search'),
            'role' => $request->query('role'),
            'verified' => $request->query('verified'),
        ];
        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');

        $page = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 20), 100);

        $result = $this->userAdminService->getAllUsers($filters, $page, $perPage);

        return $this->json($result);
    }

    /**
     * Get a single user.
     *
     * GET /api/admin/users/{id}
     */
    public function show(int $id): JsonResponseInterface
    {
        $result = $this->userAdminService->getUser($id);

        if (!$result['success']) {
            return $this->json($result, 404);
        }

        return $this->json($result);
    }

    /**
     * Create a new user.
     *
     * POST /api/admin/users
     */
    public function store(Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $adminId = Auth::user()->getAuthIdentifier();

        $result = $this->userAdminService->createUser($data, $adminId);

        if (!$result['success']) {
            return $this->json($result, 422);
        }

        return $this->json($result, 201);
    }

    /**
     * Update a user.
     *
     * PUT /api/admin/users/{id}
     */
    public function update(int $id, Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $adminId = Auth::user()->getAuthIdentifier();

        $result = $this->userAdminService->updateUser($id, $data, $adminId);

        if (!$result['success']) {
            $statusCode = $result['message'] === 'User not found' ? 404 : 422;
            return $this->json($result, $statusCode);
        }

        return $this->json($result);
    }

    /**
     * Delete a user.
     *
     * DELETE /api/admin/users/{id}
     */
    public function destroy(int $id): JsonResponseInterface
    {
        $adminId = Auth::user()->getAuthIdentifier();

        $result = $this->userAdminService->deleteUser($id, $adminId);

        if (!$result['success']) {
            $statusCode = $result['message'] === 'User not found' ? 404 : 422;
            return $this->json($result, $statusCode);
        }

        return $this->json($result);
    }

    /**
     * Change user role.
     *
     * POST /api/admin/users/{id}/role
     */
    public function changeRole(int $id, Request $request): JsonResponseInterface
    {
        $data = $request->json();
        $role = $data['role'] ?? '';
        $adminId = Auth::user()->getAuthIdentifier();

        $result = $this->userAdminService->changeRole($id, $role, $adminId);

        if (!$result['success']) {
            $statusCode = $result['message'] === 'User not found' ? 404 : 422;
            return $this->json($result, $statusCode);
        }

        return $this->json($result);
    }

    /**
     * Get user statistics.
     *
     * GET /api/admin/users/statistics
     */
    public function statistics(): JsonResponseInterface
    {
        $result = $this->userAdminService->getStatistics();

        return $this->json($result);
    }
}
