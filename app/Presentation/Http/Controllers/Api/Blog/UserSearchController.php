<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Blog;

use App\Infrastructure\Persistence\Models\UserModel;
use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * User Search Controller - Search users for @mentions.
 *
 * Optimized for autocomplete with debounced search.
 * Returns minimal user data for privacy.
 */
final class UserSearchController extends BaseController
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    /**
     * Search users by name/username for @mention autocomplete.
     *
     * GET /api/blog/users/search?q=john&limit=10
     *
     * Returns: id, name, username, avatar
     */
    public function search(Request $request): JsonResponseInterface
    {
        $query = trim($request->get('q') ?? '');
        $limit = min((int) ($request->get('limit') ?? 10), 20);

        if (strlen($query) < 2) {
            return $this->json([
                'success' => true,
                'data' => [],
                'message' => 'Query too short (min 2 characters)',
            ]);
        }

        // Search users by name or username
        // Using LIKE for simplicity, can be optimized with full-text search for very large datasets
        $users = UserModel::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('username', 'LIKE', "%{$query}%");
            })
            ->where('is_active', true)
            ->select(['id', 'name', 'username', 'avatar'])
            ->orderByRaw("
                CASE
                    WHEN name LIKE ? THEN 1
                    WHEN username LIKE ? THEN 2
                    ELSE 3
                END
            ", ["{$query}%", "{$query}%"])
            ->limit($limit)
            ->get();

        $results = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->avatar,
                'display' => $user->username ? "@{$user->username}" : $user->name,
            ];
        })->all();

        return $this->json([
            'success' => true,
            'data' => $results,
        ]);
    }
}
