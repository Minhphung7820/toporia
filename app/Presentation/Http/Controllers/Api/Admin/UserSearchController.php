<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Admin;

use App\Infrastructure\Persistence\Models\UserModel;
use Toporia\Framework\Http\JsonResponse;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * User Search Controller for Admin
 *
 * Provides searchable user list for filters/dropdowns
 */
final class UserSearchController
{
    /**
     * Search users with cursor pagination
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->query('search', '');
        $cursor = $request->query('cursor');
        $limit = min((int) $request->query('limit', 20), 100);
        $role = $request->query('role', '');

        $query = UserModel::query()
            ->select(['id', 'name', 'email', 'role', 'created_at'])
            ->orderBy('id', 'desc');

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Apply role filter
        if ($role) {
            $query->where('role', $role);
        }

        // Cursor pagination
        if ($cursor !== null) {
            $query->where('id', '<', (int) $cursor);
        }

        // Get one more than limit to check if there are more
        $users = $query->limit($limit + 1)->get();

        $hasMore = $users->count() > $limit;
        if ($hasMore) {
            $users = $users->slice(0, $limit);
        }

        $nextCursor = $hasMore && $users->count() > 0
            ? $users->last()->id
            : null;

        // Transform data for frontend
        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ]);
    }
}
