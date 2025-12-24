<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\Admin;

use App\Application\Services\Search\PostSearchService;
use App\Infrastructure\Persistence\Models\PostModel;
use Toporia\Framework\Http\JsonResponse;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Post Search Controller for Admin
 *
 * Uses Elasticsearch for fast full-text search on millions of records.
 * Falls back to MySQL if Elasticsearch is unavailable.
 */
final class PostSearchController
{
    public function __construct(
        private readonly PostSearchService $searchService
    ) {}

    /**
     * Search posts with cursor pagination
     *
     * Uses Elasticsearch for search queries to avoid disk I/O issues.
     * Falls back to MySQL for non-search queries or when ES is unavailable.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->query('search', '');
        $cursor = $request->query('cursor');
        $limit = min((int) $request->query('limit', 20), 100);
        $published = $request->query('published'); // null, 'true', 'false'

        // Use Elasticsearch if search query exists and ES is available
        if ($search && $this->searchService->isAvailable()) {
            return $this->searchWithElasticsearch($search, $cursor, $limit, $published);
        }

        // Fallback to MySQL for non-search queries or when ES is unavailable
        return $this->searchWithMySQL($search, $cursor, $limit, $published);
    }

    /**
     * Search using Elasticsearch (fast for millions of records)
     */
    private function searchWithElasticsearch(
        string $search,
        ?string $cursor,
        int $limit,
        ?string $published
    ): JsonResponse {
        // Build filters for Elasticsearch
        $filters = [];
        if ($published === 'true') {
            $filters['is_published'] = true;
        } elseif ($published === 'false') {
            $filters['is_published'] = false;
        }

        // Elasticsearch uses offset-based pagination for search (not cursor)
        // This is acceptable because search results are usually small
        $offset = $cursor ? (int) $cursor : 0;

        try {
            // Use Elasticsearch search
            $result = $this->searchService->searchAdmin(
                $search,
                $filters,
                $limit + 1, // Get one extra to check has_more
                $offset
            );

            $posts = $result['posts'];
            $hasMore = count($posts) > $limit;

            if ($hasMore) {
                array_pop($posts); // Remove the extra item
            }

            // Transform Elasticsearch results to match expected format
            $data = [];
            foreach ($posts as $post) {
                $data[] = [
                    'id' => $post['id'],
                    'title' => $post['title'],
                    'slug' => $post['slug'],
                    'excerpt' => isset($post['excerpt']) ? substr($post['excerpt'], 0, 100) : '',
                    'is_published' => $post['is_published'],
                    'created_at' => $post['created_at'],
                ];
            }

            // For ES search results, use offset-based cursor
            $nextCursor = $hasMore ? ($offset + $limit) : null;

            return response()->json([
                'success' => true,
                'data' => $data,
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
                'source' => 'elasticsearch', // Debug info
            ]);
        } catch (\Throwable $e) {
            // Log error and fallback to MySQL
            log_error('Elasticsearch search failed, falling back to MySQL', [
                'error' => $e->getMessage(),
                'search' => $search,
            ]);

            return $this->searchWithMySQL($search, $cursor, $limit, $published);
        }
    }

    /**
     * Fallback to MySQL (for when ES is unavailable or no search query)
     *
     * Performance optimizations:
     * - Covering index on (is_published, id) for published filter
     * - Minimal column selection (no large TEXT fields)
     * - Cursor pagination (no OFFSET)
     * - LIMIT + 1 trick for hasMore check
     */
    private function searchWithMySQL(
        string $search,
        ?string $cursor,
        int $limit,
        ?string $published
    ): JsonResponse {
        $query = PostModel::query()
            ->select(['id', 'title', 'slug', 'excerpt', 'is_published', 'created_at'])
            ->orderBy('id', 'desc');

        // Apply published filter (uses index)
        if ($published === 'true') {
            $query->where('is_published', true);
        } elseif ($published === 'false') {
            $query->where('is_published', false);
        }

        // Apply search filter (WARNING: LIKE causes full table scan on large tables)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('slug', 'LIKE', "%{$search}%");
            });
        }

        // Cursor pagination
        if ($cursor !== null) {
            $query->where('id', '<', (int) $cursor);
        }

        // Get one more than limit to check if there are more
        $posts = $query->limit($limit + 1)->get();

        $hasMore = $posts->count() > $limit;
        if ($hasMore) {
            $posts = $posts->slice(0, $limit);
        }

        $nextCursor = $hasMore && $posts->count() > 0
            ? $posts->last()->id
            : null;

        // Transform data
        $data = [];
        foreach ($posts as $post) {
            $data[] = [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt ? substr($post->excerpt, 0, 100) : '',
                'is_published' => $post->is_published,
                'created_at' => $post->created_at,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
            'source' => 'mysql', // Debug info
        ]);
    }
}
