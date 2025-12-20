<?php

declare(strict_types=1);

namespace App\Application\Services\Search;

use App\Infrastructure\Persistence\Models\PostModel;
use App\Infrastructure\Persistence\Models\UserModel;
use App\Infrastructure\Persistence\Models\CategoryModel;
use Toporia\Framework\Search\SearchManager;

/**
 * Post Search Service
 *
 * Handles Elasticsearch operations for posts.
 * Provides fast full-text search optimized for millions of records.
 *
 * Features:
 * - Full-text search on title, content, excerpt
 * - Filter by status, category, author, featured
 * - Cursor-based pagination for consistent results
 * - Auto-sync via model observers
 */
final class PostSearchService
{
    private const INDEX_NAME = 'posts';

    public function __construct(
        private readonly SearchManager $searchManager
    ) {}

    /**
     * Check if Elasticsearch is available.
     */
    public function isAvailable(): bool
    {
        try {
            return config('search.enabled', false) && $this->searchManager->client() !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Ensure the posts index exists with proper mappings.
     */
    public function ensureIndex(): void
    {
        $indexConfig = config('search.indices.posts');
        if (!$indexConfig) {
            throw new \RuntimeException('Posts index configuration not found in config/search.php');
        }

        $this->searchManager->client()->ensureIndex(
            $indexConfig['name'] ?? self::INDEX_NAME,
            [
                'settings' => $indexConfig['settings'] ?? [],
                'mappings' => $indexConfig['mappings'] ?? [],
            ]
        );
    }

    /**
     * Index a single post document.
     */
    public function indexPost(PostModel $post): void
    {
        $document = $this->buildDocument($post);
        $this->searchManager->indexer()->upsert(self::INDEX_NAME, $post->id, $document);
    }

    /**
     * Remove a post from the index.
     */
    public function removePost(int $postId): void
    {
        try {
            $this->searchManager->indexer()->remove(self::INDEX_NAME, $postId);
        } catch (\Throwable) {
            // Document may not exist, ignore
        }
    }

    /**
     * Bulk index multiple posts.
     *
     * @param iterable<PostModel> $posts
     * @return int Number of documents indexed
     */
    public function bulkIndex(iterable $posts): int
    {
        $documents = [];
        foreach ($posts as $post) {
            $documents[] = [
                'id' => $post->id,
                'body' => $this->buildDocument($post),
            ];
        }

        if (empty($documents)) {
            return 0;
        }

        $this->searchManager->indexer()->bulkUpsert(self::INDEX_NAME, $documents);

        return count($documents);
    }

    /**
     * Search posts for public blog (only published posts).
     *
     * @param string $query Search query
     * @param int $limit Results per page
     * @param string|null $cursor Cursor for pagination
     * @param string $direction 'next' or 'prev'
     * @return array{posts: array, next_cursor: ?string, prev_cursor: ?string, has_more: bool, total: int}
     */
    public function searchPublished(
        string $query,
        int $limit = 10,
        ?string $cursor = null,
        string $direction = 'next'
    ): array {
        return $this->search($query, [
            'is_published' => true,
            'published_before' => date('Y-m-d H:i:s'),
        ], $limit, $cursor, $direction);
    }

    /**
     * Search posts for admin panel (all posts with filters).
     *
     * @param string $query Search query
     * @param array $filters Filters: status, category_id, author_id, is_featured
     * @param int $limit Results per page
     * @param int $offset Offset for pagination
     * @return array{posts: array, total: int}
     */
    public function searchAdmin(
        string $query,
        array $filters = [],
        int $limit = 20,
        int $offset = 0
    ): array {
        $esQuery = $this->buildAdminQuery($query, $filters, $limit, $offset);

        try {
            $response = $this->searchManager->client()->search(self::INDEX_NAME, $esQuery);

            $hits = $response['hits']['hits'] ?? [];
            $total = $response['hits']['total']['value'] ?? 0;

            $posts = array_map(fn($hit) => $hit['_source'], $hits);

            return [
                'posts' => $posts,
                'total' => $total,
            ];
        } catch (\Throwable $e) {
            log_error('Elasticsearch search failed', ['error' => $e->getMessage()]);
            return ['posts' => [], 'total' => 0];
        }
    }

    /**
     * Search with cursor pagination (for blog frontend).
     *
     * @param string $query Search query
     * @param array $filters Additional filters
     * @param int $limit Results per page
     * @param string|null $cursor Cursor for pagination
     * @param string $direction 'next' or 'prev'
     * @return array{posts: array, next_cursor: ?string, prev_cursor: ?string, has_more: bool, total: int}
     */
    public function search(
        string $query,
        array $filters = [],
        int $limit = 10,
        ?string $cursor = null,
        string $direction = 'next'
    ): array {
        $esQuery = $this->buildSearchQuery($query, $filters, $limit, $cursor, $direction);

        try {
            $response = $this->searchManager->client()->search(self::INDEX_NAME, $esQuery);

            $hits = $response['hits']['hits'] ?? [];
            $total = $response['hits']['total']['value'] ?? 0;

            // Handle pagination
            $hasMore = count($hits) > $limit;
            if ($hasMore) {
                array_pop($hits);
            }

            if ($direction === 'prev') {
                $hits = array_reverse($hits);
            }

            $posts = array_map(fn($hit) => $hit['_source'], $hits);

            // Build cursors
            $nextCursor = null;
            $prevCursor = null;

            if (!empty($hits)) {
                $firstHit = $hits[0];
                $lastHit = $hits[count($hits) - 1];

                if ($hasMore) {
                    $nextCursor = $this->encodeCursor(
                        $lastHit['_source']['published_at'] ?? '',
                        (int) $lastHit['_source']['id']
                    );
                }

                if ($cursor !== null) {
                    $prevCursor = $this->encodeCursor(
                        $firstHit['_source']['published_at'] ?? '',
                        (int) $firstHit['_source']['id']
                    );
                }
            }

            return [
                'posts' => $posts,
                'next_cursor' => $nextCursor,
                'prev_cursor' => $prevCursor,
                'has_more' => $hasMore,
                'total' => $total,
            ];
        } catch (\Throwable $e) {
            log_error('Elasticsearch search failed', ['error' => $e->getMessage()]);
            return [
                'posts' => [],
                'next_cursor' => null,
                'prev_cursor' => null,
                'has_more' => false,
                'total' => 0,
            ];
        }
    }

    /**
     * Get search suggestions (autocomplete).
     *
     * @param string $prefix Search prefix
     * @param int $limit Max suggestions
     * @return array<string>
     */
    public function suggest(string $prefix, int $limit = 5): array
    {
        if (strlen($prefix) < 2) {
            return [];
        }

        $query = [
            'suggest' => [
                'title-suggest' => [
                    'prefix' => $prefix,
                    'completion' => [
                        'field' => 'title.suggest',
                        'size' => $limit,
                        'skip_duplicates' => true,
                    ],
                ],
            ],
        ];

        try {
            $response = $this->searchManager->client()->search(self::INDEX_NAME, $query);

            $suggestions = $response['suggest']['title-suggest'][0]['options'] ?? [];

            return array_map(fn($s) => $s['text'], $suggestions);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Build Elasticsearch document from PostModel.
     */
    private function buildDocument(PostModel $post): array
    {
        // Get author name
        $authorName = null;
        if ($post->author_id) {
            $author = UserModel::find($post->author_id);
            $authorName = $author?->name;
        }

        // Get category name
        $categoryName = null;
        if ($post->category_id) {
            $category = CategoryModel::find($post->category_id);
            $categoryName = $category?->name;
        }

        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'content' => $this->stripHtml($post->content ?? ''),
            'excerpt' => $post->excerpt,
            'author_id' => $post->author_id,
            'author_name' => $authorName,
            'category_id' => $post->category_id,
            'category_name' => $categoryName,
            'is_published' => (bool) $post->is_published,
            'is_featured' => (bool) $post->is_featured,
            'views' => (int) ($post->views ?? 0),
            'reading_time' => (int) ($post->reading_time ?? 0),
            'published_at' => $post->published_at,
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at,
        ];
    }

    /**
     * Build search query for blog (with cursor pagination).
     */
    private function buildSearchQuery(
        string $query,
        array $filters,
        int $limit,
        ?string $cursor,
        string $direction
    ): array {
        $must = [];
        $filter = [];

        // Full-text search on title, content, excerpt
        if (!empty($query)) {
            $must[] = [
                'multi_match' => [
                    'query' => $query,
                    'fields' => ['title^3', 'excerpt^2', 'content'],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        // Filters
        if (isset($filters['is_published'])) {
            $filter[] = ['term' => ['is_published' => $filters['is_published']]];
        }

        if (isset($filters['published_before'])) {
            $filter[] = ['range' => ['published_at' => ['lte' => $filters['published_before']]]];
        }

        if (isset($filters['category_id'])) {
            $filter[] = ['term' => ['category_id' => $filters['category_id']]];
        }

        if (isset($filters['author_id'])) {
            $filter[] = ['term' => ['author_id' => $filters['author_id']]];
        }

        // Cursor-based pagination using search_after
        $searchAfter = null;
        if ($cursor) {
            $cursorData = $this->decodeCursor($cursor);
            if ($cursorData) {
                $searchAfter = [$cursorData['published_at'], $cursorData['id']];
            }
        }

        $esQuery = [
            'query' => [
                'bool' => [
                    'must' => $must ?: [['match_all' => (object) []]],
                    'filter' => $filter,
                ],
            ],
            'sort' => [
                ['published_at' => ['order' => $direction === 'prev' ? 'asc' : 'desc']],
                ['id' => ['order' => $direction === 'prev' ? 'asc' : 'desc']],
            ],
            'size' => $limit + 1, // Fetch one extra to check has_more
            'track_total_hits' => true,
        ];

        if ($searchAfter) {
            $esQuery['search_after'] = $searchAfter;
        }

        return $esQuery;
    }

    /**
     * Search posts for admin panel with cursor pagination.
     *
     * @param string $query Search query
     * @param array $filters Filters: status, category_id, author_id, is_featured
     * @param int $limit Results per page
     * @param string|null $cursor Cursor for pagination
     * @return array{posts: array, next_cursor: ?string, prev_cursor: ?string, has_more: bool, total: int}
     */
    public function searchAdminCursor(
        string $query,
        array $filters = [],
        int $limit = 20,
        ?string $cursor = null
    ): array {
        $esQuery = $this->buildAdminCursorQuery($query, $filters, $limit, $cursor);

        try {
            $response = $this->searchManager->client()->search(self::INDEX_NAME, $esQuery);

            $hits = $response['hits']['hits'] ?? [];
            $total = $response['hits']['total']['value'] ?? 0;

            // Handle pagination - fetch one extra to check has_more
            $hasMore = count($hits) > $limit;
            if ($hasMore) {
                array_pop($hits);
            }

            $posts = array_map(fn($hit) => $hit['_source'], $hits);

            // Build cursors based on created_at (admin sorts by created_at desc)
            $nextCursor = null;
            $prevCursor = null;

            if (!empty($hits)) {
                $lastHit = $hits[count($hits) - 1];
                $firstHit = $hits[0];

                if ($hasMore) {
                    $nextCursor = $this->encodeAdminCursor(
                        $lastHit['_source']['created_at'] ?? '',
                        (int) $lastHit['_source']['id']
                    );
                }

                if ($cursor !== null) {
                    $prevCursor = $this->encodeAdminCursor(
                        $firstHit['_source']['created_at'] ?? '',
                        (int) $firstHit['_source']['id']
                    );
                }
            }

            return [
                'posts' => $posts,
                'next_cursor' => $nextCursor,
                'prev_cursor' => $prevCursor,
                'has_more' => $hasMore,
                'total' => $total,
            ];
        } catch (\Throwable $e) {
            log_error('Elasticsearch admin search failed', ['error' => $e->getMessage()]);
            return [
                'posts' => [],
                'next_cursor' => null,
                'prev_cursor' => null,
                'has_more' => false,
                'total' => 0,
            ];
        }
    }

    /**
     * Build search query for admin panel with cursor pagination.
     */
    private function buildAdminCursorQuery(
        string $query,
        array $filters,
        int $limit,
        ?string $cursor
    ): array {
        $must = [];
        $filterClauses = [];

        // Full-text search
        if (!empty($query)) {
            $must[] = [
                'multi_match' => [
                    'query' => $query,
                    'fields' => ['title^3', 'excerpt^2', 'content', 'author_name', 'category_name'],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        // Status filter
        if (isset($filters['status'])) {
            match ($filters['status']) {
                'published' => $filterClauses[] = ['term' => ['is_published' => true]],
                'draft' => $filterClauses[] = ['term' => ['is_published' => false]],
                default => null,
            };
        }

        // Category filter
        if (isset($filters['category_id']) && $filters['category_id']) {
            $filterClauses[] = ['term' => ['category_id' => (int) $filters['category_id']]];
        }

        // Author filter
        if (isset($filters['author_id']) && $filters['author_id']) {
            $filterClauses[] = ['term' => ['author_id' => (int) $filters['author_id']]];
        }

        // Featured filter
        if (isset($filters['is_featured'])) {
            $filterClauses[] = ['term' => ['is_featured' => (bool) $filters['is_featured']]];
        }

        // Cursor-based pagination using search_after
        $searchAfter = null;
        if ($cursor) {
            $cursorData = $this->decodeAdminCursor($cursor);
            if ($cursorData) {
                $searchAfter = [$cursorData['created_at'], $cursorData['id']];
            }
        }

        $esQuery = [
            'query' => [
                'bool' => [
                    'must' => $must ?: [['match_all' => (object) []]],
                    'filter' => $filterClauses,
                ],
            ],
            'sort' => [
                ['created_at' => ['order' => 'desc']],
                ['id' => ['order' => 'desc']],
            ],
            'size' => $limit + 1, // Fetch one extra to check has_more
            'track_total_hits' => true,
        ];

        if ($searchAfter) {
            $esQuery['search_after'] = $searchAfter;
        }

        return $esQuery;
    }

    /**
     * Encode admin cursor for pagination (based on created_at).
     */
    private function encodeAdminCursor(string $createdAt, int $id): string
    {
        return base64_encode(json_encode(['created_at' => $createdAt, 'id' => $id]));
    }

    /**
     * Decode admin cursor for pagination.
     */
    private function decodeAdminCursor(string $cursor): ?array
    {
        try {
            $data = json_decode(base64_decode($cursor), true);
            if (isset($data['created_at'], $data['id'])) {
                return $data;
            }
        } catch (\Throwable) {
        }
        return null;
    }

    /**
     * Build search query for admin panel (with offset pagination).
     */
    private function buildAdminQuery(
        string $query,
        array $filters,
        int $limit,
        int $offset
    ): array {
        $must = [];
        $filter = [];

        // Full-text search
        if (!empty($query)) {
            $must[] = [
                'multi_match' => [
                    'query' => $query,
                    'fields' => ['title^3', 'excerpt^2', 'content', 'author_name', 'category_name'],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        // Status filter
        if (isset($filters['status'])) {
            match ($filters['status']) {
                'published' => $filter[] = ['term' => ['is_published' => true]],
                'draft' => $filter[] = ['term' => ['is_published' => false]],
                default => null,
            };
        }

        // Category filter
        if (isset($filters['category_id']) && $filters['category_id']) {
            $filter[] = ['term' => ['category_id' => (int) $filters['category_id']]];
        }

        // Author filter
        if (isset($filters['author_id']) && $filters['author_id']) {
            $filter[] = ['term' => ['author_id' => (int) $filters['author_id']]];
        }

        // Featured filter
        if (isset($filters['is_featured'])) {
            $filter[] = ['term' => ['is_featured' => (bool) $filters['is_featured']]];
        }

        return [
            'query' => [
                'bool' => [
                    'must' => $must ?: [['match_all' => (object) []]],
                    'filter' => $filter,
                ],
            ],
            'sort' => [
                ['created_at' => ['order' => 'desc']],
                ['id' => ['order' => 'desc']],
            ],
            'from' => $offset,
            'size' => $limit,
            'track_total_hits' => true,
        ];
    }

    /**
     * Strip HTML tags and decode entities.
     */
    private function stripHtml(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Encode cursor for pagination.
     */
    private function encodeCursor(string $publishedAt, int $id): string
    {
        return base64_encode(json_encode(['published_at' => $publishedAt, 'id' => $id]));
    }

    /**
     * Decode cursor for pagination.
     */
    private function decodeCursor(string $cursor): ?array
    {
        try {
            $data = json_decode(base64_decode($cursor), true);
            if (isset($data['published_at'], $data['id'])) {
                return $data;
            }
        } catch (\Throwable) {
        }
        return null;
    }

    /**
     * Delete and recreate the index.
     */
    public function recreateIndex(): void
    {
        $indexName = config('search.indices.posts.name', self::INDEX_NAME);

        try {
            $this->searchManager->client()->deleteIndex($indexName);
        } catch (\Throwable) {
            // Index may not exist
        }

        $this->ensureIndex();
    }

    /**
     * Get index statistics.
     */
    public function getIndexStats(): array
    {
        try {
            $indexName = config('search.indices.posts.name', self::INDEX_NAME);
            return $this->searchManager->client()->getIndexStats($indexName);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
