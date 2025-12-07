<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Infrastructure\Persistence\Models\CategoryModel;
use App\Infrastructure\Persistence\Models\OrderModel;
use App\Infrastructure\Persistence\Models\ProductModel;
use App\Infrastructure\Persistence\Models\ReviewModel;
use App\Infrastructure\Persistence\Models\UserModel;
use App\Infrastructure\Persistence\Models\TagModel;
use App\Infrastructure\Persistence\Models\OrderItemModel;
use App\Infrastructure\Persistence\Models\PostModel;
use App\Infrastructure\Persistence\Models\VideoModel;
use App\Infrastructure\Persistence\Models\CommentModel;
use App\Infrastructure\Persistence\Models\ImageModel;
use Toporia\Framework\Database\ORM\Relations\MorphTo;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Contracts\JsonResponseInterface;
use Toporia\Framework\Support\Accessors\DB;
use Toporia\Framework\Support\Accessors\QueryBuilder;

/**
 * Product API Controller
 *
 * Test controller for querying products, categories, reviews, and orders.
 */
final class ProductController extends BaseController
{
    /**
     * Format query logs for response.
     *
     * @param array $queries Raw query logs
     * @return array Formatted query logs
     */
    private function formatQueryLogs(array $queries): array
    {
        return array_map(function ($query) {
            return [
                'query' => $query['query'] ?? '',
                'bindings' => $query['bindings'] ?? [],
                'time' => ($query['time'] ?? 0) . 'ms'
            ];
        }, $queries);
    }

    /**
     * Demo method to test different HTTP methods.
     *
     * GET /api/products/test-methods
     * POST /api/products/test-methods
     * PUT /api/products/test-methods
     * PATCH /api/products/test-methods
     * DELETE /api/products/test-methods
     */
    public function testMethods(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $response = [
            'method' => $request->method(),
            'is_get' => $request->isGet(),
            'is_post' => $request->isPost(),
            'is_put' => $request->isPut(),
            'is_patch' => $request->isPatch(),
            'is_delete' => $request->isDelete(),
            'get_data' => $request->get(),
            'post_data' => $request->post(),
            'put_data' => $request->put(),
            'patch_data' => $request->patch(),
            'delete_data' => $request->delete(),
            'all_input' => $request->input(),
        ];

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'HTTP Methods Test',
            'data' => $response,
            'queries' => $queries,
        ]);
    }

    /**
     * Get products with pagination and filters.
     *
     * GET /api/products?page=1&per_page=20&category_id=1&min_price=100&max_price=500&search=laptop
     */
    public function index(Request $request)
    {
        DB::enableQueryLog();

        // Test enterprise Response system with different response types
        // Test different approaches for explain functionality

        // Option 1: Get SQL string for debugging
        $query = ProductModel::query()->where('category_id', 1);
        $sqlInfo = [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ];
        $actualData = ProductModel::where('category_id', 1)->first();

        // Option 3: Performance testing with query hints
        // Cursor-based pagination (high-performance for large datasets)
        // O(1) performance regardless of dataset size
        $perPage = (int) ($request->get('per_page', 150));
        $cursor = $request->get('cursor'); // Get cursor from query parameter
        $path = $request->path();
        $baseUrl = $request->root(); // Get base URL (http://localhost:8000)
        // Example 1: Filter products that have allTags with name = 'abs'
        $optimizedQuery = ProductModel::query()
            ->with([
                'categories',
                'reviews.user' => function ($q) {
                    return $q->where('role', '=', 'users');
                },
                'allTags' // Eager load allTags for the filtered products
            ])
            ->where('id', '>', 40000)
            ->whereHas('allTags', function ($q) {
                $q->where('tags.id', 17);
            })
            ->optimizeForLargeResults()
            ->orderBy('id', 'DESC') // Cursor column must be ordered
            ->cursorPaginate($perPage, ['cursor' => $cursor], ['path' => $path, 'baseUrl' => $baseUrl]);
        $queries = $this->formatQueryLogs(DB::getQueryLog());
        $singleProduct = [
            'queries' => $queries,
            'sql_info' => $sqlInfo,
            'actual_data' => $actualData,
            'optimized_result' => $optimizedQuery,
            'performance_note' => 'explain() needs proper implementation in framework'
        ];
        return response()->json([
            'success' => true,
            'data' => $singleProduct,
        ]);
    }

    /**
     * Get single product with relationships.
     *
     * GET /api/products/{id}?with=category,reviews,categories
     */
    public function show(Request $request, int $id): JsonResponseInterface
    {
        try {
            DB::enableQueryLog();

            $query = ProductModel::where('id', $id);

            // Eager loading
            $with = $request->input('with', '');
            if ($with) {
                $relations = explode(',', $with);
                $relations = array_map('trim', $relations); // Trim whitespace
                $query->with(...$relations);
            }

            /** @var ProductModel|null $product */
            $product = $query->first();

            $queries = $this->formatQueryLogs(DB::getQueryLog());

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                    'queries' => $queries,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $product->toArray(),
                'queries' => $queries,
            ]);
        } catch (\Throwable $e) {
            $queries = $this->formatQueryLogs(DB::getQueryLog());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'queries' => $queries,
            ], 500);
        }
    }

    /**
     * Get products with complex queries.
     *
     * GET /api/products/complex?test=aggregates|chunking|eager|subquery|cte
     */
    public function complex(Request $request)
    {
        $test = $request->input('test', 'aggregates');

        // Enable query logging for all cases
        DB::enableQueryLog();

        switch ($test) {
            case 'aggregates':
                // Test aggregates
                $stats = ProductModel::query()
                    ->aggregates([
                        'total_products' => 'COUNT(*)',
                        'avg_price' => 'AVG(price)',
                        'max_price' => 'MAX(price)',
                        'min_price' => 'MIN(price)',
                        'total_stock' => 'SUM(stock)',
                    ]);

                $queries = DB::getQueryLog();
                $queries = array_map(function ($query) {
                    return [
                        'query' => $query['query'],
                        'bindings' => $query['bindings'],
                        'time' => $query['time'] . 'ms'
                    ];
                }, $queries);

                return response()->json([
                    'success' => true,
                    'test' => 'aggregates',
                    'data' => $stats,
                    'queries' => $queries,
                ]);
                break;

            case 'chunking':
                // Test chunking
                $count = 0;
                ProductModel::query()
                    ->where('is_active', true)
                    ->chunkById(100, function ($products) use (&$count) {
                        $count += $products->count();
                    });

                $queries = DB::getQueryLog();
                $queries = array_map(function ($query) {
                    return [
                        'query' => $query['query'],
                        'bindings' => $query['bindings'],
                        'time' => $query['time'] . 'ms'
                    ];
                }, $queries);

                return response()->json([
                    'success' => true,
                    'test' => 'chunking',
                    'data' => [
                        'chunked_count' => $count,
                    ],
                    'queries' => $queries,
                ]);
                break;

            case 'eager':
                // Test eager loading
                $products = ProductModel::with([
                    'category',
                    'categories',
                    'reviews' => function ($q) {
                        $q->where('is_approved', true)
                            ->where('rating', '>=', 1)
                            ->orderBy('rating', 'DESC')
                            ->limit(1);
                    },
                ])
                    ->withCount('reviews')
                    ->withSum('reviews', 'rating')
                    ->limit(10)
                    ->get();

                $queries = DB::getQueryLog();
                $queries = array_map(function ($query) {
                    return [
                        'query' => $query['query'],
                        'bindings' => $query['bindings'],
                        'time' => $query['time'] . 'ms'
                    ];
                }, $queries);

                $data = [];
                foreach ($products as $product) {
                    $data[] = $product->toArray();
                }

                return response()->json([
                    'success' => true,
                    'test' => 'eager_loading',
                    'data' => $data,
                    'queries' => $queries,
                ]);
                break;

            case 'subquery':
                // Test subqueries
                // Optimized query using index order: (is_approved, product_id, rating)
                $products = ProductModel::query()
                    ->whereIn('id', function ($q) {
                        $q->table('reviews')
                            ->select('product_id')
                            ->where('is_approved', true)  // First: uses index prefix
                            ->where('rating', '>=', 4)     // Third: uses index suffix
                            ->groupBy('product_id')        // Second: uses index middle
                            ->havingRaw('COUNT(*) >= 5');
                    })
                    ->with('category')
                    ->limit(10)
                    ->get();

                $queries = DB::getQueryLog();
                $queries = array_map(function ($query) {
                    return [
                        'query' => $query['query'],
                        'bindings' => $query['bindings'],
                        'time' => $query['time'] . 'ms'
                    ];
                }, $queries);

                $data = [];
                foreach ($products as $product) {
                    $data[] = $product->toArray();
                }

                return response()->json([
                    'success' => true,
                    'test' => 'subquery',
                    'data' => $data,
                    'queries' => $queries,
                ]);
                break;

            case 'cte':
                // Test CTE (Common Table Expression)
                // Note: Use withCte() for CTE, not with() which is for eager loading
                // withCte() works with both QueryBuilder and ModelQueryBuilder (ORM)
                // Example 1: Using QueryBuilder (raw query)
                // $rows = DB::table('top_rated')
                //     ->withCte('top_rated', function ($q) {
                //         $q->select('product_id')
                //             ->selectRaw('AVG(rating) as avg_rating')
                //             ->table('reviews')
                //             ->where('is_approved', true)
                //             ->groupBy('product_id')
                //             ->havingRaw('AVG(rating) >= 4.5');
                //     })
                //     ->join('products', 'products.id', '=', 'top_rated.product_id')
                //     ->select('products.*', 'top_rated.avg_rating')
                //     ->orderBy('top_rated.avg_rating', 'DESC')
                //     ->limit(10)
                //     ->get()->toArray();

                // Example 2: Using ModelQueryBuilder (ORM) - withCte() works here too!
                // Note: When querying from CTE with joins, results may not hydrate to Model instances
                // Use QueryBuilder for complex CTE queries, or handle as arrays
                // Query from products table (ProductModel::query() already sets table to 'products')
                // Join with CTE to get top rated products
                // Performance optimization:
                // 1. Filter is_approved first (uses index) before GROUP BY
                // 2. Only select necessary columns (product_id, rating)
                // 3. Use composite index on (is_approved, product_id, rating) for optimal performance
                $rows = ProductModel::query()
                    ->withCte('top_rated', function ($q) {
                        $q->select('product_id')
                            ->selectRaw('AVG(rating) as avg_rating')
                            ->table('reviews')
                            ->where('is_approved', true)  // Filter first (uses index)
                            ->whereNotNull('rating')       // Exclude null ratings
                            ->groupBy('product_id')
                            ->havingRaw('AVG(rating) >= 4.5');
                    })
                    ->join('top_rated', 'products.id', '=', 'top_rated.product_id')
                    ->select('products.*', 'top_rated.avg_rating')
                    ->orderBy('top_rated.avg_rating', 'DESC')
                    ->limit(10)
                    ->get();

                // Convert to array - handle both Model instances and arrays
                $data = [];
                foreach ($rows as $row) {
                    if ($row instanceof \Toporia\Framework\Database\ORM\Model) {
                        $data[] = $row->toArray();
                    } else {
                        $data[] = (array) $row;
                    }
                }
                $rows = $data;
                $queries = DB::getQueryLog();
                $queries = array_map(function ($query) {
                    return [
                        'query' => $query['query'],
                        'bindings' => $query['bindings'],
                        'time' => $query['time'] . 'ms'
                    ];
                }, $queries);


                // Convert rows to array format
                $data = [];
                foreach ($rows as $row) {
                    $data[] = (array) $row;
                }

                return response()->json([
                    'queries' => $queries,
                    'success' => true,
                    'test' => 'cte',
                    'data' => $data,
                ]);
                break;

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid test type. Use: aggregates, chunking, eager, subquery, cte',
                ], 400);
        }
    }

    /**
     * Get categories.
     *
     * GET /api/categories
     */
    public function categories(Request $request): JsonResponseInterface
    {
        try {
            DB::enableQueryLog();

            $query = CategoryModel::query();

            if ($request->has('active')) {
                $query->where('is_active', true);
            }

            $categories = $query->withCount('products')
                ->orderBy('sort_order')
                ->get();

            $data = [];
            foreach ($categories as $category) {
                $data[] = $category->toArray();
            }

            $queries = $this->formatQueryLogs(DB::getQueryLog());

            return response()->json([
                'success' => true,
                'data' => $data,
                'queries' => $queries,
            ]);
        } catch (\Throwable $e) {
            $queries = $this->formatQueryLogs(DB::getQueryLog());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'queries' => $queries,
            ], 500);
        }
    }

    /**
     * Get product statistics.
     *
     * GET /api/products/stats
     */
    public function stats(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $stats = [
            'total_products' => ProductModel::count(),
            'active_products' => ProductModel::where('is_active', true)->count(),
            'total_categories' => CategoryModel::count(),
            'total_reviews' => ReviewModel::count(),
            'total_orders' => OrderModel::count(),
            'price_stats' => ProductModel::query()
                ->aggregates([
                    'avg_price' => 'AVG(price)',
                    'max_price' => 'MAX(price)',
                    'min_price' => 'MIN(price)',
                ]),
            'rating_stats' => ProductModel::query()
                ->where('rating_count', '>', 0)
                ->aggregates([
                    'avg_rating' => 'AVG(rating)',
                    'max_rating' => 'MAX(rating)',
                    'min_rating' => 'MIN(rating)',
                ]),
        ];

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'data' => $stats,
            'queries' => $queries,
        ]);
    }

    /**
     * Test performance with large dataset.
     *
     * GET /api/products/performance?limit=1000
     */
    public function performance(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $limit = (int) $request->input('limit', 100);

        $startTime = microtime(true);

        $products = ProductModel::with(['category', 'reviews'])
            ->where('is_active', true)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();

        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2); // milliseconds

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $products->count(),
                'execution_time_ms' => $executionTime,
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ],
            'products' => array_map(fn($p) => $p->toArray(), $products->all()),
            'queries' => $queries,
        ]);
    }

    /**
     * Get top rated products.
     *
     * GET /api/products/top-rated?limit=10
     */
    public function topRated(Request $request): JsonResponseInterface
    {
        $limit = (int) $request->input('limit', 10);

        $products = ProductModel::where('rating_count', '>', 0)
            ->orderBy('rating', 'DESC')
            ->orderBy('rating_count', 'DESC')
            ->with('category')
            ->limit($limit)
            ->get();

        $data = [];
        foreach ($products as $product) {
            $data[] = $product->toArray();
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get products with reviews.
     *
     * GET /api/products/{id}/reviews
     */
    public function reviews(Request $request, int $id): JsonResponseInterface
    {
        try {
            DB::enableQueryLog();

            $product = ProductModel::find($id);

            if (!$product) {
                $queries = $this->formatQueryLogs(DB::getQueryLog());
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                    'queries' => $queries,
                ], 404);
            }

            $reviews = $product->reviews()
                ->where('is_approved', true)
                ->orderBy('rating', 'DESC')
                ->orderBy('created_at', 'DESC')
                ->paginate(20, (int) $request->input('page', 1));

            $queries = $this->formatQueryLogs(DB::getQueryLog());

            return response()->json([
                'success' => true,
                'data' => [
                    'product' => $product->toArray(),
                    'reviews' => array_map(fn($r) => $r->toArray(), $reviews->items()->all()),
                    'pagination' => [
                        'current_page' => $reviews->currentPage(),
                        'total' => $reviews->total(),
                        'last_page' => $reviews->lastPage(),
                    ],
                ],
                'queries' => $queries,
            ]);
        } catch (\Throwable $e) {
            $queries = $this->formatQueryLogs(DB::getQueryLog());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'queries' => $queries,
            ], 500);
        }
    }

    /**
     * Test complex BelongsToMany relationships with pivot data.
     *
     * GET /api/products/test-belongs-to-many
     */
    public function testBelongsToMany(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        // Test various BelongsToMany features
        $results = [];

        // 1. Basic many-to-many with pivot columns
        $productsWithCategories = ProductModel::with(['categories'])
            ->limit(5)
            ->get();

        $results['basic_pivot'] = $productsWithCategories;

        // 2. Complex pivot constraints
        $productsWithFilteredCategories = ProductModel::with(['categories'])
            ->limit(3)
            ->get();

        $results['complex_pivot_constraints'] = $productsWithFilteredCategories;

        // 3. Pivot aggregations (skip for now due to aggregation issues)
        $results['pivot_aggregations'] = [
            'note' => 'Aggregations skipped due to data type issues',
            'total_sort_order' => 0,
            'avg_sort_order' => 0,
            'min_sort_order' => 0,
            'max_sort_order' => 0,
        ];

        // 4. Chunked operations
        $chunkResults = [];
        ProductModel::find(1)?->categories()->chunk(2, function ($categories) use (&$chunkResults) {
            $chunkResults[] = $categories->count();
            return true;
        });
        $results['chunk_results'] = $chunkResults;

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'BelongsToMany relationship tests',
            'data' => $results,
            'queries' => $queries
        ]);
    }

    /**
     * Test HasMany and HasOne relationships.
     *
     * GET /api/products/test-has-relationships
     */
    public function testHasRelationships(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $results = [];

        // 1. HasMany with complex queries
        $productsWithReviews = ProductModel::with(['reviews' => function ($query) {
            $query->where('rating', '>=', 4)
                ->whereDate('created_at', '>=', '2024-01-01')
                ->orderBy('created_at', 'desc')
                ->limit(5);
        }])
            ->limit(3)
            ->get();

        $results['has_many_reviews'] = $productsWithReviews;

        // 2. HasMany aggregations
        $product = ProductModel::first();
        if ($product) {
            $results['review_stats'] = [
                'total_reviews' => $product->reviews()->count(),
                'avg_rating' => $product->reviews()->avg('rating'),
                'high_ratings' => $product->reviews()->where('rating', '>=', 4)->count(),
                'recent_reviews' => $product->reviews()->whereDate('created_at', '>=', '2024-01-01')->count(),
            ];
        }

        // 3. HasMany with chunked processing
        $chunkData = [];
        ProductModel::first()?->reviews()->chunkById(2, function ($reviews) use (&$chunkData) {
            $chunkData[] = [
                'count' => $reviews->count(),
                'avg_rating' => $reviews->avg('rating')
            ];
            return true;
        });
        $results['chunked_reviews'] = $chunkData;

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'HasMany relationship tests',
            'data' => $results,
            'queries' => $queries
        ]);
    }

    /**
     * Test BelongsTo relationships.
     *
     * GET /api/products/test-belongs-to
     */
    public function testBelongsTo(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $results = [];

        // 1. Basic BelongsTo with constraints
        $productsWithCategory = ProductModel::with(['category' => function ($query) {
            $query->where('is_active', true)
                ->select('id', 'name', 'slug', 'description');
        }])
            ->whereHas('category', function ($query) {
                $query->where('name', 'like', '%Electronics%');
            })
            ->limit(5)
            ->get();

        $results['products_with_category'] = $productsWithCategory;

        // 2. BelongsTo existence checks
        $results['category_stats'] = [
            'products_with_category' => ProductModel::whereNotNull('category_id')->count(),
            'products_without_category' => ProductModel::whereNull('category_id')->count(),
            'electronics_products' => ProductModel::whereHas('category', function ($query) {
                $query->where('name', 'like', '%Electronics%');
            })->count(),
        ];

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'BelongsTo relationship tests',
            'data' => $results,
            'queries' => $queries
        ]);
    }

    /**
     * Test complex queries with multiple relationships.
     *
     * GET /api/products/test-complex-queries
     */
    public function testComplexQueries(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $results = [];

        // 1. Multi-level eager loading
        $complexProducts = ProductModel::with([
            'category',
            'categories.products' => function ($query) {
                $query->limit(3);
            },
            'reviews' => function ($query) {
                $query->where('rating', '>=', 4)->limit(5);
            }
        ])
            ->whereHas('reviews', function ($query) {
                $query->where('rating', '>=', 4);
            })
            ->where('is_active', true)
            ->limit(3)
            ->get();

        $results['multi_level_eager_loading'] = $complexProducts;

        // 2. Complex WHERE conditions with relationships
        $advancedQuery = ProductModel::where('price', '>', 100)
            ->where('stock', '>', 0)
            ->whereHas('reviews', function ($query) {
                $query->where('rating', '>=', 4)
                    ->whereDate('created_at', '>=', '2024-01-01');
            })
            ->orderBy('rating', 'desc')
            ->limit(5)
            ->get();

        $results['advanced_query'] = $advancedQuery;

        // 3. Subquery selections
        $productsWithSubqueries = ProductModel::select('products.*')
            ->limit(5)
            ->get();

        $results['subquery_selections'] = $productsWithSubqueries;

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'Complex query tests',
            'data' => $results,
            'queries' => $queries
        ]);
    }

    /**
     * Test sync operations for BelongsToMany.
     *
     * POST /api/products/test-sync-operations
     */
    public function testSyncOperations(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $results = [];
        $productId = $request->input('product_id', 1);

        $product = ProductModel::find($productId);
        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // 1. Basic sync
        $syncResult1 = $product->categories()->sync([1, 2, 3]);

        $results['basic_sync'] = $syncResult1;

        // 2. Sync with pivot data
        $syncResult2 = $product->categories()->sync([
            1 => ['sort_order' => 1],
            2 => ['sort_order' => 2],
            4 => ['sort_order' => 3],
        ]);
        $results['sync_with_pivot'] = $syncResult2;

        // 3. Sync without detaching
        $syncResult3 = $product->categories()->syncWithoutDetaching([5, 6]);
        $results['sync_without_detaching'] = $syncResult3;

        // 4. Toggle relationships
        $toggleResult = $product->categories()->toggle([2, 7, 8]);
        $results['toggle_result'] = $toggleResult;

        // 5. Update existing pivot
        $updateResult = $product->categories()->updateExistingPivot(1, [
            'sort_order' => 10
        ]);
        $results['update_pivot'] = $updateResult;

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'Sync operations tests',
            'data' => $results,
            'queries' => $queries
        ]);
    }

    /**
     * Test performance with large datasets.
     *
     * GET /api/products/test-performance
     */
    public function testPerformance(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $results = [];
        $startTime = microtime(true);

        // 1. Chunked processing test
        $chunkCount = 0;
        $totalProcessed = 0;

        ProductModel::chunk(10, function ($products) use (&$chunkCount, &$totalProcessed) {
            $chunkCount++;
            $totalProcessed += $products->count();

            // Simulate processing
            foreach ($products as $product) {
                $product->getAttribute('title');
            }

            return true;
        });

        $results['chunk_processing'] = [
            'chunks' => $chunkCount,
            'total_processed' => $totalProcessed,
            'time_taken' => microtime(true) - $startTime
        ];

        // 2. Eager loading vs N+1 comparison
        $eagerStart = microtime(true);
        $eagerProducts = ProductModel::with(['category', 'reviews'])->limit(20)->get();
        $eagerTime = microtime(true) - $eagerStart;

        $lazyStart = microtime(true);
        $lazyProducts = ProductModel::limit(20)->get();
        // Skip lazy loading test due to data structure issues
        $lazyTime = microtime(true) - $lazyStart;

        $results['eager_vs_lazy'] = [
            'eager_loading_time' => $eagerTime,
            'lazy_loading_time' => $lazyTime,
            'performance_improvement' => round(($lazyTime - $eagerTime) / $lazyTime * 100, 2) . '%'
        ];

        // 3. Complex query performance
        $complexStart = microtime(true);
        $complexQuery = ProductModel::with(['category', 'categories', 'reviews'])
            ->whereHas('reviews', function ($query) {
                $query->where('rating', '>=', 4);
            })
            ->limit(10)
            ->get();
        $complexTime = microtime(true) - $complexStart;

        $results['complex_query_performance'] = [
            'time_taken' => $complexTime,
            'results_count' => $complexQuery->count()
        ];

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'Performance tests',
            'data' => $results,
            'total_time' => microtime(true) - $startTime,
            'queries' => $queries
        ]);
    }

    /**
     * Test pivot table validation and debugging.
     *
     * GET /api/products/test-pivot-validation
     */
    public function testPivotValidation(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $results = [];
        $productId = $request->input('product_id', 1);

        $product = ProductModel::find($productId);
        if (!$product) {
            $queries = $this->formatQueryLogs(DB::getQueryLog());
            return response()->json(['error' => 'Product not found', 'queries' => $queries], 404);
        }

        // 1. Validate pivot table structure
        $validation = $product->categories()->validatePivotStructure();
        $results['pivot_validation'] = $validation;

        // 2. Test pivot existence
        $results['pivot_existence_tests'] = [
            'category_1_exists' => $product->categories()->pivotExists(1),
            'category_2_exists' => $product->categories()->pivotExists(2),
            'featured_category_1' => $product->categories()->pivotExists(1, ['is_featured' => true]),
        ];

        // 3. Get distinct pivot values
        $results['distinct_pivot_values'] = [
            'sort_orders' => $product->categories()->distinctPivot('sort_order'),
            'featured_flags' => $product->categories()->distinctPivot('is_featured'),
        ];

        // 4. Pivot query builder
        $pivotQuery = $product->categories()->pivotQuery()
            ->where('sort_order', '>', 0)
            ->orderBy('sort_order')
            ->get();
        $results['pivot_query_results'] = $pivotQuery;

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'Pivot validation tests',
            'data' => $results,
            'queries' => $queries
        ]);
    }

    /**
     * Create a new product with relationships.
     *
     * POST /api/products
     * Body: {
     *   "title": "Product Name",
     *   "price": 99.99,
     *   "category_ids": [1, 2],           // belongsToMany categories
     *   "tag_ids": [1, 2, 3],            // belongsToMany tags
     *   "polymorphic_tag_ids": [4, 5],   // morphToMany allTags
     *   "related_product_ids": [10, 11]  // belongsToMany relatedProducts
     * }
     */
    public function store(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $data = $request->all();

        $result = DB::transaction(function () use ($data) {
            // Create product
            $product = ProductModel::create([
                'title' => $data['title'] ?? 'Test Product',
                'slug' => $data['slug'] ?? strtolower(str_replace(' ', '-', $data['title'] ?? 'test-product')),
                'price' => $data['price'] ?? 99.99,
                'sale_price' => $data['sale_price'] ?? null,
                'stock' => $data['stock'] ?? 100,
                'description' => $data['description'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'status' => $data['status'] ?? 'active',
            ]);

            // Attach belongsToMany categories
            if (isset($data['category_ids']) && is_array($data['category_ids'])) {
                // Validate category IDs exist
                $validCategoryIds = CategoryModel::whereIn('id', $data['category_ids'])
                    ->get()
                    ->pluck('id')
                    ->values()
                    ->all();
                if (count($validCategoryIds) !== count($data['category_ids'])) {
                    $invalidIds = array_diff($data['category_ids'], $validCategoryIds);
                    throw new \RuntimeException('Invalid category IDs: ' . implode(', ', $invalidIds));
                }
                $product->categories()->attach($validCategoryIds);
            }

            // Attach belongsToMany tags (with pivot data)
            if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
                // Validate tag IDs exist - only attach valid ones
                $validTagIds = TagModel::whereIn('id', $data['tag_ids'])
                    ->get()
                    ->pluck('id')
                    ->values()
                    ->all();

                // Skip invalid tag IDs instead of throwing error
                if (count($validTagIds) !== count($data['tag_ids'])) {
                    $invalidIds = array_diff($data['tag_ids'], $validTagIds);
                    // Log warning but continue with valid tags only
                    // Invalid tag IDs are silently skipped
                }

                // Only proceed if there are valid tag IDs
                if (!empty($validTagIds)) {
                    $pivotData = [];
                    $createdBy = null;

                    // Validate created_by if provided
                    if (isset($data['created_by'])) {
                        $user = UserModel::find($data['created_by']);
                        if ($user) {
                            $createdBy = $data['created_by'];
                        }
                        // If user doesn't exist, created_by will be null (foreign key allows NULL)
                    }

                    foreach ($validTagIds as $tagId) {
                        $tagPivotData = [
                            'created_at' => now()->toDateTimeString(),
                        ];

                        // Only add created_by if user exists
                        if ($createdBy !== null) {
                            $tagPivotData['created_by'] = $createdBy;
                        }

                        $pivotData[$tagId] = $tagPivotData;
                    }
                    $product->tags()->attach($pivotData);
                }
            }

            // Attach polymorphic many-to-many tags (morphToMany)
            if (isset($data['polymorphic_tag_ids']) && is_array($data['polymorphic_tag_ids'])) {

                // Validate tag IDs exist - only attach valid ones
                $validTagIds = TagModel::whereIn('id', $data['polymorphic_tag_ids'])
                    ->get()
                    ->pluck('id')
                    ->values()
                    ->all();

                // Skip invalid tag IDs instead of throwing error
                if (count($validTagIds) !== count($data['polymorphic_tag_ids'])) {
                    $invalidIds = array_diff($data['polymorphic_tag_ids'], $validTagIds);
                    // Log warning but continue with valid tags only
                    // Invalid tag IDs are silently skipped
                }

                // Only proceed if there are valid tag IDs
                if (!empty($validTagIds)) {
                    $product->allTags()->attach($validTagIds);
                }
            }

            // Attach related products (self-referencing many-to-many)
            if (isset($data['related_product_ids']) && is_array($data['related_product_ids'])) {
                // Validate product IDs exist
                $validProductIds = ProductModel::whereIn('id', $data['related_product_ids'])
                    ->get()
                    ->pluck('id')
                    ->values()
                    ->all();
                if (count($validProductIds) !== count($data['related_product_ids'])) {
                    $invalidIds = array_diff($data['related_product_ids'], $validProductIds);
                    throw new \RuntimeException('Invalid related product IDs: ' . implode(', ', $invalidIds));
                }

                // Validate relation_type (enum: similar, complementary, alternative, accessory)
                $allowedRelationTypes = ['similar', 'complementary', 'alternative', 'accessory'];

                // Map common aliases to valid relation types
                $relationTypeMapping = [
                    'recommended' => 'similar',
                    'related' => 'similar',
                    'suggested' => 'similar',
                    'upsell' => 'complementary',
                    'cross-sell' => 'complementary',
                ];

                $relationType = $data['relation_type'] ?? 'similar';

                // If the provided type is in the mapping, use the mapped value
                if (isset($relationTypeMapping[$relationType])) {
                    $relationType = $relationTypeMapping[$relationType];
                }

                // Validate that the final relation_type is allowed
                if (!in_array($relationType, $allowedRelationTypes, true)) {
                    throw new \RuntimeException(
                        'Invalid relation_type: "' . ($data['relation_type'] ?? 'null') . '". ' .
                            'Allowed values are: ' . implode(', ', $allowedRelationTypes) . '. ' .
                            'Supported aliases: ' . implode(', ', array_keys($relationTypeMapping))
                    );
                }

                // Validate strength (must be between 0.00 and 1.00)
                $strength = $data['strength'] ?? 0.8;
                $strength = (float) $strength;
                if ($strength < 0.00 || $strength > 1.00) {
                    throw new \RuntimeException(
                        'Invalid strength: ' . $strength . '. ' .
                            'Strength must be between 0.00 and 1.00'
                    );
                }

                $pivotData = [];
                foreach ($validProductIds as $relatedId) {
                    $pivotData[$relatedId] = [
                        'relation_type' => $relationType,
                        'strength' => $strength,
                    ];
                }
                $product->relatedProducts()->attach($pivotData);
            }

            // Load relationships for response
            $product->load(['category', 'categories', 'tags', 'allTags', 'relatedProducts']);

            return $product;
        });

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $result->toArray(),
            'queries' => $queries,
        ], 201);
    }

    /**
     * Update product relationships (sync operations).
     *
     * PUT /api/products/{id}/relationships
     * Body: {
     *   "category_ids": [1, 2, 3],        // Sync categories (detach others)
     *   "tag_ids": [1, 2],                // Sync tags
     *   "polymorphic_tag_ids": [4, 5, 6], // Sync polymorphic tags
     *   "related_product_ids": [10, 11]   // Sync related products
     * }
     */
    public function updateRelationships(Request $request, int $id): JsonResponseInterface
    {
        DB::enableQueryLog();

        $product = ProductModel::find($id);

        if (!$product) {
            $queries = $this->formatQueryLogs(DB::getQueryLog());
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'queries' => $queries,
            ], 404);
        }

        $data = $request->all();
        $results = [];

        // Sync belongsToMany categories
        if (isset($data['category_ids']) && is_array($data['category_ids'])) {
            // Validate category IDs exist before syncing
            $validCategoryIds = CategoryModel::whereIn('id', $data['category_ids'])
                ->get()
                ->pluck('id')
                ->values()
                ->all();

            if (count($validCategoryIds) !== count($data['category_ids'])) {
                $invalidIds = array_diff($data['category_ids'], $validCategoryIds);
                return response()->json([
                    'success' => false,
                    'message' => 'Some category IDs do not exist: ' . implode(', ', $invalidIds),
                    'invalid_ids' => array_values($invalidIds),
                ], 422);
            }

            $synced = $product->categories()->sync($validCategoryIds);
            $results['categories'] = [
                'attached' => $synced['attached'] ?? [],
                'detached' => $synced['detached'] ?? [],
                'updated' => $synced['updated'] ?? [],
            ];
        }

        // Sync belongsToMany tags
        if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
            // Validate tag IDs exist before syncing
            $validTagIds = TagModel::whereIn('id', $data['tag_ids'])
                ->get()
                ->pluck('id')
                ->values()
                ->all();

            if (count($validTagIds) !== count($data['tag_ids'])) {
                $invalidIds = array_diff($data['tag_ids'], $validTagIds);
                return response()->json([
                    'success' => false,
                    'message' => 'Some tag IDs do not exist: ' . implode(', ', $invalidIds),
                    'invalid_ids' => array_values($invalidIds),
                ], 422);
            }

            $synced = $product->tags()->sync($validTagIds);
            $results['tags'] = [
                'attached' => $synced['attached'] ?? [],
                'detached' => $synced['detached'] ?? [],
                'updated' => $synced['updated'] ?? [],
            ];
        }

        // Sync polymorphic many-to-many tags (morphToMany)
        if (isset($data['polymorphic_tag_ids']) && is_array($data['polymorphic_tag_ids'])) {
            // Validate tag IDs exist before syncing
            $validTagIds = TagModel::whereIn('id', $data['polymorphic_tag_ids'])
                ->get()
                ->pluck('id')
                ->values()
                ->all();

            if (count($validTagIds) !== count($data['polymorphic_tag_ids'])) {
                $invalidIds = array_diff($data['polymorphic_tag_ids'], $validTagIds);
                return response()->json([
                    'success' => false,
                    'message' => 'Some polymorphic tag IDs do not exist: ' . implode(', ', $invalidIds),
                    'invalid_ids' => array_values($invalidIds),
                ], 422);
            }

            $synced = $product->allTags()->sync($validTagIds);
            $results['polymorphic_tags'] = [
                'attached' => $synced['attached'] ?? [],
                'detached' => $synced['detached'] ?? [],
                'updated' => $synced['updated'] ?? [],
            ];
        }

        // Sync related products
        if (isset($data['related_product_ids']) && is_array($data['related_product_ids'])) {
            // Validate product IDs exist before syncing (exclude current product)
            $validProductIds = ProductModel::whereIn('id', $data['related_product_ids'])
                ->where('id', '!=', $product->id) // Prevent self-reference
                ->get()
                ->pluck('id')
                ->values()
                ->all();

            if (count($validProductIds) !== count($data['related_product_ids'])) {
                $invalidIds = array_diff($data['related_product_ids'], $validProductIds);
                // Check if any invalid ID is the current product itself
                if (in_array($product->id, $data['related_product_ids'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot relate product to itself. Invalid IDs: ' . implode(', ', $invalidIds),
                        'invalid_ids' => array_values($invalidIds),
                    ], 422);
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Some related product IDs do not exist: ' . implode(', ', $invalidIds),
                    'invalid_ids' => array_values($invalidIds),
                ], 422);
            }

            $synced = $product->relatedProducts()->sync($validProductIds);
            $results['related_products'] = [
                'attached' => $synced['attached'] ?? [],
                'detached' => $synced['detached'] ?? [],
                'updated' => $synced['updated'] ?? [],
            ];
        }

        // Reload relationships
        $product->load(['categories', 'tags', 'allTags', 'relatedProducts']);

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'Relationships updated successfully',
            'data' => [
                'product' => $product->toArray(),
                'sync_results' => $results,
            ],
            'queries' => $queries,
        ]);
    }

    /**
     * Test polymorphic relationships (morphToMany).
     *
     * GET /api/products/{id}/polymorphic-tags
     */
    public function getPolymorphicTags(Request $request, int $id): JsonResponseInterface
    {
        DB::enableQueryLog();

        $product = ProductModel::find($id);

        if (!$product) {
            $queries = $this->formatQueryLogs(DB::getQueryLog());
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'queries' => $queries,
            ], 404);
        }

        // Load polymorphic tags
        $product->load('allTags');

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $product->id,
                'product_title' => $product->title,
                'polymorphic_tags' => $product->allTags->toArray(),
                'polymorphic_tags_count' => $product->allTags->count(),
            ],
            'queries' => $queries,
        ]);
    }

    /**
     * Attach polymorphic tags to product.
     *
     * POST /api/products/{id}/polymorphic-tags
     * Body: {
     *   "tag_ids": [1, 2, 3]
     * }
     */
    public function attachPolymorphicTags(Request $request, int $id): JsonResponseInterface
    {
        DB::enableQueryLog();

        $product = ProductModel::find($id);

        if (!$product) {
            $queries = $this->formatQueryLogs(DB::getQueryLog());
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'queries' => $queries,
            ], 404);
        }

        $data = $request->json();
        $tagIds = $data['tag_ids'] ?? [];

        if (empty($tagIds) || !is_array($tagIds)) {
            $queries = $this->formatQueryLogs(DB::getQueryLog());
            return response()->json([
                'success' => false,
                'message' => 'tag_ids must be a non-empty array',
                'queries' => $queries,
            ], 400);
        }

        // Attach polymorphic tags
        $product->allTags()->attach($tagIds);

        // Reload to get updated tags
        $product->load('allTags');

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'Polymorphic tags attached successfully',
            'data' => [
                'product_id' => $product->id,
                'attached_tag_ids' => $tagIds,
                'polymorphic_tags' => $product->allTags->toArray(),
            ],
            'queries' => $queries,
        ]);
    }

    /**
     * Sync polymorphic tags for product.
     *
     * PUT /api/products/{id}/polymorphic-tags
     * Body: {
     *   "tag_ids": [1, 2, 3]  // Will detach others not in this list
     * }
     */
    public function syncPolymorphicTags(Request $request, int $id): JsonResponseInterface
    {
        DB::enableQueryLog();

        $product = ProductModel::find($id);

        if (!$product) {
            $queries = $this->formatQueryLogs(DB::getQueryLog());
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'queries' => $queries,
            ], 404);
        }

        $data = $request->json();
        $tagIds = $data['tag_ids'] ?? [];

        // Sync polymorphic tags (detach others by default)
        $synced = $product->allTags()->sync($tagIds);

        // Reload to get updated tags
        $product->load('allTags');

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'Polymorphic tags synced successfully',
            'data' => [
                'product_id' => $product->id,
                'sync_results' => [
                    'attached' => $synced['attached'] ?? [],
                    'detached' => $synced['detached'] ?? [],
                    'updated' => $synced['updated'] ?? [],
                ],
                'polymorphic_tags' => $product->allTags->toArray(),
            ],
            'queries' => $queries,
        ]);
    }

    /**
     * Detach polymorphic tags from product.
     *
     * DELETE /api/products/{id}/polymorphic-tags
     * Body: {
     *   "tag_ids": [1, 2]  // Optional: specific tags to detach. If empty, detach all.
     * }
     */
    public function detachPolymorphicTags(Request $request, int $id): JsonResponseInterface
    {
        DB::enableQueryLog();

        $product = ProductModel::find($id);

        if (!$product) {
            $queries = $this->formatQueryLogs(DB::getQueryLog());
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'queries' => $queries,
            ], 404);
        }

        $data = $request->json();
        $tagIds = $data['tag_ids'] ?? null;

        // Detach specific tags or all tags
        if ($tagIds !== null && is_array($tagIds)) {
            $product->allTags()->detach($tagIds);
            $message = 'Specific polymorphic tags detached successfully';
        } else {
            $product->allTags()->detach();
            $message = 'All polymorphic tags detached successfully';
        }

        // Reload to get updated tags
        $product->load('allTags');

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'product_id' => $product->id,
                'detached_tag_ids' => $tagIds,
                'polymorphic_tags' => $product->allTags->toArray(),
            ],
            'queries' => $queries,
        ]);
    }

    /**
     * Test all relationship types for a product.
     *
     * GET /api/products/{id}/relationships
     */
    public function getAllRelationships(Request $request, int $id): JsonResponseInterface
    {
        DB::enableQueryLog();

        $product = ProductModel::with([
            'category',              // belongsTo
            'categories',            // belongsToMany
            'reviews',               // hasMany
            'approvedReviews',       // hasMany with constraint
            'tags',                  // belongsToMany with pivot
            'allTags',               // morphToMany (polymorphic)
            'relatedProducts',       // belongsToMany self-referencing
            'favoritedBy',           // belongsToMany (users)
        ])->find($id);

        if (!$product) {
            $queries = $this->formatQueryLogs(DB::getQueryLog());
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'queries' => $queries,
            ], 404);
        }

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'title' => $product->title,
                    'price' => $product->price,
                ],
                'relationships' => [
                    'category' => $product->category ? $product->category->toArray() : null,
                    'categories' => $product->categories->toArray(),
                    'categories_count' => $product->categories->count(),
                    'reviews' => $product->reviews->toArray(),
                    'reviews_count' => $product->reviews->count(),
                    'approved_reviews' => $product->approvedReviews->toArray(),
                    'approved_reviews_count' => $product->approvedReviews->count(),
                    'tags' => $product->tags->toArray(),
                    'tags_count' => $product->tags->count(),
                    'polymorphic_tags' => $product->allTags->toArray(),
                    'polymorphic_tags_count' => $product->allTags->count(),
                    'related_products' => $product->relatedProducts->toArray(),
                    'related_products_count' => $product->relatedProducts->count(),
                    'favorited_by' => $product->favoritedBy->toArray(),
                    'favorited_by_count' => $product->favoritedBy->count(),
                ],
            ],
            'queries' => $queries,
        ]);
    }

    /**
     * Create a new tag.
     *
     * POST /api/tags
     * Body: {
     *   "name": "Tag Name",
     *   "slug": "tag-name",  // Optional: auto-generated from name
     *   "description": "Tag description",
     *   "color": "#FF5733",
     *   "is_active": true
     * }
     */
    public function createTag(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $data = $request->json();

        // Validate required fields
        if (empty($data['name'])) {
            $queries = $this->formatQueryLogs(DB::getQueryLog());
            return response()->json([
                'success' => false,
                'message' => 'Tag name is required',
                'queries' => $queries,
            ], 400);
        }

        // Auto-generate slug if not provided
        $slug = $data['slug'] ?? strtolower(str_replace(' ', '-', $data['name']));

        // Create tag
        $tag = TagModel::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? '#3498db',
            'is_active' => $data['is_active'] ?? true,
            'usage_count' => 0,
        ]);

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'Tag created successfully',
            'data' => $tag->toArray(),
            'queries' => $queries,
        ], 201);
    }

    /**
     * Get all tags.
     *
     * GET /api/tags?active=true&popular=true&limit=20
     */
    public function getTags(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $query = TagModel::query();

        // Filter active tags
        if ($request->get('active') === 'true') {
            $query->where('is_active', true);
        }

        // Get popular tags
        if ($request->get('popular') === 'true') {
            $limit = (int) ($request->get('limit', 10));
            $tags = TagModel::popular($limit)->get();
        } else {
            $tags = $query->orderBy('name', 'ASC')->get();
        }

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'data' => $tags->toArray(),
            'count' => $tags->count(),
            'queries' => $queries,
        ]);
    }

    /**
     * Get single tag with relationships.
     *
     * GET /api/tags/{id}?with=products,categories
     */
    public function getTag(Request $request, int $id): JsonResponseInterface
    {
        DB::enableQueryLog();

        $query = TagModel::where('id', $id);

        // Eager loading
        $with = $request->input('with', '');
        if ($with) {
            $relations = explode(',', $with);
            $relations = array_map('trim', $relations);
            $query->with(...$relations);
        }

        $tag = $query->first();

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'Tag not found',
                'queries' => $queries,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $tag->toArray(),
            'queries' => $queries,
        ]);
    }

    // =========================================================================
    // POLYMORPHIC RELATIONSHIPS TESTING ENDPOINTS
    // =========================================================================

    /**
     * Test MorphOne relationship (Post/Video has one Image) with complex queries.
     *
     * GET /api/polymorphic/test-morph-one?type=post&id=1&min_width=800&min_height=600&min_size=102400
     * GET /api/polymorphic/test-morph-one?type=video&id=1&min_width=1280&min_height=720
     *
     * Parameters:
     * - type: 'post' or 'video' (required)
     * - id: ID of post or video (required, must exist in database)
     * - min_width: Minimum image width (optional, default: 0)
     * - min_height: Minimum image height (optional, default: 0)
     * - min_size: Minimum image size in bytes (optional, default: 0)
     * - has_alt_text: Filter images with alt_text (optional, default: false, values: true/false)
     * - url_contains: Filter images by URL containing string (optional)
     *
     * Example with complex queries:
     * GET /api/polymorphic/test-morph-one?type=post&id=1&min_width=800&min_height=600&has_alt_text=true
     * GET /api/polymorphic/test-morph-one?type=video&id=1&min_width=1280&min_height=720&url_contains=thumbnail
     */
    public function testMorphOne(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $type = $request->get('type', 'post');
        $id = (int) $request->get('id', 1);
        $minWidth = (int) $request->get('min_width', 0);
        $minHeight = (int) $request->get('min_height', 0);
        $minSize = (int) $request->get('min_size', 0);
        $hasAltText = $request->get('has_alt_text', 'false') === 'true';
        $urlContains = $request->get('url_contains', '');
        $publishedOnly = $request->get('published_only', 'false') === 'true';
        $minViews = (int) $request->get('min_views', 0);

        $results = [];

        if ($type === 'post') {
            // Test 1: Basic with() simple
            $postBasic = PostModel::with('image')->find($id);
            $results['basic_with'] = $postBasic ? [
                'post' => $postBasic->toArray(),
                'image' => $postBasic->image ? $postBasic->image->toArray() : null,
            ] : ['error' => 'Post not found'];

            // Test 2: Complex with() callback - multiple conditions
            $postComplex = PostModel::with(['image' => function ($q) use ($minWidth, $minHeight, $minSize, $hasAltText, $urlContains) {
                if ($minWidth > 0) {
                    $q->where('width', '>=', $minWidth);
                }
                if ($minHeight > 0) {
                    $q->where('height', '>=', $minHeight);
                }
                if ($minSize > 0) {
                    $q->where('size', '>=', $minSize);
                }
                if ($hasAltText) {
                    $q->whereNotNull('alt_text')->where('alt_text', '!=', '');
                }
                if (!empty($urlContains)) {
                    $q->where('url', 'like', '%' . $urlContains . '%');
                }
                $q->orderBy('size', 'DESC');
            }])->find($id);
            $results['complex_with_callback'] = $postComplex ? [
                'post' => $postComplex->toArray(),
                'image' => $postComplex->image ? $postComplex->image->toArray() : null,
            ] : ['error' => 'Post not found'];

            // Test 3: whereHas with complex conditions
            $postsWithImage = PostModel::whereHas('image', function ($q) use ($minWidth, $minHeight, $hasAltText) {
                if ($minWidth > 0) {
                    $q->where('width', '>=', $minWidth);
                }
                if ($minHeight > 0) {
                    $q->where('height', '>=', $minHeight);
                }
                if ($hasAltText) {
                    $q->whereNotNull('alt_text');
                }
                $q->where('size', '>', 0);
            })
                ->where('is_published', true)
                ->where('views', '>', 0)
                ->with(['image' => function ($q) {
                    $q->orderBy('size', 'DESC');
                }])
                ->limit(5)
                ->get();
            $results['where_has_complex'] = $postsWithImage->map(function ($p) {
                return [
                    'post' => $p->toArray(),
                    'image' => $p->image ? $p->image->toArray() : null,
                ];
            })->all();

            // Test 4: Nested whereHas with multiple conditions
            $postsWithLargeImages = PostModel::whereHas('image', function ($q) {
                $q->where('width', '>=', 800)
                    ->where('height', '>=', 600)
                    ->where(function ($subQ) {
                        $subQ->where('size', '>=', 100000)
                            ->orWhere('url', 'like', '%jpg%');
                    });
            })
                ->where(function ($q) {
                    $q->where('views', '>', 100)
                        ->orWhere('is_published', true);
                })
                ->with(['image' => function ($q) {
                    $q->select('id', 'imageable_type', 'imageable_id', 'url', 'width', 'height', 'size', 'alt_text')
                        ->orderBy('size', 'DESC');
                }])
                ->orderBy('views', 'DESC')
                ->limit(10)
                ->get();
            $results['nested_where_has'] = $postsWithLargeImages->map(function ($p) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'views' => $p->views,
                    'image' => $p->image ? $p->image->toArray() : null,
                ];
            })->all();
        } elseif ($type === 'video') {
            // Test 1: Basic with() simple
            // $videoBasic = VideoModel::with('image')->find($id);
            // $results['basic_with'] = $videoBasic ? [
            //     'video' => $videoBasic->toArray(),
            //     'image' => $videoBasic->image ? $videoBasic->image->toArray() : null,
            // ] : ['error' => 'Video not found'];

            // // Test 2: Complex with() callback - multiple conditions
            // $videoComplex = VideoModel::with(['image' => function ($q) use ($minWidth, $minHeight, $minSize, $hasAltText, $urlContains) {
            //     if ($minWidth > 0) {
            //         $q->where('width', '>=', $minWidth);
            //     }
            //     if ($minHeight > 0) {
            //         $q->where('height', '>=', $minHeight);
            //     }
            //     if ($minSize > 0) {
            //         $q->where('size', '>=', $minSize);
            //     }
            //     if ($hasAltText) {
            //         $q->whereNotNull('alt_text')->where('alt_text', '!=', '');
            //     }
            //     if (!empty($urlContains)) {
            //         $q->where('url', 'like', '%' . $urlContains . '%');
            //     }
            //     $q->orderBy('size', 'DESC');
            // }])->find($id);
            // $results['complex_with_callback'] = $videoComplex ? [
            //     'video' => $videoComplex->toArray(),
            //     'image' => $videoComplex->image ? $videoComplex->image->toArray() : null,
            // ] : ['error' => 'Video not found'];

            // // Test 3: whereHas with complex conditions
            // $videosWithImage = VideoModel::whereHas('image', function ($q) use ($minWidth, $minHeight, $hasAltText) {
            //     if ($minWidth > 0) {
            //         $q->where('width', '>=', $minWidth);
            //     }
            //     if ($minHeight > 0) {
            //         $q->where('height', '>=', $minHeight);
            //     }
            //     if ($hasAltText) {
            //         $q->whereNotNull('alt_text');
            //     }
            //     $q->where('size', '>', 0);
            // })
            //     ->where('is_published', true)
            //     ->where('duration', '>', 0)
            //     ->with(['image' => function ($q) {
            //         $q->orderBy('size', 'DESC');
            //     }])
            //     ->limit(5)
            //     ->get();
            // $results['where_has_complex'] = $videosWithImage->map(function ($v) {
            //     return [
            //         'video' => $v->toArray(),
            //         'image' => $v->image ? $v->image->toArray() : null,
            //     ];
            // })->all();

            // // Test 4: Nested whereHas with multiple conditions
            // $videosWithLargeImages = VideoModel::whereHas('image', function ($q) {
            //     $q->where('width', '>=', 1280)
            //         ->where('height', '>=', 720)
            //         ->where(function ($subQ) {
            //             $subQ->where('size', '>=', 200000)
            //                 ->orWhere('url', 'like', '%thumbnail%');
            //         });
            // })
            //     ->where(function ($q) {
            //         $q->where('views', '>', 500)
            //             ->orWhere('is_published', true);
            //     })
            //     ->where('duration', '>', 60)
            //     ->with(['image' => function ($q) {
            //         $q->select('id', 'imageable_type', 'imageable_id', 'url', 'width', 'height', 'size', 'alt_text')
            //             ->orderBy('size', 'DESC');
            //     }])
            //     ->orderBy('views', 'DESC')
            //     ->limit(10)
            //     ->get();
            // $results['nested_where_has'] = $videosWithLargeImages->map(function ($v) {
            //     return [
            //         'id' => $v->id,
            //         'title' => $v->title,
            //         'views' => $v->views,
            //         'duration' => $v->duration,
            //         'image' => $v->image ? $v->image->toArray() : null,
            //     ];
            // })->all();

            // Test 5: Nested relationship - Post with image and image has imageable (nested MorphTo)
            // $postsWithNestedImage = VideoModel::whereHas('image', function ($q) use ($minWidth, $minHeight) {
            //     if ($minWidth > 0) {
            //         $q->where('width', '>=', $minWidth);
            //     }
            //     if ($minHeight > 0) {
            //         $q->where('height', '>=', $minHeight);
            //     }
            // })
            //     ->with([
            //         'image' => function ($q) {
            //             $q->select('id', 'imageable_type', 'imageable_id', 'url', 'width', 'height', 'size', 'alt_text')
            //                 ->orderBy('size', 'DESC');
            //         },
            //         'image.imageable' => function (MorphTo $morphTo) use ($publishedOnly, $minViews) {
            //             $morphTo->constrain([
            //                 PostModel::class => function ($query) use ($publishedOnly, $minViews) {
            //                     if ($publishedOnly) {
            //                         $query->where('is_published', true);
            //                     }
            //                     if ($minViews > 0) {
            //                         $query->where('views', '>=', $minViews);
            //                     }
            //                 },
            //                 VideoModel::class => function ($query) use ($publishedOnly, $minViews) {
            //                     if ($publishedOnly) {
            //                         $query->where('is_published', true);
            //                     }
            //                     if ($minViews > 0) {
            //                         $query->where('views', '>=', $minViews);
            //                     }
            //                 },
            //             ]);
            //         }
            //     ])
            //     ->with('tags', function ($q) {
            //         $q->select('tags.id', 'tags.name', 'tags.slug')->orderBy('tags.name', 'ASC');
            //     })
            //     ->where('is_published', true)
            //     ->limit(5)
            //     ->get();


            // $results['nested_image_imageable'] = $postsWithNestedImage->all();

            // Test 6: Deep nested - Post with image, image has imageable, and imageable has relationships
            // Use whereHas with whereHasMorph to properly handle polymorphic relationship
            $postsWithDeepNested = PostModel::whereHas('image', function ($q) use ($publishedOnly, $minViews) {
                // Filter images that have an imageable (Post) with the specified conditions using whereHasMorph
                $q->whereHasMorph('imageable', [PostModel::class], function ($subQ) use ($publishedOnly, $minViews) {
                    if ($publishedOnly) {
                        $subQ->where('is_published', true);
                    }
                    if ($minViews > 0) {
                        $subQ->where('views', '>=', $minViews);
                    }
                });
            })
                ->with([
                    'image.imageable' => function (MorphTo $morphTo) use ($publishedOnly, $minViews) {
                        $morphTo->constrain([
                            PostModel::class => function ($query) use ($publishedOnly, $minViews) {
                                if ($publishedOnly) {
                                    $query->where('is_published', true);
                                }
                                if ($minViews > 0) {
                                    $query->where('views', '>=', $minViews);
                                }
                            }
                        ]);
                    },
                    // Load imageable and its nested relationships
                    // Note: 'image.imageable.image' creates a circular reference:
                    // Post -> Image -> Imageable (Post) -> Image
                    // The constraint applies to the final 'image' in the path
                    'image.imageable.image' => function ($q) {
                        // Deep nested: Post -> Image -> Imageable (Post) -> Image
                        // Constraint applies to imageable's image, ordered by size DESC
                        $q->orderBy('size', 'DESC');
                    },
                    'image.imageable.comments' => function ($q) {
                        // Deep nested: Post -> Image -> Imageable (Post) -> Comments
                        $q->where('is_approved', true)
                            ->orderBy('created_at', 'DESC')
                            ->limit(3);
                    },
                    'image.imageable.tags' => function ($q) {
                        // Deep nested: Post -> Image -> Imageable (Post) -> Tags
                        $q->select('tags.id', 'tags.name', 'tags.slug')
                            ->where('tags.is_active', true)
                            ->orderBy('tags.name', 'ASC')->limit(3);
                    }
                ])
                ->limit(3)
                ->get();
            $results['deep_nested_relationships'] = $postsWithDeepNested->all();

            // // Test 5: Nested relationship - Video with image and image has imageable (nested MorphTo)
            // $videosWithNestedImage = VideoModel::whereHas('image', function ($q) use ($minWidth, $minHeight) {
            //     if ($minWidth > 0) {
            //         $q->where('width', '>=', $minWidth);
            //     }
            //     if ($minHeight > 0) {
            //         $q->where('height', '>=', $minHeight);
            //     }
            // })
            //     ->with([
            //         'image.imageable' => function ($q) use ($publishedOnly, $minViews) {
            //             // Nested: image -> imageable (Post/Video)
            //             if ($publishedOnly) {
            //                 $q->where('is_published', true);
            //             }
            //             if ($minViews > 0) {
            //                 $q->where('views', '>=', $minViews);
            //             }
            //         },
            //         'image' => function ($q) {
            //             $q->orderBy('size', 'DESC');
            //         }
            //     ])
            //     ->where('is_published', true)
            //     ->where('duration', '>', 0)
            //     ->limit(5)
            //     ->get();
            // $results['nested_image_imageable'] = $videosWithNestedImage->map(function ($v) {
            //     return [
            //         'id' => $v->id,
            //         'title' => $v->title,
            //         'image' => $v->image ? [
            //             'image' => $v->image->toArray(),
            //             'imageable' => $v->image->imageable ? $v->image->imageable->toArray() : null,
            //             'imageable_type' => $v->image->imageable_type ?? null,
            //         ] : null,
            //     ];
            // })->all();

            // // Test 6: Deep nested - Video with image, image has imageable, and imageable has relationships
            // // Use whereHas with whereHasMorph to properly handle polymorphic relationship
            // $videosWithDeepNested = VideoModel::whereHas('image', function ($q) use ($publishedOnly, $minViews) {
            //     // Filter images that have an imageable (Video) with the specified conditions using whereHasMorph
            //     $q->whereHasMorph('imageable', [VideoModel::class], function ($subQ) use ($publishedOnly, $minViews) {
            //         if ($publishedOnly) {
            //             $subQ->where('is_published', true);
            //         }
            //         if ($minViews > 0) {
            //             $subQ->where('views', '>=', $minViews);
            //         }
            //     });
            // })
            //     ->with([
            //         // Load imageable and its nested relationships
            //         // Note: 'image.imageable.image' creates a circular reference:
            //         // Video -> Image -> Imageable (Video) -> Image
            //         // The constraint applies to the final 'image' in the path
            //         'image.imageable.image' => function ($q) {
            //             // Deep nested: Video -> Image -> Imageable (Video) -> Image
            //             // Constraint applies to imageable's image, ordered by size DESC
            //             $q->orderBy('size', 'DESC');
            //         },
            //         'image.imageable.comments' => function ($q) {
            //             // Deep nested: Video -> Image -> Imageable (Video) -> Comments
            //             $q->where('is_approved', true)
            //                 ->orderBy('created_at', 'DESC')
            //                 ->limit(3);
            //         },
            //         'image.imageable.tags' => function ($q) {
            //             // Deep nested: Video -> Image -> Imageable (Video) -> Tags
            //             $q->where('is_active', true)
            //                 ->orderBy('name', 'ASC');
            //         }
            //     ])
            //     ->where('duration', '>', 60)
            //     ->limit(3)
            //     ->get();
            // $results['deep_nested_relationships'] = $videosWithDeepNested->map(function ($v) {
            //     $imageable = $v->image && $v->image->imageable ? $v->image->imageable : null;
            //     return [
            //         'id' => $v->id,
            //         'title' => $v->title,
            //         'duration' => $v->duration,
            //         'image' => $v->image ? [
            //             'image' => $v->image->toArray(),
            //             'imageable' => $imageable ? [
            //                 'imageable' => $imageable->toArray(),
            //                 'nested_image' => $imageable->image ? $imageable->image->toArray() : null,
            //                 'nested_comments' => $imageable->comments ? $imageable->comments->toArray() : [],
            //                 'nested_tags' => $imageable->tags ? $imageable->tags->map(function ($tag) {
            //                     $data = $tag->toArray();
            //                     if ($tag->pivot) {
            //                         $data['pivot'] = $tag->pivot->toArray();
            //                     }
            //                     return $data;
            //                 })->all() : [],
            //             ] : null,
            //         ] : null,
            //     ];
            // })->all();
        }

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'MorphOne relationship test with complex queries and nested relationships',
            'data' => $results,
            'queries' => $queries,
        ]);
    }

    /**
     * Test MorphMany relationship (Post/Video has many Comments) with complex queries.
     *
     * GET /api/polymorphic/test-morph-many?type=post&id=1&min_comments=5&approved_only=true&date_from=2024-01-01
     * GET /api/polymorphic/test-morph-many?type=video&id=1&min_comments=3&approved_only=true&has_user=true
     *
     * Parameters:
     * - type: 'post' or 'video' (required)
     * - id: ID of post or video (required, must exist in database)
     * - min_comments: Minimum number of comments (optional, default: 0)
     * - approved_only: Filter only approved comments (optional, default: false, values: true/false)
     * - has_user: Filter comments with user_id (optional, default: false, values: true/false)
     * - date_from: Filter comments from date (optional, format: Y-m-d)
     * - date_to: Filter comments to date (optional, format: Y-m-d)
     * - content_contains: Filter comments containing text (optional)
     * - limit: Limit number of comments returned (optional, default: 10)
     *
     * Example with complex queries:
     * GET /api/polymorphic/test-morph-many?type=post&id=1&min_comments=5&approved_only=true&date_from=2024-01-01
     * GET /api/polymorphic/test-morph-many?type=video&id=1&has_user=true&content_contains=great&limit=20
     */
    public function testMorphMany(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $type = $request->get('type', 'post');
        $id = (int) $request->get('id', 1);
        $minComments = (int) $request->get('min_comments', 0);
        $approvedOnly = $request->get('approved_only', 'false') === 'true';
        $hasUser = $request->get('has_user', 'false') === 'true';
        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        $contentContains = $request->get('content_contains', '');
        $limit = (int) $request->get('limit', 10);
        $publishedOnly = $request->get('published_only', 'false') === 'true';
        $minViews = (int) $request->get('min_views', 0);

        $results = [];

        if ($type === 'post') {
            // Test 1: Basic with() simple
            $postBasic = PostModel::with(['comments' => function ($q) {
                $q->where('is_approved', true)->orderBy('created_at', 'DESC');
            }])->find($id);
            $results['basic_with'] = $postBasic ? [
                'post' => $postBasic->toArray(),
                'comments' => $postBasic->comments->toArray(),
                'comments_count' => $postBasic->comments->count(),
            ] : ['error' => 'Post not found'];

            // Test 2: Complex with() callback - multiple nested conditions
            $postComplex = PostModel::with(['comments' => function ($q) use ($approvedOnly, $hasUser, $dateFrom, $dateTo, $contentContains, $limit) {
                if ($approvedOnly) {
                    $q->where('is_approved', true);
                }
                if ($hasUser) {
                    $q->whereNotNull('user_id');
                }
                if (!empty($dateFrom)) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                }
                if (!empty($dateTo)) {
                    $q->whereDate('created_at', '<=', $dateTo);
                }
                if (!empty($contentContains)) {
                    $q->where('content', 'like', '%' . $contentContains . '%');
                }
                $q->orderBy('created_at', 'DESC')
                    ->orderBy('id', 'DESC')
                    ->limit($limit);
            }])->find($id);
            $results['complex_with_callback'] = $postComplex ? [
                'post' => $postComplex->toArray(),
                'comments' => $postComplex->comments->toArray(),
                'comments_count' => $postComplex->comments->count(),
            ] : ['error' => 'Post not found'];

            // Test 3: whereHas with complex conditions and aggregations
            $postsWithComments = PostModel::whereHas('comments', function ($q) use ($approvedOnly, $hasUser, $minComments) {
                if ($approvedOnly) {
                    $q->where('is_approved', true);
                }
                if ($hasUser) {
                    $q->whereNotNull('user_id');
                }
            })
                ->withCount(['comments' => function ($q) use ($approvedOnly) {
                    if ($approvedOnly) {
                        $q->where('is_approved', true);
                    }
                }])
                ->having('comments_count', '>=', $minComments > 0 ? $minComments : 1)
                ->where('is_published', true)
                ->with(['comments' => function ($q) use ($approvedOnly, $limit) {
                    if ($approvedOnly) {
                        $q->where('is_approved', true);
                    }
                    $q->orderBy('created_at', 'DESC')->limit($limit);
                }])
                ->orderBy('comments_count', 'DESC')
                ->limit(10)
                ->get();
            $results['where_has_with_aggregations'] = $postsWithComments->map(function ($p) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'views' => $p->views,
                    'comments_count' => $p->comments_count,
                    'comments' => $p->comments->toArray(),
                ];
            })->all();

            // Test 4: Nested whereHas with multiple conditions and subqueries
            $postsWithComplexComments = PostModel::whereHas('comments', function ($q) use ($approvedOnly, $dateFrom) {
                $q->where('is_approved', $approvedOnly)
                    ->where(function ($subQ) {
                        $subQ->whereNotNull('user_id')
                            ->orWhere('content', '!=', '');
                    });
                if (!empty($dateFrom)) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                }
                $q->whereIn('id', function ($subQ) {
                    $subQ->select('id')
                        ->from('comments')
                        ->where('is_approved', true)
                        ->limit(100);
                });
            })
                ->where(function ($q) {
                    $q->where('views', '>', 100)
                        ->orWhere('is_published', true);
                })
                ->with(['comments' => function ($q) use ($approvedOnly, $hasUser, $limit) {
                    if ($approvedOnly) {
                        $q->where('is_approved', true);
                    }
                    if ($hasUser) {
                        $q->whereNotNull('user_id');
                    }
                    $q->orderBy('created_at', 'DESC')
                        ->orderBy('id', 'DESC')
                        ->limit($limit);
                }])
                ->withCount(['comments as approved_comments_count' => function ($q) {
                    $q->where('is_approved', true);
                }])
                ->orderBy('views', 'DESC')
                ->limit(10)
                ->get();
            $results['nested_where_has_complex'] = $postsWithComplexComments->map(function ($p) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'views' => $p->views,
                    'approved_comments_count' => $p->approved_comments_count,
                    'comments' => $p->comments->toArray(),
                ];
            })->all();

            // Test 5: Multiple whereHas with orWhereHas
            $postsWithMultipleConditions = PostModel::whereHas('comments', function ($q) {
                $q->where('is_approved', true)
                    ->whereDate('created_at', '>=', '2024-01-01');
            })
                ->orWhereHas('comments', function ($q) {
                    $q->whereNotNull('user_id')
                        ->where('content', 'like', '%great%');
                })
                ->with(['comments' => function ($q) {
                    $q->where(function ($subQ) {
                        $subQ->where('is_approved', true)
                            ->orWhereNotNull('user_id');
                    })
                        ->orderBy('created_at', 'DESC')
                        ->limit(5);
                }])
                ->where('is_published', true)
                ->limit(10)
                ->get();
            $results['multiple_where_has'] = $postsWithMultipleConditions->map(function ($p) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'comments' => $p->comments->toArray(),
                ];
            })->all();
        } elseif ($type === 'video') {
            // Test 1: Basic with() simple
            $videoBasic = VideoModel::with(['comments' => function ($q) {
                $q->where('is_approved', true)->orderBy('created_at', 'DESC');
            }])->find($id);
            $results['basic_with'] = $videoBasic ? [
                'video' => $videoBasic->toArray(),
                'comments' => $videoBasic->comments->toArray(),
                'comments_count' => $videoBasic->comments->count(),
            ] : ['error' => 'Video not found'];

            // Test 2: Complex with() callback - multiple nested conditions
            $videoComplex = VideoModel::with(['comments' => function ($q) use ($approvedOnly, $hasUser, $dateFrom, $dateTo, $contentContains, $limit) {
                if ($approvedOnly) {
                    $q->where('is_approved', true);
                }
                if ($hasUser) {
                    $q->whereNotNull('user_id');
                }
                if (!empty($dateFrom)) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                }
                if (!empty($dateTo)) {
                    $q->whereDate('created_at', '<=', $dateTo);
                }
                if (!empty($contentContains)) {
                    $q->where('content', 'like', '%' . $contentContains . '%');
                }
                $q->orderBy('created_at', 'DESC')
                    ->orderBy('id', 'DESC')
                    ->limit($limit);
            }])->find($id);
            $results['complex_with_callback'] = $videoComplex ? [
                'video' => $videoComplex->toArray(),
                'comments' => $videoComplex->comments->toArray(),
                'comments_count' => $videoComplex->comments->count(),
            ] : ['error' => 'Video not found'];

            // Test 3: whereHas with complex conditions and aggregations
            $videosWithComments = VideoModel::whereHas('comments', function ($q) use ($approvedOnly, $hasUser, $minComments) {
                if ($approvedOnly) {
                    $q->where('is_approved', true);
                }
                if ($hasUser) {
                    $q->whereNotNull('user_id');
                }
            })
                ->withCount(['comments' => function ($q) use ($approvedOnly) {
                    if ($approvedOnly) {
                        $q->where('is_approved', true);
                    }
                }])
                ->having('comments_count', '>=', $minComments > 0 ? $minComments : 1)
                ->where('is_published', true)
                ->where('duration', '>', 0)
                ->with(['comments' => function ($q) use ($approvedOnly, $limit) {
                    if ($approvedOnly) {
                        $q->where('is_approved', true);
                    }
                    $q->orderBy('created_at', 'DESC')->limit($limit);
                }])
                ->orderBy('comments_count', 'DESC')
                ->limit(10)
                ->get();
            $results['where_has_with_aggregations'] = $videosWithComments->map(function ($v) {
                return [
                    'id' => $v->id,
                    'title' => $v->title,
                    'views' => $v->views,
                    'duration' => $v->duration,
                    'comments_count' => $v->comments_count,
                    'comments' => $v->comments->toArray(),
                ];
            })->all();

            // Test 4: Nested whereHas with multiple conditions and subqueries
            $videosWithComplexComments = VideoModel::whereHas('comments', function ($q) use ($approvedOnly, $dateFrom) {
                $q->where('is_approved', $approvedOnly)
                    ->where(function ($subQ) {
                        $subQ->whereNotNull('user_id')
                            ->orWhere('content', '!=', '');
                    });
                if (!empty($dateFrom)) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                }
                $q->whereIn('id', function ($subQ) {
                    $subQ->select('id')
                        ->from('comments')
                        ->where('is_approved', true)
                        ->limit(100);
                });
            })
                ->where(function ($q) {
                    $q->where('views', '>', 500)
                        ->orWhere('is_published', true);
                })
                ->where('duration', '>', 60)
                ->with(['comments' => function ($q) use ($approvedOnly, $hasUser, $limit) {
                    if ($approvedOnly) {
                        $q->where('is_approved', true);
                    }
                    if ($hasUser) {
                        $q->whereNotNull('user_id');
                    }
                    $q->orderBy('created_at', 'DESC')
                        ->orderBy('id', 'DESC')
                        ->limit($limit);
                }])
                ->withCount(['comments as approved_comments_count' => function ($q) {
                    $q->where('is_approved', true);
                }])
                ->orderBy('views', 'DESC')
                ->limit(10)
                ->get();
            $results['nested_where_has_complex'] = $videosWithComplexComments->map(function ($v) {
                return [
                    'id' => $v->id,
                    'title' => $v->title,
                    'views' => $v->views,
                    'duration' => $v->duration,
                    'approved_comments_count' => $v->approved_comments_count,
                    'comments' => $v->comments->toArray(),
                ];
            })->all();

            // Test 5: Multiple whereHas with orWhereHas
            $videosWithMultipleConditions = VideoModel::whereHas('comments', function ($q) {
                $q->where('is_approved', true)
                    ->whereDate('created_at', '>=', '2024-01-01');
            })
                ->orWhereHas('comments', function ($q) {
                    $q->whereNotNull('user_id')
                        ->where('content', 'like', '%awesome%');
                })
                ->with(['comments' => function ($q) {
                    $q->where(function ($subQ) {
                        $subQ->where('is_approved', true)
                            ->orWhereNotNull('user_id');
                    })
                        ->orderBy('created_at', 'DESC')
                        ->limit(5);
                }])
                ->where('is_published', true)
                ->where('duration', '>', 60)
                ->limit(10)
                ->get();
            $results['multiple_where_has'] = $videosWithMultipleConditions->map(function ($v) {
                return [
                    'id' => $v->id,
                    'title' => $v->title,
                    'comments' => $v->comments->toArray(),
                ];
            })->all();

            // Test 6: Nested relationship - Post with comments and comments have commentable (nested MorphTo)
            $postsWithNestedComments = PostModel::whereHas('comments', function ($q) use ($approvedOnly) {
                if ($approvedOnly) {
                    $q->where('is_approved', true);
                }
            })
                ->with([
                    'comments.commentable' => function ($q) use ($publishedOnly, $minViews) {
                        // Nested: comments -> commentable (Post/Video)
                        if ($publishedOnly) {
                            $q->where('is_published', true);
                        }
                        if ($minViews > 0) {
                            $q->where('views', '>=', $minViews);
                        }
                    },
                    'comments' => function ($q) use ($approvedOnly, $limit) {
                        if ($approvedOnly) {
                            $q->where('is_approved', true);
                        }
                        $q->orderBy('created_at', 'DESC')->limit($limit);
                    }
                ])
                ->where('is_published', true)
                ->limit(5)
                ->get();
            $results['nested_comments_commentable'] = $postsWithNestedComments->map(function ($p) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'comments' => $p->comments->map(function ($comment) {
                        return [
                            'comment' => $comment->toArray(),
                            'commentable' => $comment->commentable ? $comment->commentable->toArray() : null,
                            'commentable_type' => $comment->commentable_type,
                        ];
                    })->all(),
                ];
            })->all();

            // Test 7: Deep nested - Post with comments, comments have commentable, and commentable has relationships
            $postsWithDeepNestedComments = PostModel::whereHas('comments.commentable', function ($q) use ($publishedOnly, $minViews) {
                if ($publishedOnly) {
                    $q->where('is_published', true);
                }
                if ($minViews > 0) {
                    $q->where('views', '>=', $minViews);
                }
            })
                ->with([
                    'comments.commentable' => function ($q) {
                        // Load commentable with its own relationships
                    },
                    'comments.commentable.image' => function ($q) {
                        // Deep nested: Post -> Comments -> Commentable (Post) -> Image
                        $q->orderBy('size', 'DESC');
                    },
                    'comments.commentable.tags' => function ($q) {
                        // Deep nested: Post -> Comments -> Commentable (Post) -> Tags
                        $q->where('is_active', true)
                            ->orderBy('name', 'ASC');
                    },
                    'comments.commentable.comments' => function ($q) {
                        // Deep nested: Post -> Comments -> Commentable (Post) -> Comments
                        $q->where('is_approved', true)
                            ->orderBy('created_at', 'DESC')
                            ->limit(3);
                    },
                    'comments' => function ($q) use ($approvedOnly) {
                        if ($approvedOnly) {
                            $q->where('is_approved', true);
                        }
                        $q->orderBy('created_at', 'DESC')->limit(5);
                    }
                ])
                ->where('is_published', true)
                ->limit(3)
                ->get();
            $results['deep_nested_comments'] = $postsWithDeepNestedComments->map(function ($p) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'comments' => $p->comments->map(function ($comment) {
                        $commentable = $comment->commentable;
                        return [
                            'comment' => $comment->toArray(),
                            'commentable' => $commentable ? [
                                'commentable' => $commentable->toArray(),
                                'nested_image' => $commentable->image ? $commentable->image->toArray() : null,
                                'nested_tags' => $commentable->tags ? $commentable->tags->map(function ($tag) {
                                    $data = $tag->toArray();
                                    if ($tag->pivot) {
                                        $data['pivot'] = $tag->pivot->toArray();
                                    }
                                    return $data;
                                })->all() : [],
                                'nested_comments' => $commentable->comments ? $commentable->comments->toArray() : [],
                            ] : null,
                        ];
                    })->all(),
                ];
            })->all();
        } elseif ($type === 'video') {
            // Test 6: Nested relationship - Video with comments and comments have commentable (nested MorphTo)
            $videosWithNestedComments = VideoModel::whereHas('comments', function ($q) use ($approvedOnly) {
                if ($approvedOnly) {
                    $q->where('is_approved', true);
                }
            })
                ->with([
                    'comments.commentable' => function ($q) use ($publishedOnly, $minViews) {
                        // Nested: comments -> commentable (Post/Video)
                        if ($publishedOnly) {
                            $q->where('is_published', true);
                        }
                        if ($minViews > 0) {
                            $q->where('views', '>=', $minViews);
                        }
                    },
                    'comments' => function ($q) use ($approvedOnly, $limit) {
                        if ($approvedOnly) {
                            $q->where('is_approved', true);
                        }
                        $q->orderBy('created_at', 'DESC')->limit($limit);
                    }
                ])
                ->where('is_published', true)
                ->where('duration', '>', 0)
                ->limit(5)
                ->get();
            $results['nested_comments_commentable'] = $videosWithNestedComments->map(function ($v) {
                return [
                    'id' => $v->id,
                    'title' => $v->title,
                    'comments' => $v->comments->map(function ($comment) {
                        return [
                            'comment' => $comment->toArray(),
                            'commentable' => $comment->commentable ? $comment->commentable->toArray() : null,
                            'commentable_type' => $comment->commentable_type,
                        ];
                    })->all(),
                ];
            })->all();

            // Test 7: Deep nested - Video with comments, comments have commentable, and commentable has relationships
            $videosWithDeepNestedComments = VideoModel::whereHas('comments.commentable', function ($q) use ($publishedOnly, $minViews) {
                if ($publishedOnly) {
                    $q->where('is_published', true);
                }
                if ($minViews > 0) {
                    $q->where('views', '>=', $minViews);
                }
            })
                ->with([
                    'comments.commentable' => function ($q) {
                        // Load commentable with its own relationships
                    },
                    'comments.commentable.image' => function ($q) {
                        // Deep nested: Video -> Comments -> Commentable (Video) -> Image
                        $q->orderBy('size', 'DESC');
                    },
                    'comments.commentable.tags' => function ($q) {
                        // Deep nested: Video -> Comments -> Commentable (Video) -> Tags
                        $q->where('is_active', true)
                            ->orderBy('name', 'ASC');
                    },
                    'comments.commentable.comments' => function ($q) {
                        // Deep nested: Video -> Comments -> Commentable (Video) -> Comments
                        $q->where('is_approved', true)
                            ->orderBy('created_at', 'DESC')
                            ->limit(3);
                    },
                    'comments' => function ($q) use ($approvedOnly) {
                        if ($approvedOnly) {
                            $q->where('is_approved', true);
                        }
                        $q->orderBy('created_at', 'DESC')->limit(5);
                    }
                ])
                ->where('is_published', true)
                ->where('duration', '>', 60)
                ->limit(3)
                ->get();
            $results['deep_nested_comments'] = $videosWithDeepNestedComments->map(function ($v) {
                return [
                    'id' => $v->id,
                    'title' => $v->title,
                    'duration' => $v->duration,
                    'comments' => $v->comments->map(function ($comment) {
                        $commentable = $comment->commentable;
                        return [
                            'comment' => $comment->toArray(),
                            'commentable' => $commentable ? [
                                'commentable' => $commentable->toArray(),
                                'nested_image' => $commentable->image ? $commentable->image->toArray() : null,
                                'nested_tags' => $commentable->tags ? $commentable->tags->map(function ($tag) {
                                    $data = $tag->toArray();
                                    if ($tag->pivot) {
                                        $data['pivot'] = $tag->pivot->toArray();
                                    }
                                    return $data;
                                })->all() : [],
                                'nested_comments' => $commentable->comments ? $commentable->comments->toArray() : [],
                            ] : null,
                        ];
                    })->all(),
                ];
            })->all();
        }

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'MorphMany relationship test with complex queries and nested relationships',
            'data' => $results,
            'queries' => $queries,
        ]);
    }

    /**
     * Test MorphTo relationship (Comment belongs to Post/Video) with complex queries.
     *
     * GET /api/polymorphic/test-morph-to?comment_id=1&type_filter=post&min_views=100&published_only=true
     *
     * Parameters:
     * - comment_id: ID of comment (required, must exist in database)
     * - type_filter: Filter by commentable type (optional, values: 'post', 'video', 'both')
     * - min_views: Minimum views for commentable (optional, default: 0)
     * - published_only: Filter only published commentables (optional, default: false, values: true/false)
     * - approved_only: Filter only approved comments (optional, default: false, values: true/false)
     * - has_user: Filter comments with user_id (optional, default: false, values: true/false)
     * - content_contains: Filter comments containing text (optional)
     *
     * Example with complex queries:
     * GET /api/polymorphic/test-morph-to?comment_id=1&type_filter=post&min_views=100&published_only=true
     * GET /api/polymorphic/test-morph-to?comment_id=1&approved_only=true&has_user=true&content_contains=great
     */
    public function testMorphTo(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $commentId = (int) $request->get('comment_id', 1);
        $typeFilter = $request->get('type_filter', 'both');
        $minViews = (int) $request->get('min_views', 0);
        $publishedOnly = $request->get('published_only', 'false') === 'true';
        $approvedOnly = $request->get('approved_only', 'false') === 'true';
        $hasUser = $request->get('has_user', 'false') === 'true';
        $contentContains = $request->get('content_contains', '');

        $results = [];

        // Test 1: Basic with() simple
        $commentBasic = CommentModel::with('commentable')->find($commentId);
        $results['basic_with'] = $commentBasic ? [
            'comment' => $commentBasic->toArray(),
            'commentable' => $commentBasic->commentable ? $commentBasic->commentable->toArray() : null,
            'commentable_type' => $commentBasic->commentable_type,
            'commentable_class' => $commentBasic->commentable ? get_class($commentBasic->commentable) : null,
        ] : ['error' => 'Comment not found'];

        // Test 2: Complex with() callback - filter commentable by conditions
        $commentComplex = CommentModel::with(['commentable' => function ($q) use ($typeFilter, $minViews, $publishedOnly) {
            if ($typeFilter === 'post') {
                $q->where('commentable_type', PostModel::class);
            } elseif ($typeFilter === 'video') {
                $q->where('commentable_type', VideoModel::class);
            }
            // Note: MorphTo doesn't support direct filtering on related model fields in with()
            // This is a limitation - we'll filter in whereHas instead
        }])->find($commentId);
        $results['complex_with_callback'] = $commentComplex ? [
            'comment' => $commentComplex->toArray(),
            'commentable' => $commentComplex->commentable ? $commentComplex->commentable->toArray() : null,
        ] : ['error' => 'Comment not found'];

        // Test 3: whereHas with complex conditions - filter comments by commentable properties
        $commentsWithPost = CommentModel::whereHasMorph('commentable', [PostModel::class], function ($q) use ($minViews, $publishedOnly) {
            if ($minViews > 0) {
                $q->where('views', '>=', $minViews);
            }
            if ($publishedOnly) {
                $q->where('is_published', true);
            }
        })
            ->where(function ($q) use ($approvedOnly, $hasUser, $contentContains) {
                if ($approvedOnly) {
                    $q->where('is_approved', true);
                }
                if ($hasUser) {
                    $q->whereNotNull('user_id');
                }
                if (!empty($contentContains)) {
                    $q->where('content', 'like', '%' . $contentContains . '%');
                }
            })
            ->with(['commentable' => function ($q) {
                // Load with specific fields
            }])
            ->orderBy('created_at', 'DESC')
            ->limit(10)
            ->get();
        $results['where_has_morph_posts'] = $commentsWithPost->map(function ($c) {
            return [
                'comment' => $c->toArray(),
                'commentable' => $c->commentable ? $c->commentable->toArray() : null,
                'commentable_type' => $c->commentable_type,
            ];
        })->all();

        // Test 4: whereHasMorph with Video
        $commentsWithVideo = CommentModel::whereHasMorph('commentable', [VideoModel::class], function ($q) use ($minViews, $publishedOnly) {
            if ($minViews > 0) {
                $q->where('views', '>=', $minViews);
            }
            if ($publishedOnly) {
                $q->where('is_published', true);
            }
            $q->where('duration', '>', 0);
        })
            ->where(function ($q) use ($approvedOnly, $hasUser) {
                if ($approvedOnly) {
                    $q->where('is_approved', true);
                }
                if ($hasUser) {
                    $q->whereNotNull('user_id');
                }
            })
            ->with('commentable')
            ->orderBy('created_at', 'DESC')
            ->limit(10)
            ->get();
        $results['where_has_morph_videos'] = $commentsWithVideo->map(function ($c) {
            return [
                'comment' => $c->toArray(),
                'commentable' => $c->commentable ? $c->commentable->toArray() : null,
                'commentable_type' => $c->commentable_type,
            ];
        })->all();

        // Test 5: whereHasMorph with multiple types (Post and Video)
        $commentsWithBoth = CommentModel::whereHasMorph('commentable', [PostModel::class, VideoModel::class], function ($q) use ($minViews, $publishedOnly) {
            if ($minViews > 0) {
                $q->where('views', '>=', $minViews);
            }
            if ($publishedOnly) {
                $q->where('is_published', true);
            }
        })
            ->where(function ($q) use ($approvedOnly, $hasUser, $contentContains) {
                if ($approvedOnly) {
                    $q->where('is_approved', true);
                }
                if ($hasUser) {
                    $q->whereNotNull('user_id');
                }
                if (!empty($contentContains)) {
                    $q->where('content', 'like', '%' . $contentContains . '%');
                }
            })
            ->with('commentable')
            ->orderBy('created_at', 'DESC')
            ->limit(20)
            ->get();
        $results['where_has_morph_both'] = $commentsWithBoth->map(function ($c) {
            return [
                'comment' => $c->toArray(),
                'commentable' => $c->commentable ? $c->commentable->toArray() : null,
                'commentable_type' => $c->commentable_type,
                'commentable_class' => $c->commentable ? get_class($c->commentable) : null,
            ];
        })->all();

        // Test 6: orWhereHasMorph - complex conditions
        $commentsWithOrConditions = CommentModel::whereHasMorph('commentable', [PostModel::class], function ($q) {
            $q->where('views', '>', 100)
                ->where('is_published', true);
        })
            ->orWhereHasMorph('commentable', [VideoModel::class], function ($q) {
                $q->where('views', '>', 500)
                    ->where('duration', '>', 60)
                    ->where('is_published', true);
            })
            ->where(function ($q) use ($approvedOnly) {
                if ($approvedOnly) {
                    $q->where('is_approved', true);
                }
            })
            ->with('commentable')
            ->orderBy('created_at', 'DESC')
            ->limit(15)
            ->get();
        $results['or_where_has_morph'] = $commentsWithOrConditions->map(function ($c) {
            return [
                'comment' => $c->toArray(),
                'commentable' => $c->commentable ? $c->commentable->toArray() : null,
                'commentable_type' => $c->commentable_type,
            ];
        })->all();

        // Test 7: Nested whereHasMorph with subqueries
        $commentsWithNestedConditions = CommentModel::whereHasMorph('commentable', [PostModel::class, VideoModel::class], function ($q) use ($minViews, $publishedOnly) {
            $q->where(function ($subQ) use ($minViews) {
                $subQ->where('views', '>=', $minViews)
                    ->orWhereIn('id', function ($subSubQ) {
                        $subSubQ->select('id')
                            ->from('posts')
                            ->where('is_published', true)
                            ->limit(100);
                    });
            });
            if ($publishedOnly) {
                $q->where('is_published', true);
            }
        })
            ->where(function ($q) use ($approvedOnly, $hasUser) {
                if ($approvedOnly) {
                    $q->where('is_approved', true);
                }
                if ($hasUser) {
                    $q->whereNotNull('user_id');
                }
            })
            ->with(['commentable' => function ($q) {
                // Additional constraints if needed
            }])
            ->withCount('commentable')
            ->orderBy('created_at', 'DESC')
            ->limit(10)
            ->get();
        $results['nested_where_has_morph'] = $commentsWithNestedConditions->map(function ($c) {
            return [
                'comment' => $c->toArray(),
                'commentable' => $c->commentable ? $c->commentable->toArray() : null,
                'commentable_type' => $c->commentable_type,
            ];
        })->all();

        // Test 8: Nested relationship - Comment with commentable and commentable has relationships
        $commentsWithNestedCommentable = CommentModel::whereHasMorph('commentable', [PostModel::class, VideoModel::class], function ($q) use ($minViews, $publishedOnly) {
            if ($minViews > 0) {
                $q->where('views', '>=', $minViews);
            }
            if ($publishedOnly) {
                $q->where('is_published', true);
            }
        })
            ->where(function ($q) use ($approvedOnly) {
                if ($approvedOnly) {
                    $q->where('is_approved', true);
                }
            })
            ->with([
                'commentable' => function ($q) {
                    // Load commentable with its own relationships
                },
                'commentable.image' => function ($q) {
                    // Nested: commentable -> image (MorphOne)
                    $q->orderBy('size', 'DESC');
                },
                'commentable.comments' => function ($q) {
                    // Nested: commentable -> comments (MorphMany)
                    $q->where('is_approved', true)
                        ->orderBy('created_at', 'DESC')
                        ->limit(5);
                },
                'commentable.tags' => function ($q) {
                    // Nested: commentable -> tags (MorphToMany)
                    $q->where('is_active', true)
                        ->orderBy('name', 'ASC');
                }
            ])
            ->orderBy('created_at', 'DESC')
            ->limit(10)
            ->get();
        $results['nested_commentable_relationships'] = $commentsWithNestedCommentable->map(function ($c) {
            $commentable = $c->commentable;
            return [
                'comment' => $c->toArray(),
                'commentable' => $commentable ? [
                    'commentable' => $commentable->toArray(),
                    'nested_image' => $commentable->image ? $commentable->image->toArray() : null,
                    'nested_comments' => $commentable->comments ? $commentable->comments->toArray() : [],
                    'nested_tags' => $commentable->tags ? $commentable->tags->map(function ($tag) {
                        $data = $tag->toArray();
                        if ($tag->pivot) {
                            $data['pivot'] = $tag->pivot->toArray();
                        }
                        return $data;
                    })->all() : [],
                ] : null,
            ];
        })->all();

        // Test 9: Deep nested - Comment with commentable, commentable has image, and image has imageable
        $commentsWithDeepNested = CommentModel::whereHasMorph('commentable', [PostModel::class, VideoModel::class], function ($q) use ($publishedOnly) {
            if ($publishedOnly) {
                $q->where('is_published', true);
            }
        })
            ->with([
                'commentable.image.imageable' => function ($q) {
                    // Deep nested: Comment -> Commentable -> Image -> Imageable
                },
                'commentable.image' => function ($q) {
                    $q->orderBy('size', 'DESC');
                },
                'commentable' => function ($q) {
                    // Load commentable
                }
            ])
            ->where('is_approved', true)
            ->limit(5)
            ->get();
        $results['deep_nested_commentable_image'] = $commentsWithDeepNested->map(function ($c) {
            $commentable = $c->commentable;
            $image = $commentable && $commentable->image ? $commentable->image : null;
            return [
                'comment' => $c->toArray(),
                'commentable' => $commentable ? [
                    'commentable' => $commentable->toArray(),
                    'image' => $image ? [
                        'image' => $image->toArray(),
                        'imageable' => $image->imageable ? $image->imageable->toArray() : null,
                        'imageable_type' => $image->imageable_type ?? null,
                    ] : null,
                ] : null,
            ];
        })->all();

        // Test 10: Ultra deep nested - Comment -> Commentable -> Comments -> Commentable (circular nested)
        $commentsWithUltraDeepNested = CommentModel::whereHasMorph('commentable', [PostModel::class, VideoModel::class], function ($q) {
            $q->where('is_published', true);
        })
            ->with([
                'commentable.comments.commentable' => function ($q) {
                    // Ultra deep nested: Comment -> Commentable -> Comments -> Commentable
                },
                'commentable.comments' => function ($q) {
                    $q->where('is_approved', true)
                        ->orderBy('created_at', 'DESC')
                        ->limit(3);
                },
                'commentable' => function ($q) {
                    // Load commentable
                }
            ])
            ->where('is_approved', true)
            ->limit(5)
            ->get();
        $results['ultra_deep_nested_comments'] = $commentsWithUltraDeepNested->map(function ($c) {
            $commentable = $c->commentable;
            return [
                'comment' => $c->toArray(),
                'commentable' => $commentable ? [
                    'commentable' => $commentable->toArray(),
                    'nested_comments' => $commentable->comments ? $commentable->comments->map(function ($nestedComment) {
                        return [
                            'comment' => $nestedComment->toArray(),
                            'nested_commentable' => $nestedComment->commentable ? $nestedComment->commentable->toArray() : null,
                        ];
                    })->all() : [],
                ] : null,
            ];
        })->all();

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'MorphTo relationship test with complex queries and nested relationships',
            'data' => $results,
            'queries' => $queries,
        ]);
    }

    /**
     * Test MorphToMany relationship (Post/Video has many Tags) with complex queries.
     *
     * GET /api/polymorphic/test-morph-to-many?type=post&id=1&min_tags=3&active_tags_only=true&tag_ids=1,2,3
     * GET /api/polymorphic/test-morph-to-many?type=video&id=1&min_tags=2&tag_name_contains=tech
     *
     * Parameters:
     * - type: 'post' or 'video' (required)
     * - id: ID of post or video (required, must exist in database)
     * - min_tags: Minimum number of tags (optional, default: 0)
     * - active_tags_only: Filter only active tags (optional, default: false, values: true/false)
     * - tag_ids: Comma-separated tag IDs to filter (optional)
     * - tag_name_contains: Filter tags by name containing text (optional)
     * - tag_color: Filter tags by color (optional)
     * - published_only: Filter only published posts/videos (optional, default: false, values: true/false)
     * - min_views: Minimum views for post/video (optional, default: 0)
     *
     * Example with complex queries:
     * GET /api/polymorphic/test-morph-to-many?type=post&id=1&min_tags=3&active_tags_only=true&tag_ids=1,2,3
     * GET /api/polymorphic/test-morph-to-many?type=video&id=1&min_tags=2&tag_name_contains=tech&published_only=true
     */
    public function testMorphToMany(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $type = $request->get('type', 'post');
        $id = (int) $request->get('id', 1);
        $minTags = (int) $request->get('min_tags', 0);
        $activeTagsOnly = $request->get('active_tags_only', 'false') === 'true';
        $tagIds = $request->get('tag_ids', '');
        $tagNameContains = $request->get('tag_name_contains', '');
        $tagColor = $request->get('tag_color', '');
        $publishedOnly = $request->get('published_only', 'false') === 'true';
        $minViews = (int) $request->get('min_views', 0);

        $tagIdsArray = !empty($tagIds) ? array_map('intval', explode(',', $tagIds)) : [];

        $results = [];

        if ($type === 'post') {
            // Test 1: Basic with() simple
            $postBasic = PostModel::with('tags')->find($id);
            $results['basic_with'] = $postBasic ? [
                'post' => $postBasic->toArray(),
                'tags' => $postBasic->tags->map(function ($tag) {
                    $data = $tag->toArray();
                    if ($tag->pivot) {
                        $data['pivot'] = $tag->pivot->toArray();
                    }
                    return $data;
                })->all(),
                'tags_count' => $postBasic->tags->count(),
            ] : ['error' => 'Post not found'];

            // Test 2: Complex with() callback - multiple conditions on tags
            $postComplex = PostModel::with(['tags' => function ($q) use ($activeTagsOnly, $tagIdsArray, $tagNameContains, $tagColor) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
                if (!empty($tagIdsArray)) {
                    $q->whereIn('tags.id', $tagIdsArray);
                }
                if (!empty($tagNameContains)) {
                    $q->where('name', 'like', '%' . $tagNameContains . '%');
                }
                if (!empty($tagColor)) {
                    $q->where('color', $tagColor);
                }
                $q->orderBy('name', 'ASC');
            }])->find($id);
            $results['complex_with_callback'] = $postComplex ? [
                'post' => $postComplex->toArray(),
                'tags' => $postComplex->tags->map(function ($tag) {
                    $data = $tag->toArray();
                    if ($tag->pivot) {
                        $data['pivot'] = $tag->pivot->toArray();
                    }
                    return $data;
                })->all(),
                'tags_count' => $postComplex->tags->count(),
            ] : ['error' => 'Post not found'];

            // Test 3: whereHas with complex conditions
            $postsWithTags = PostModel::whereHas('tags', function ($q) use ($activeTagsOnly, $tagNameContains, $tagIdsArray) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
                if (!empty($tagNameContains)) {
                    $q->where('name', 'like', '%' . $tagNameContains . '%');
                }
                if (!empty($tagIdsArray)) {
                    $q->whereIn('tags.id', $tagIdsArray);
                }
            })
                ->withCount(['tags' => function ($q) use ($activeTagsOnly) {
                    if ($activeTagsOnly) {
                        $q->where('is_active', true);
                    }
                }])
                ->having('tags_count', '>=', $minTags > 0 ? $minTags : 1)
                ->where(function ($q) use ($publishedOnly, $minViews) {
                    if ($publishedOnly) {
                        $q->where('is_published', true);
                    }
                    if ($minViews > 0) {
                        $q->where('views', '>=', $minViews);
                    }
                })
                ->with(['tags' => function ($q) use ($activeTagsOnly) {
                    if ($activeTagsOnly) {
                        $q->where('is_active', true);
                    }
                    $q->orderBy('name', 'ASC');
                }])
                ->orderBy('tags_count', 'DESC')
                ->limit(10)
                ->get();
            $results['where_has_with_aggregations'] = $postsWithTags->map(function ($p) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'views' => $p->views,
                    'tags_count' => $p->tags_count,
                    'tags' => $p->tags->map(function ($tag) {
                        $data = $tag->toArray();
                        if ($tag->pivot) {
                            $data['pivot'] = $tag->pivot->toArray();
                        }
                        return $data;
                    })->all(),
                ];
            })->all();

            // Test 4: Nested whereHas with multiple conditions and subqueries
            $postsWithComplexTags = PostModel::whereHas('tags', function ($q) use ($activeTagsOnly, $tagNameContains) {
                $q->where('is_active', $activeTagsOnly ? true : '!=', null)
                    ->where(function ($subQ) use ($tagNameContains) {
                        if (!empty($tagNameContains)) {
                            $subQ->where('name', 'like', '%' . $tagNameContains . '%')
                                ->orWhere('slug', 'like', '%' . $tagNameContains . '%');
                        }
                    })
                    ->whereIn('id', function ($subQ) {
                        $subQ->select('id')
                            ->from('tags')
                            ->where('is_active', true)
                            ->limit(100);
                    });
            })
                ->where(function ($q) use ($publishedOnly, $minViews) {
                    if ($publishedOnly) {
                        $q->where('is_published', true);
                    }
                    if ($minViews > 0) {
                        $q->where('views', '>=', $minViews);
                    }
                })
                ->with(['tags' => function ($q) use ($activeTagsOnly, $tagIdsArray) {
                    if ($activeTagsOnly) {
                        $q->where('is_active', true);
                    }
                    if (!empty($tagIdsArray)) {
                        $q->whereIn('tags.id', $tagIdsArray);
                    }
                    $q->orderBy('name', 'ASC');
                }])
                ->withCount(['tags as active_tags_count' => function ($q) {
                    $q->where('is_active', true);
                }])
                ->orderBy('views', 'DESC')
                ->limit(10)
                ->get();
            $results['nested_where_has_complex'] = $postsWithComplexTags->map(function ($p) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'views' => $p->views,
                    'active_tags_count' => $p->active_tags_count,
                    'tags' => $p->tags->map(function ($tag) {
                        $data = $tag->toArray();
                        if ($tag->pivot) {
                            $data['pivot'] = $tag->pivot->toArray();
                        }
                        return $data;
                    })->all(),
                ];
            })->all();

            // Test 5: Multiple whereHas with orWhereHas
            $postsWithMultipleTagConditions = PostModel::whereHas('tags', function ($q) {
                $q->where('is_active', true)
                    ->where('name', 'like', '%tech%');
            })
                ->orWhereHas('tags', function ($q) {
                    $q->where('is_active', true)
                        ->where('color', 'like', '%blue%');
                })
                ->with(['tags' => function ($q) {
                    $q->where(function ($subQ) {
                        $subQ->where('is_active', true)
                            ->orWhere('name', 'like', '%important%');
                    })
                        ->orderBy('name', 'ASC');
                }])
                ->where('is_published', true)
                ->limit(10)
                ->get();
            $results['multiple_where_has'] = $postsWithMultipleTagConditions->map(function ($p) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'tags' => $p->tags->map(function ($tag) {
                        $data = $tag->toArray();
                        if ($tag->pivot) {
                            $data['pivot'] = $tag->pivot->toArray();
                        }
                        return $data;
                    })->all(),
                ];
            })->all();
        } elseif ($type === 'video') {
            // Test 1: Basic with() simple
            $videoBasic = VideoModel::with('tags')->find($id);
            $results['basic_with'] = $videoBasic ? [
                'video' => $videoBasic->toArray(),
                'tags' => $videoBasic->tags->map(function ($tag) {
                    $data = $tag->toArray();
                    if ($tag->pivot) {
                        $data['pivot'] = $tag->pivot->toArray();
                    }
                    return $data;
                })->all(),
                'tags_count' => $videoBasic->tags->count(),
            ] : ['error' => 'Video not found'];

            // Test 2: Complex with() callback - multiple conditions on tags
            $videoComplex = VideoModel::with(['tags' => function ($q) use ($activeTagsOnly, $tagIdsArray, $tagNameContains, $tagColor) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
                if (!empty($tagIdsArray)) {
                    $q->whereIn('tags.id', $tagIdsArray);
                }
                if (!empty($tagNameContains)) {
                    $q->where('name', 'like', '%' . $tagNameContains . '%');
                }
                if (!empty($tagColor)) {
                    $q->where('color', $tagColor);
                }
                $q->orderBy('name', 'ASC');
            }])->find($id);
            $results['complex_with_callback'] = $videoComplex ? [
                'video' => $videoComplex->toArray(),
                'tags' => $videoComplex->tags->map(function ($tag) {
                    $data = $tag->toArray();
                    if ($tag->pivot) {
                        $data['pivot'] = $tag->pivot->toArray();
                    }
                    return $data;
                })->all(),
                'tags_count' => $videoComplex->tags->count(),
            ] : ['error' => 'Video not found'];

            // Test 3: whereHas with complex conditions
            $videosWithTags = VideoModel::whereHas('tags', function ($q) use ($activeTagsOnly, $tagNameContains, $tagIdsArray) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
                if (!empty($tagNameContains)) {
                    $q->where('name', 'like', '%' . $tagNameContains . '%');
                }
                if (!empty($tagIdsArray)) {
                    $q->whereIn('tags.id', $tagIdsArray);
                }
            })
                ->withCount(['tags' => function ($q) use ($activeTagsOnly) {
                    if ($activeTagsOnly) {
                        $q->where('is_active', true);
                    }
                }])
                ->having('tags_count', '>=', $minTags > 0 ? $minTags : 1)
                ->where(function ($q) use ($publishedOnly, $minViews) {
                    if ($publishedOnly) {
                        $q->where('is_published', true);
                    }
                    if ($minViews > 0) {
                        $q->where('views', '>=', $minViews);
                    }
                })
                ->where('duration', '>', 0)
                ->with(['tags' => function ($q) use ($activeTagsOnly) {
                    if ($activeTagsOnly) {
                        $q->where('is_active', true);
                    }
                    $q->orderBy('name', 'ASC');
                }])
                ->orderBy('tags_count', 'DESC')
                ->limit(10)
                ->get();
            $results['where_has_with_aggregations'] = $videosWithTags->map(function ($v) {
                return [
                    'id' => $v->id,
                    'title' => $v->title,
                    'views' => $v->views,
                    'duration' => $v->duration,
                    'tags_count' => $v->tags_count,
                    'tags' => $v->tags->map(function ($tag) {
                        $data = $tag->toArray();
                        if ($tag->pivot) {
                            $data['pivot'] = $tag->pivot->toArray();
                        }
                        return $data;
                    })->all(),
                ];
            })->all();

            // Test 4: Nested whereHas with multiple conditions and subqueries
            $videosWithComplexTags = VideoModel::whereHas('tags', function ($q) use ($activeTagsOnly, $tagNameContains) {
                $q->where('is_active', $activeTagsOnly ? true : '!=', null)
                    ->where(function ($subQ) use ($tagNameContains) {
                        if (!empty($tagNameContains)) {
                            $subQ->where('name', 'like', '%' . $tagNameContains . '%')
                                ->orWhere('slug', 'like', '%' . $tagNameContains . '%');
                        }
                    })
                    ->whereIn('id', function ($subQ) {
                        $subQ->select('id')
                            ->from('tags')
                            ->where('is_active', true)
                            ->limit(100);
                    });
            })
                ->where(function ($q) use ($publishedOnly, $minViews) {
                    if ($publishedOnly) {
                        $q->where('is_published', true);
                    }
                    if ($minViews > 0) {
                        $q->where('views', '>=', $minViews);
                    }
                })
                ->where('duration', '>', 60)
                ->with(['tags' => function ($q) use ($activeTagsOnly, $tagIdsArray) {
                    if ($activeTagsOnly) {
                        $q->where('is_active', true);
                    }
                    if (!empty($tagIdsArray)) {
                        $q->whereIn('tags.id', $tagIdsArray);
                    }
                    $q->orderBy('name', 'ASC');
                }])
                ->withCount(['tags as active_tags_count' => function ($q) {
                    $q->where('is_active', true);
                }])
                ->orderBy('views', 'DESC')
                ->limit(10)
                ->get();
            $results['nested_where_has_complex'] = $videosWithComplexTags->map(function ($v) {
                return [
                    'id' => $v->id,
                    'title' => $v->title,
                    'views' => $v->views,
                    'duration' => $v->duration,
                    'active_tags_count' => $v->active_tags_count,
                    'tags' => $v->tags->map(function ($tag) {
                        $data = $tag->toArray();
                        if ($tag->pivot) {
                            $data['pivot'] = $tag->pivot->toArray();
                        }
                        return $data;
                    })->all(),
                ];
            })->all();

            // Test 5: Multiple whereHas with orWhereHas
            $videosWithMultipleTagConditions = VideoModel::whereHas('tags', function ($q) {
                $q->where('is_active', true)
                    ->where('name', 'like', '%video%');
            })
                ->orWhereHas('tags', function ($q) {
                    $q->where('is_active', true)
                        ->where('color', 'like', '%red%');
                })
                ->with(['tags' => function ($q) {
                    $q->where(function ($subQ) {
                        $subQ->where('is_active', true)
                            ->orWhere('name', 'like', '%featured%');
                    })
                        ->orderBy('name', 'ASC');
                }])
                ->where('is_published', true)
                ->where('duration', '>', 60)
                ->limit(10)
                ->get();
            $results['multiple_where_has'] = $videosWithMultipleTagConditions->map(function ($v) {
                return [
                    'id' => $v->id,
                    'title' => $v->title,
                    'tags' => $v->tags->map(function ($tag) {
                        $data = $tag->toArray();
                        if ($tag->pivot) {
                            $data['pivot'] = $tag->pivot->toArray();
                        }
                        return $data;
                    })->all(),
                ];
            })->all();

            // Test 6: Nested relationship - Post with tags and all other relationships
            $postsWithNestedTags = PostModel::whereHas('tags', function ($q) use ($activeTagsOnly) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
            })
                ->with([
                    'tags' => function ($q) use ($activeTagsOnly) {
                        if ($activeTagsOnly) {
                            $q->where('is_active', true);
                        }
                        $q->orderBy('name', 'ASC');
                    },
                    'image' => function ($q) {
                        // Nested: Post -> Tags + Image
                        $q->orderBy('size', 'DESC');
                    },
                    'comments' => function ($q) {
                        // Nested: Post -> Tags + Comments
                        $q->where('is_approved', true)
                            ->orderBy('created_at', 'DESC')
                            ->limit(5);
                    },
                    'comments.commentable' => function ($q) {
                        // Deep nested: Post -> Tags + Comments -> Commentable
                    }
                ])
                ->where('is_published', true)
                ->limit(5)
                ->get();
            $results['nested_tags_with_relationships'] = $postsWithNestedTags->map(function ($p) {
                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'tags' => $p->tags->map(function ($tag) {
                        $data = $tag->toArray();
                        if ($tag->pivot) {
                            $data['pivot'] = $tag->pivot->toArray();
                        }
                        return $data;
                    })->all(),
                    'image' => $p->image ? $p->image->toArray() : null,
                    'comments' => $p->comments->map(function ($comment) {
                        return [
                            'comment' => $comment->toArray(),
                            'commentable' => $comment->commentable ? $comment->commentable->toArray() : null,
                        ];
                    })->all(),
                ];
            })->all();

            // Test 7: Deep nested - Post with tags, and tags used by other Posts/Videos
            $postsWithTaggedItems = PostModel::whereHas('tags', function ($q) use ($activeTagsOnly) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
            })
                ->with([
                    'tags' => function ($q) use ($activeTagsOnly) {
                        if ($activeTagsOnly) {
                            $q->where('is_active', true);
                        }
                        $q->orderBy('name', 'ASC');
                    }
                ])
                ->where('is_published', true)
                ->limit(3)
                ->get();

            // Load other items with same tags (reverse relationship through tags)
            $results['deep_nested_tagged_items'] = $postsWithTaggedItems->map(function ($p) {
                $tagIds = $p->tags->pluck('id')->all();
                $relatedPosts = PostModel::whereHas('tags', function ($q) use ($tagIds) {
                    $q->whereIn('tags.id', $tagIds);
                })
                    ->where('id', '!=', $p->id)
                    ->with(['tags' => function ($q) use ($tagIds) {
                        $q->whereIn('tags.id', $tagIds);
                    }])
                    ->limit(3)
                    ->get();

                $relatedVideos = VideoModel::whereHas('tags', function ($q) use ($tagIds) {
                    $q->whereIn('tags.id', $tagIds);
                })
                    ->with(['tags' => function ($q) use ($tagIds) {
                        $q->whereIn('tags.id', $tagIds);
                    }])
                    ->limit(3)
                    ->get();

                return [
                    'post' => $p->toArray(),
                    'tags' => $p->tags->map(function ($tag) {
                        $data = $tag->toArray();
                        if ($tag->pivot) {
                            $data['pivot'] = $tag->pivot->toArray();
                        }
                        return $data;
                    })->all(),
                    'related_posts_with_same_tags' => $relatedPosts->toArray(),
                    'related_videos_with_same_tags' => $relatedVideos->toArray(),
                ];
            })->all();
        } elseif ($type === 'video') {
            // Test 6: Nested relationship - Video with tags and all other relationships
            $videosWithNestedTags = VideoModel::whereHas('tags', function ($q) use ($activeTagsOnly) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
            })
                ->with([
                    'tags' => function ($q) use ($activeTagsOnly) {
                        if ($activeTagsOnly) {
                            $q->where('is_active', true);
                        }
                        $q->orderBy('name', 'ASC');
                    },
                    'image' => function ($q) {
                        // Nested: Video -> Tags + Image
                        $q->orderBy('size', 'DESC');
                    },
                    'comments' => function ($q) {
                        // Nested: Video -> Tags + Comments
                        $q->where('is_approved', true)
                            ->orderBy('created_at', 'DESC')
                            ->limit(5);
                    },
                    'comments.commentable' => function ($q) {
                        // Deep nested: Video -> Tags + Comments -> Commentable
                    }
                ])
                ->where('is_published', true)
                ->where('duration', '>', 0)
                ->limit(5)
                ->get();
            $results['nested_tags_with_relationships'] = $videosWithNestedTags->map(function ($v) {
                return [
                    'id' => $v->id,
                    'title' => $v->title,
                    'tags' => $v->tags->map(function ($tag) {
                        $data = $tag->toArray();
                        if ($tag->pivot) {
                            $data['pivot'] = $tag->pivot->toArray();
                        }
                        return $data;
                    })->all(),
                    'image' => $v->image ? $v->image->toArray() : null,
                    'comments' => $v->comments->map(function ($comment) {
                        return [
                            'comment' => $comment->toArray(),
                            'commentable' => $comment->commentable ? $comment->commentable->toArray() : null,
                        ];
                    })->all(),
                ];
            })->all();

            // Test 7: Deep nested - Video with tags, and tags used by other Posts/Videos
            $videosWithTaggedItems = VideoModel::whereHas('tags', function ($q) use ($activeTagsOnly) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
            })
                ->with([
                    'tags' => function ($q) use ($activeTagsOnly) {
                        if ($activeTagsOnly) {
                            $q->where('is_active', true);
                        }
                        $q->orderBy('name', 'ASC');
                    }
                ])
                ->where('is_published', true)
                ->where('duration', '>', 60)
                ->limit(3)
                ->get();

            // Load other items with same tags (reverse relationship through tags)
            $results['deep_nested_tagged_items'] = $videosWithTaggedItems->map(function ($v) {
                $tagIds = $v->tags->pluck('id')->all();
                $relatedPosts = PostModel::whereHas('tags', function ($q) use ($tagIds) {
                    $q->whereIn('tags.id', $tagIds);
                })
                    ->with(['tags' => function ($q) use ($tagIds) {
                        $q->whereIn('tags.id', $tagIds);
                    }])
                    ->limit(3)
                    ->get();

                $relatedVideos = VideoModel::whereHas('tags', function ($q) use ($tagIds) {
                    $q->whereIn('tags.id', $tagIds);
                })
                    ->where('id', '!=', $v->id)
                    ->with(['tags' => function ($q) use ($tagIds) {
                        $q->whereIn('tags.id', $tagIds);
                    }])
                    ->limit(3)
                    ->get();

                return [
                    'video' => $v->toArray(),
                    'tags' => $v->tags->map(function ($tag) {
                        $data = $tag->toArray();
                        if ($tag->pivot) {
                            $data['pivot'] = $tag->pivot->toArray();
                        }
                        return $data;
                    })->all(),
                    'related_posts_with_same_tags' => $relatedPosts->toArray(),
                    'related_videos_with_same_tags' => $relatedVideos->toArray(),
                ];
            })->all();
        }

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'MorphToMany relationship test with complex queries and nested relationships',
            'data' => $results,
            'queries' => $queries,
        ]);
    }

    /**
     * Create a Post with polymorphic relationships.
     *
     * POST /api/polymorphic/posts
     *
     * Body (JSON):
     * {
     *   "title": "Post Title",
     *   "slug": "post-title" (optional, auto-generated from title),
     *   "content": "Post content",
     *   "views": 0 (optional, default: 0),
     *   "is_published": true (optional, default: true),
     *   "tag_ids": [1, 2, 3] (optional, must be valid tag IDs from database),
     *   "image": {
     *     "url": "https://example.com/image.jpg",
     *     "alt_text": "Post image" (optional),
     *     "width": 800 (optional, default: 0),
     *     "height": 600 (optional, default: 0),
     *     "size": 102400 (optional, default: 0)
     *   } (optional)
     * }
     *
     * Example with real tag IDs:
     * 1. First get available tag IDs: GET /api/polymorphic/available-ids
     * 2. Use real tag IDs in request:
     * POST /api/polymorphic/posts
     * {
     *   "title": "My First Post",
     *   "content": "This is my first post content",
     *   "tag_ids": [1, 2, 3],
     *   "image": {
     *     "url": "https://picsum.photos/800/600",
     *     "alt_text": "Featured image",
     *     "width": 800,
     *     "height": 600,
     *     "size": 102400
     *   }
     * }
     */
    public function createPost(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $data = $request->json();

        $result = DB::transaction(function () use ($data) {
            // Create post
            $post = PostModel::create([
                'title' => $data['title'] ?? 'Test Post',
                'slug' => $data['slug'] ?? strtolower(str_replace(' ', '-', $data['title'] ?? 'test-post')),
                'content' => $data['content'] ?? null,
                'views' => $data['views'] ?? 0,
                'is_published' => $data['is_published'] ?? true,
            ]);

            // Create image (MorphOne)
            if (isset($data['image']) && is_array($data['image'])) {
                $post->image()->create([
                    'url' => $data['image']['url'] ?? 'https://example.com/default.jpg',
                    'alt_text' => $data['image']['alt_text'] ?? null,
                    'width' => $data['image']['width'] ?? 0,
                    'height' => $data['image']['height'] ?? 0,
                    'size' => $data['image']['size'] ?? 0,
                ]);
            }

            // Attach tags (MorphToMany)
            if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
                $validTagIds = TagModel::whereIn('id', $data['tag_ids'])
                    ->get()
                    ->pluck('id')
                    ->values()
                    ->all();
                if (!empty($validTagIds)) {
                    $post->tags()->attach($validTagIds);
                }
            }

            // Load relationships
            $post->load(['image', 'tags']);

            return $post;
        });

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'Post created successfully',
            'data' => $result->toArray(),
            'queries' => $queries,
        ], 201);
    }

    /**
     * Create a Video with polymorphic relationships.
     *
     * POST /api/polymorphic/videos
     *
     * Body (JSON):
     * {
     *   "title": "Video Title",
     *   "slug": "video-title" (optional, auto-generated from title),
     *   "description": "Video description" (optional),
     *   "video_url": "https://example.com/video.mp4" (optional),
     *   "duration": 3600 (optional, default: 0, in seconds),
     *   "views": 0 (optional, default: 0),
     *   "is_published": true (optional, default: true),
     *   "tag_ids": [1, 2, 3] (optional, must be valid tag IDs from database),
     *   "image": {
     *     "url": "https://example.com/thumbnail.jpg",
     *     "alt_text": "Video thumbnail" (optional),
     *     "width": 1280 (optional, default: 0),
     *     "height": 720 (optional, default: 0),
     *     "size": 204800 (optional, default: 0)
     *   } (optional)
     * }
     *
     * Example with real tag IDs:
     * 1. First get available tag IDs: GET /api/polymorphic/available-ids
     * 2. Use real tag IDs in request:
     * POST /api/polymorphic/videos
     * {
     *   "title": "My First Video",
     *   "description": "This is my first video description",
     *   "video_url": "https://example.com/video.mp4",
     *   "duration": 3600,
     *   "tag_ids": [1, 2, 3],
     *   "image": {
     *     "url": "https://picsum.photos/1280/720",
     *     "alt_text": "Video thumbnail",
     *     "width": 1280,
     *     "height": 720,
     *     "size": 204800
     *   }
     * }
     */
    public function createVideo(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $data = $request->json();

        $result = DB::transaction(function () use ($data) {
            // Create video
            $video = VideoModel::create([
                'title' => $data['title'] ?? 'Test Video',
                'slug' => $data['slug'] ?? strtolower(str_replace(' ', '-', $data['title'] ?? 'test-video')),
                'description' => $data['description'] ?? null,
                'video_url' => $data['video_url'] ?? null,
                'duration' => $data['duration'] ?? 0,
                'views' => $data['views'] ?? 0,
                'is_published' => $data['is_published'] ?? true,
            ]);

            // Create image (MorphOne)
            if (isset($data['image']) && is_array($data['image'])) {
                $video->image()->create([
                    'url' => $data['image']['url'] ?? 'https://example.com/default.jpg',
                    'alt_text' => $data['image']['alt_text'] ?? null,
                    'width' => $data['image']['width'] ?? 0,
                    'height' => $data['image']['height'] ?? 0,
                    'size' => $data['image']['size'] ?? 0,
                ]);
            }

            // Attach tags (MorphToMany)
            if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
                $validTagIds = TagModel::whereIn('id', $data['tag_ids'])
                    ->get()
                    ->pluck('id')
                    ->values()
                    ->all();
                if (!empty($validTagIds)) {
                    $video->tags()->attach($validTagIds);
                }
            }

            // Load relationships
            $video->load(['image', 'tags']);

            return $video;
        });

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'Video created successfully',
            'data' => $result->toArray(),
            'queries' => $queries,
        ], 201);
    }

    /**
     * Create a Comment for Post or Video.
     *
     * POST /api/polymorphic/comments
     *
     * Body (JSON):
     * {
     *   "commentable_type": "post" (required, must be "post" or "video"),
     *   "commentable_id": 1 (required, must be valid post/video ID from database),
     *   "content": "Comment content" (required),
     *   "user_id": 1 (optional, must be valid user ID if provided),
     *   "is_approved": true (optional, default: false)
     * }
     *
     * Example with real data:
     * 1. First get available IDs: GET /api/polymorphic/available-ids
     * 2. Use real post ID:
     * POST /api/polymorphic/comments
     * {
     *   "commentable_type": "post",
     *   "commentable_id": 1,
     *   "content": "This is a great post!",
     *   "is_approved": true
     * }
     *
     * 3. Use real video ID:
     * POST /api/polymorphic/comments
     * {
     *   "commentable_type": "video",
     *   "commentable_id": 1,
     *   "content": "Awesome video!",
     *   "is_approved": true
     * }
     */
    public function createComment(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $data = $request->json();

        // Map type to model class
        $typeMap = [
            'post' => PostModel::class,
            'video' => VideoModel::class,
        ];

        $commentableType = $data['commentable_type'] ?? 'post';
        $commentableId = (int) ($data['commentable_id'] ?? 1);

        if (!isset($typeMap[$commentableType])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid commentable_type. Use: post or video',
            ], 400);
        }

        // Verify commentable exists
        $modelClass = $typeMap[$commentableType];
        $commentable = $modelClass::find($commentableId);
        if (!$commentable) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($commentableType) . ' not found',
            ], 404);
        }

        $result = DB::transaction(function () use ($data, $modelClass, $commentableId) {
            $comment = CommentModel::create([
                'commentable_type' => $modelClass,
                'commentable_id' => $commentableId,
                'content' => $data['content'] ?? 'Test comment',
                'user_id' => $data['user_id'] ?? null,
                'is_approved' => $data['is_approved'] ?? false,
            ]);

            // Load relationship
            $comment->load('commentable');

            return $comment;
        });

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'Comment created successfully',
            'data' => $result->toArray(),
            'queries' => $queries,
        ], 201);
    }

    /**
     * Test all polymorphic relationships together with complex queries.
     *
     * GET /api/polymorphic/test-all?post_id=1&video_id=1&comment_id=1&min_views=100&published_only=true&min_tags=2
     *
     * Parameters (all optional, but recommended to use real IDs):
     * - post_id: ID of post (optional, must exist in database if provided)
     * - video_id: ID of video (optional, must exist in database if provided)
     * - comment_id: ID of comment (optional, must exist in database if provided)
     * - min_views: Minimum views for posts/videos (optional, default: 0)
     * - published_only: Filter only published posts/videos (optional, default: false, values: true/false)
     * - min_tags: Minimum number of tags (optional, default: 0)
     * - active_tags_only: Filter only active tags (optional, default: false, values: true/false)
     * - approved_comments_only: Filter only approved comments (optional, default: false, values: true/false)
     * - min_image_size: Minimum image size in bytes (optional, default: 0)
     * - min_image_width: Minimum image width (optional, default: 0)
     * - min_image_height: Minimum image height (optional, default: 0)
     *
     * Example with complex queries:
     * GET /api/polymorphic/test-all?post_id=1&video_id=1&comment_id=1&min_views=100&published_only=true&min_tags=2
     * GET /api/polymorphic/test-all?min_views=500&active_tags_only=true&approved_comments_only=true&min_image_size=100000
     */
    public function testAllPolymorphic(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $postId = (int) $request->get('post_id', 1);
        $videoId = (int) $request->get('video_id', 1);
        $commentId = (int) $request->get('comment_id', 1);
        $minViews = (int) $request->get('min_views', 0);
        $publishedOnly = $request->get('published_only', 'false') === 'true';
        $minTags = (int) $request->get('min_tags', 0);
        $activeTagsOnly = $request->get('active_tags_only', 'false') === 'true';
        $approvedCommentsOnly = $request->get('approved_comments_only', 'false') === 'true';
        $minImageSize = (int) $request->get('min_image_size', 0);
        $minImageWidth = (int) $request->get('min_image_width', 0);
        $minImageHeight = (int) $request->get('min_image_height', 0);

        $results = [];

        // Test 1: MorphOne - Post has one Image with complex conditions
        $post = PostModel::with(['image' => function ($q) use ($minImageSize, $minImageWidth, $minImageHeight) {
            if ($minImageSize > 0) {
                $q->where('size', '>=', $minImageSize);
            }
            if ($minImageWidth > 0) {
                $q->where('width', '>=', $minImageWidth);
            }
            if ($minImageHeight > 0) {
                $q->where('height', '>=', $minImageHeight);
            }
            $q->orderBy('size', 'DESC');
        }])->find($postId);
        if ($post) {
            $results['post_with_image'] = [
                'post' => $post->toArray(),
                'image' => $post->image ? $post->image->toArray() : null,
            ];
        }

        // Test 2: MorphOne - Video has one Image with complex conditions
        $video = VideoModel::with(['image' => function ($q) use ($minImageSize, $minImageWidth, $minImageHeight) {
            if ($minImageSize > 0) {
                $q->where('size', '>=', $minImageSize);
            }
            if ($minImageWidth > 0) {
                $q->where('width', '>=', $minImageWidth);
            }
            if ($minImageHeight > 0) {
                $q->where('height', '>=', $minImageHeight);
            }
            $q->orderBy('size', 'DESC');
        }])->find($videoId);
        if ($video) {
            $results['video_with_image'] = [
                'video' => $video->toArray(),
                'image' => $video->image ? $video->image->toArray() : null,
            ];
        }

        // Test 3: MorphMany - Post has many Comments with complex conditions
        if ($post) {
            $post->load(['comments' => function ($q) use ($approvedCommentsOnly) {
                if ($approvedCommentsOnly) {
                    $q->where('is_approved', true);
                }
                $q->orderBy('created_at', 'DESC')
                    ->orderBy('id', 'DESC')
                    ->limit(10);
            }]);
            $results['post_with_comments'] = [
                'post' => $post->toArray(),
                'comments' => $post->comments ? $post->comments->toArray() : [],
                'comments_count' => $post->comments ? $post->comments->count() : 0,
            ];
        }

        // Test 4: MorphMany - Video has many Comments with complex conditions
        if ($video) {
            $video->load(['comments' => function ($q) use ($approvedCommentsOnly) {
                if ($approvedCommentsOnly) {
                    $q->where('is_approved', true);
                }
                $q->orderBy('created_at', 'DESC')
                    ->orderBy('id', 'DESC')
                    ->limit(10);
            }]);
            $results['video_with_comments'] = [
                'video' => $video->toArray(),
                'comments' => $video->comments ? $video->comments->toArray() : [],
                'comments_count' => $video->comments ? $video->comments->count() : 0,
            ];
        }

        // Test 5: MorphTo - Comment belongs to Post/Video with whereHasMorph
        $comment = CommentModel::whereHasMorph('commentable', [PostModel::class, VideoModel::class], function ($q) use ($minViews, $publishedOnly) {
            if ($minViews > 0) {
                $q->where('views', '>=', $minViews);
            }
            if ($publishedOnly) {
                $q->where('is_published', true);
            }
        })
            ->where(function ($q) use ($approvedCommentsOnly) {
                if ($approvedCommentsOnly) {
                    $q->where('is_approved', true);
                }
            })
            ->with('commentable')
            ->find($commentId);
        if ($comment) {
            $results['comment_with_commentable'] = [
                'comment' => $comment->toArray(),
                'commentable' => $comment->commentable ? $comment->commentable->toArray() : null,
                'commentable_type' => $comment->commentable_type,
            ];
        }

        // Test 6: MorphToMany - Post has many Tags with complex conditions
        if ($post) {
            $post->load(['tags' => function ($q) use ($activeTagsOnly) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
                $q->orderBy('name', 'ASC');
            }]);
            $results['post_with_tags'] = [
                'post' => $post->toArray(),
                'tags' => $post->tags->map(function ($tag) {
                    $data = $tag->toArray();
                    if ($tag->pivot) {
                        $data['pivot'] = $tag->pivot->toArray();
                    }
                    return $data;
                })->all(),
                'tags_count' => $post->tags->count(),
            ];
        }

        // Test 7: MorphToMany - Video has many Tags with complex conditions
        if ($video) {
            $video->load(['tags' => function ($q) use ($activeTagsOnly) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
                $q->orderBy('name', 'ASC');
            }]);
            $results['video_with_tags'] = [
                'video' => $video->toArray(),
                'tags' => $video->tags->map(function ($tag) {
                    $data = $tag->toArray();
                    if ($tag->pivot) {
                        $data['pivot'] = $tag->pivot->toArray();
                    }
                    return $data;
                })->all(),
                'tags_count' => $video->tags->count(),
            ];
        }

        // Test 8: Complex combined query - Posts with all relationships
        $postsWithAllRelationships = PostModel::whereHas('image', function ($q) use ($minImageSize) {
            if ($minImageSize > 0) {
                $q->where('size', '>=', $minImageSize);
            }
        })
            ->whereHas('comments', function ($q) use ($approvedCommentsOnly) {
                if ($approvedCommentsOnly) {
                    $q->where('is_approved', true);
                }
            })
            ->whereHas('tags', function ($q) use ($activeTagsOnly, $minTags) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
            })
            ->withCount(['tags' => function ($q) use ($activeTagsOnly) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
            }])
            ->withCount(['comments' => function ($q) use ($approvedCommentsOnly) {
                if ($approvedCommentsOnly) {
                    $q->where('is_approved', true);
                }
            }])
            ->having('tags_count', '>=', $minTags > 0 ? $minTags : 1)
            ->where(function ($q) use ($publishedOnly, $minViews) {
                if ($publishedOnly) {
                    $q->where('is_published', true);
                }
                if ($minViews > 0) {
                    $q->where('views', '>=', $minViews);
                }
            })
            ->with([
                'image' => function ($q) {
                    $q->orderBy('size', 'DESC');
                },
                'comments' => function ($q) use ($approvedCommentsOnly) {
                    if ($approvedCommentsOnly) {
                        $q->where('is_approved', true);
                    }
                    $q->orderBy('created_at', 'DESC')->limit(5);
                },
                'tags' => function ($q) use ($activeTagsOnly) {
                    if ($activeTagsOnly) {
                        $q->where('is_active', true);
                    }
                    $q->orderBy('name', 'ASC');
                }
            ])
            ->orderBy('views', 'DESC')
            ->limit(10)
            ->get();
        $results['posts_with_all_relationships'] = $postsWithAllRelationships->map(function ($p) {
            return [
                'id' => $p->id,
                'title' => $p->title,
                'views' => $p->views,
                'tags_count' => $p->tags_count,
                'comments_count' => $p->comments_count,
                'image' => $p->image ? $p->image->toArray() : null,
                'comments' => $p->comments->toArray(),
                'tags' => $p->tags->map(function ($tag) {
                    $data = $tag->toArray();
                    if ($tag->pivot) {
                        $data['pivot'] = $tag->pivot->toArray();
                    }
                    return $data;
                })->all(),
            ];
        })->all();

        // Test 9: Complex combined query - Videos with all relationships
        $videosWithAllRelationships = VideoModel::whereHas('image', function ($q) use ($minImageSize) {
            if ($minImageSize > 0) {
                $q->where('size', '>=', $minImageSize);
            }
        })
            ->whereHas('comments', function ($q) use ($approvedCommentsOnly) {
                if ($approvedCommentsOnly) {
                    $q->where('is_approved', true);
                }
            })
            ->whereHas('tags', function ($q) use ($activeTagsOnly, $minTags) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
            })
            ->withCount(['tags' => function ($q) use ($activeTagsOnly) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
            }])
            ->withCount(['comments' => function ($q) use ($approvedCommentsOnly) {
                if ($approvedCommentsOnly) {
                    $q->where('is_approved', true);
                }
            }])
            ->having('tags_count', '>=', $minTags > 0 ? $minTags : 1)
            ->where(function ($q) use ($publishedOnly, $minViews) {
                if ($publishedOnly) {
                    $q->where('is_published', true);
                }
                if ($minViews > 0) {
                    $q->where('views', '>=', $minViews);
                }
            })
            ->where('duration', '>', 0)
            ->with([
                'image' => function ($q) {
                    $q->orderBy('size', 'DESC');
                },
                'comments' => function ($q) use ($approvedCommentsOnly) {
                    if ($approvedCommentsOnly) {
                        $q->where('is_approved', true);
                    }
                    $q->orderBy('created_at', 'DESC')->limit(5);
                },
                'tags' => function ($q) use ($activeTagsOnly) {
                    if ($activeTagsOnly) {
                        $q->where('is_active', true);
                    }
                    $q->orderBy('name', 'ASC');
                }
            ])
            ->orderBy('views', 'DESC')
            ->limit(10)
            ->get();
        $results['videos_with_all_relationships'] = $videosWithAllRelationships->map(function ($v) {
            return [
                'id' => $v->id,
                'title' => $v->title,
                'views' => $v->views,
                'duration' => $v->duration,
                'tags_count' => $v->tags_count,
                'comments_count' => $v->comments_count,
                'image' => $v->image ? $v->image->toArray() : null,
                'comments' => $v->comments->toArray(),
                'tags' => $v->tags->map(function ($tag) {
                    $data = $tag->toArray();
                    if ($tag->pivot) {
                        $data['pivot'] = $tag->pivot->toArray();
                    }
                    return $data;
                })->all(),
            ];
        })->all();

        // Test 10: Comments with whereHasMorph for both Post and Video
        $commentsWithBothTypes = CommentModel::whereHasMorph('commentable', [PostModel::class], function ($q) use ($minViews, $publishedOnly) {
            if ($minViews > 0) {
                $q->where('views', '>=', $minViews);
            }
            if ($publishedOnly) {
                $q->where('is_published', true);
            }
        })
            ->orWhereHasMorph('commentable', [VideoModel::class], function ($q) use ($minViews, $publishedOnly) {
                if ($minViews > 0) {
                    $q->where('views', '>=', $minViews);
                }
                if ($publishedOnly) {
                    $q->where('is_published', true);
                }
                $q->where('duration', '>', 0);
            })
            ->where(function ($q) use ($approvedCommentsOnly) {
                if ($approvedCommentsOnly) {
                    $q->where('is_approved', true);
                }
            })
            ->with('commentable')
            ->orderBy('created_at', 'DESC')
            ->limit(20)
            ->get();
        $results['comments_with_both_types'] = $commentsWithBothTypes->map(function ($c) {
            return [
                'comment' => $c->toArray(),
                'commentable' => $c->commentable ? $c->commentable->toArray() : null,
                'commentable_type' => $c->commentable_type,
            ];
        })->all();

        // Test 11: Ultra complex nested - Posts with all relationships and nested relationships
        $postsWithUltraNested = PostModel::whereHas('image', function ($q) use ($minImageSize) {
            if ($minImageSize > 0) {
                $q->where('size', '>=', $minImageSize);
            }
        })
            ->whereHas('comments', function ($q) use ($approvedCommentsOnly) {
                if ($approvedCommentsOnly) {
                    $q->where('is_approved', true);
                }
            })
            ->whereHas('tags', function ($q) use ($activeTagsOnly) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
            })
            ->with([
                'image.imageable' => function ($q) {
                    // Nested: Post -> Image -> Imageable
                },
                'image' => function ($q) {
                    $q->orderBy('size', 'DESC');
                },
                'comments.commentable' => function ($q) {
                    // Nested: Post -> Comments -> Commentable
                },
                'comments.commentable.image' => function ($q) {
                    // Deep nested: Post -> Comments -> Commentable -> Image
                    $q->orderBy('size', 'DESC');
                },
                'comments.commentable.tags' => function ($q) {
                    // Deep nested: Post -> Comments -> Commentable -> Tags
                    $q->where('is_active', true)
                        ->orderBy('name', 'ASC');
                },
                'comments' => function ($q) use ($approvedCommentsOnly) {
                    if ($approvedCommentsOnly) {
                        $q->where('is_approved', true);
                    }
                    $q->orderBy('created_at', 'DESC')->limit(5);
                },
                'tags' => function ($q) use ($activeTagsOnly) {
                    if ($activeTagsOnly) {
                        $q->where('is_active', true);
                    }
                    $q->orderBy('name', 'ASC');
                }
            ])
            ->where(function ($q) use ($publishedOnly, $minViews) {
                if ($publishedOnly) {
                    $q->where('is_published', true);
                }
                if ($minViews > 0) {
                    $q->where('views', '>=', $minViews);
                }
            })
            ->limit(3)
            ->get();
        $results['ultra_nested_posts'] = $postsWithUltraNested->map(function ($p) {
            return [
                'id' => $p->id,
                'title' => $p->title,
                'image' => $p->image ? [
                    'image' => $p->image->toArray(),
                    'imageable' => $p->image->imageable ? $p->image->imageable->toArray() : null,
                ] : null,
                'comments' => $p->comments->map(function ($comment) {
                    $commentable = $comment->commentable;
                    return [
                        'comment' => $comment->toArray(),
                        'commentable' => $commentable ? [
                            'commentable' => $commentable->toArray(),
                            'nested_image' => $commentable->image ? $commentable->image->toArray() : null,
                            'nested_tags' => $commentable->tags ? $commentable->tags->map(function ($tag) {
                                $data = $tag->toArray();
                                if ($tag->pivot) {
                                    $data['pivot'] = $tag->pivot->toArray();
                                }
                                return $data;
                            })->all() : [],
                        ] : null,
                    ];
                })->all(),
                'tags' => $p->tags->map(function ($tag) {
                    $data = $tag->toArray();
                    if ($tag->pivot) {
                        $data['pivot'] = $tag->pivot->toArray();
                    }
                    return $data;
                })->all(),
            ];
        })->all();

        // Test 12: Ultra complex nested - Videos with all relationships and nested relationships
        $videosWithUltraNested = VideoModel::whereHas('image', function ($q) use ($minImageSize) {
            if ($minImageSize > 0) {
                $q->where('size', '>=', $minImageSize);
            }
        })
            ->whereHas('comments', function ($q) use ($approvedCommentsOnly) {
                if ($approvedCommentsOnly) {
                    $q->where('is_approved', true);
                }
            })
            ->whereHas('tags', function ($q) use ($activeTagsOnly) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
            })
            ->with([
                'image.imageable' => function ($q) {
                    // Nested: Video -> Image -> Imageable
                },
                'image' => function ($q) {
                    $q->orderBy('size', 'DESC');
                },
                'comments.commentable' => function ($q) {
                    // Nested: Video -> Comments -> Commentable
                },
                'comments.commentable.image' => function ($q) {
                    // Deep nested: Video -> Comments -> Commentable -> Image
                    $q->orderBy('size', 'DESC');
                },
                'comments.commentable.tags' => function ($q) {
                    // Deep nested: Video -> Comments -> Commentable -> Tags
                    $q->where('is_active', true)
                        ->orderBy('name', 'ASC');
                },
                'comments' => function ($q) use ($approvedCommentsOnly) {
                    if ($approvedCommentsOnly) {
                        $q->where('is_approved', true);
                    }
                    $q->orderBy('created_at', 'DESC')->limit(5);
                },
                'tags' => function ($q) use ($activeTagsOnly) {
                    if ($activeTagsOnly) {
                        $q->where('is_active', true);
                    }
                    $q->orderBy('name', 'ASC');
                }
            ])
            ->where(function ($q) use ($publishedOnly, $minViews) {
                if ($publishedOnly) {
                    $q->where('is_published', true);
                }
                if ($minViews > 0) {
                    $q->where('views', '>=', $minViews);
                }
            })
            ->where('duration', '>', 60)
            ->limit(3)
            ->get();
        $results['ultra_nested_videos'] = $videosWithUltraNested->map(function ($v) {
            return [
                'id' => $v->id,
                'title' => $v->title,
                'duration' => $v->duration,
                'image' => $v->image ? [
                    'image' => $v->image->toArray(),
                    'imageable' => $v->image->imageable ? $v->image->imageable->toArray() : null,
                ] : null,
                'comments' => $v->comments->map(function ($comment) {
                    $commentable = $comment->commentable;
                    return [
                        'comment' => $comment->toArray(),
                        'commentable' => $commentable ? [
                            'commentable' => $commentable->toArray(),
                            'nested_image' => $commentable->image ? $commentable->image->toArray() : null,
                            'nested_tags' => $commentable->tags ? $commentable->tags->map(function ($tag) {
                                $data = $tag->toArray();
                                if ($tag->pivot) {
                                    $data['pivot'] = $tag->pivot->toArray();
                                }
                                return $data;
                            })->all() : [],
                        ] : null,
                    ];
                })->all(),
                'tags' => $v->tags->map(function ($tag) {
                    $data = $tag->toArray();
                    if ($tag->pivot) {
                        $data['pivot'] = $tag->pivot->toArray();
                    }
                    return $data;
                })->all(),
            ];
        })->all();

        // Test 13: Circular nested - Comments with commentable, commentable has comments (circular)
        $commentsWithCircularNested = CommentModel::whereHasMorph('commentable', [PostModel::class, VideoModel::class], function ($q) use ($publishedOnly) {
            if ($publishedOnly) {
                $q->where('is_published', true);
            }
        })
            ->with([
                'commentable.comments.commentable' => function ($q) {
                    // Circular nested: Comment -> Commentable -> Comments -> Commentable
                },
                'commentable.comments' => function ($q) {
                    $q->where('is_approved', true)
                        ->orderBy('created_at', 'DESC')
                        ->limit(3);
                },
                'commentable' => function ($q) {
                    // Load commentable with all relationships
                },
                'commentable.image' => function ($q) {
                    $q->orderBy('size', 'DESC');
                },
                'commentable.tags' => function ($q) {
                    $q->where('is_active', true)
                        ->orderBy('name', 'ASC');
                }
            ])
            ->where('is_approved', true)
            ->limit(5)
            ->get();
        $results['circular_nested_comments'] = $commentsWithCircularNested->map(function ($c) {
            $commentable = $c->commentable;
            return [
                'comment' => $c->toArray(),
                'commentable' => $commentable ? [
                    'commentable' => $commentable->toArray(),
                    'image' => $commentable->image ? $commentable->image->toArray() : null,
                    'tags' => $commentable->tags ? $commentable->tags->map(function ($tag) {
                        $data = $tag->toArray();
                        if ($tag->pivot) {
                            $data['pivot'] = $tag->pivot->toArray();
                        }
                        return $data;
                    })->all() : [],
                    'nested_comments' => $commentable->comments ? $commentable->comments->map(function ($nestedComment) {
                        return [
                            'comment' => $nestedComment->toArray(),
                            'nested_commentable' => $nestedComment->commentable ? $nestedComment->commentable->toArray() : null,
                        ];
                    })->all() : [],
                ] : null,
            ];
        })->all();

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'All polymorphic relationships test with complex queries and nested relationships',
            'data' => $results,
            'queries' => $queries,
        ]);
    }

    /**
     * Get available IDs for testing polymorphic relationships.
     *
     * GET /api/polymorphic/available-ids
     *
     * Returns actual IDs from database that can be used for testing.
     */
    public function getAvailableIds(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $results = [
            'posts' => PostModel::pluck('id')->take(10)->all(),
            'videos' => VideoModel::pluck('id')->take(10)->all(),
            'comments' => CommentModel::pluck('id')->take(10)->all(),
            'images' => ImageModel::pluck('id')->take(10)->all(),
            'tags' => TagModel::pluck('id')->take(20)->all(),
            'sample_post_id' => PostModel::first()?->id,
            'sample_video_id' => VideoModel::first()?->id,
            'sample_comment_id' => CommentModel::first()?->id,
            'sample_tag_ids' => TagModel::take(5)->pluck('id')->all(),
        ];

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'Available IDs for testing',
            'data' => $results,
            'queries' => $queries,
        ]);
    }

    /**
     * Get sample data for testing polymorphic relationships with complex queries.
     *
     * GET /api/polymorphic/sample-data?min_views=100&published_only=true&min_tags=2&active_tags_only=true&approved_comments_only=true&min_image_size=50000
     *
     * Parameters:
     * - min_views: Minimum views for posts/videos (optional, default: 0)
     * - published_only: Filter only published posts/videos (optional, default: false, values: true/false)
     * - min_tags: Minimum number of tags (optional, default: 0)
     * - active_tags_only: Filter only active tags (optional, default: false, values: true/false)
     * - approved_comments_only: Filter only approved comments (optional, default: false, values: true/false)
     * - min_image_size: Minimum image size in bytes (optional, default: 0)
     * - min_image_width: Minimum image width (optional, default: 0)
     * - min_image_height: Minimum image height (optional, default: 0)
     * - limit: Limit number of results (optional, default: 5)
     *
     * Example with complex queries:
     * GET /api/polymorphic/sample-data?min_views=100&published_only=true&min_tags=2&active_tags_only=true
     * GET /api/polymorphic/sample-data?approved_comments_only=true&min_image_size=50000&limit=10
     */
    public function getSampleData(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $minViews = (int) $request->get('min_views', 0);
        $publishedOnly = $request->get('published_only', 'false') === 'true';
        $minTags = (int) $request->get('min_tags', 0);
        $activeTagsOnly = $request->get('active_tags_only', 'false') === 'true';
        $approvedCommentsOnly = $request->get('approved_comments_only', 'false') === 'true';
        $minImageSize = (int) $request->get('min_image_size', 0);
        $minImageWidth = (int) $request->get('min_image_width', 0);
        $minImageHeight = (int) $request->get('min_image_height', 0);
        $limit = (int) $request->get('limit', 5);

        $results = [];

        // Posts with complex relationships
        $postsQuery = PostModel::query();
        if ($publishedOnly) {
            $postsQuery->where('is_published', true);
        }
        if ($minViews > 0) {
            $postsQuery->where('views', '>=', $minViews);
        }
        if ($minTags > 0) {
            $postsQuery->whereHas('tags', function ($q) use ($activeTagsOnly) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
            })
                ->withCount(['tags' => function ($q) use ($activeTagsOnly) {
                    if ($activeTagsOnly) {
                        $q->where('is_active', true);
                    }
                }])
                ->having('tags_count', '>=', $minTags);
        }
        $results['posts'] = $postsQuery->with([
            'image' => function ($q) use ($minImageSize, $minImageWidth, $minImageHeight) {
                if ($minImageSize > 0) {
                    $q->where('size', '>=', $minImageSize);
                }
                if ($minImageWidth > 0) {
                    $q->where('width', '>=', $minImageWidth);
                }
                if ($minImageHeight > 0) {
                    $q->where('height', '>=', $minImageHeight);
                }
                $q->orderBy('size', 'DESC');
            },
            'comments' => function ($q) use ($approvedCommentsOnly) {
                if ($approvedCommentsOnly) {
                    $q->where('is_approved', true);
                }
                $q->orderBy('created_at', 'DESC')->limit(5);
            },
            'tags' => function ($q) use ($activeTagsOnly) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
                $q->orderBy('name', 'ASC');
            }
        ])
            ->orderBy('views', 'DESC')
            ->limit($limit)
            ->get()
            ->toArray();

        // Videos with complex relationships
        $videosQuery = VideoModel::query();
        if ($publishedOnly) {
            $videosQuery->where('is_published', true);
        }
        if ($minViews > 0) {
            $videosQuery->where('views', '>=', $minViews);
        }
        if ($minTags > 0) {
            $videosQuery->whereHas('tags', function ($q) use ($activeTagsOnly) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
            })
                ->withCount(['tags' => function ($q) use ($activeTagsOnly) {
                    if ($activeTagsOnly) {
                        $q->where('is_active', true);
                    }
                }])
                ->having('tags_count', '>=', $minTags);
        }
        $results['videos'] = $videosQuery->with([
            'image' => function ($q) use ($minImageSize, $minImageWidth, $minImageHeight) {
                if ($minImageSize > 0) {
                    $q->where('size', '>=', $minImageSize);
                }
                if ($minImageWidth > 0) {
                    $q->where('width', '>=', $minImageWidth);
                }
                if ($minImageHeight > 0) {
                    $q->where('height', '>=', $minImageHeight);
                }
                $q->orderBy('size', 'DESC');
            },
            'comments' => function ($q) use ($approvedCommentsOnly) {
                if ($approvedCommentsOnly) {
                    $q->where('is_approved', true);
                }
                $q->orderBy('created_at', 'DESC')->limit(5);
            },
            'tags' => function ($q) use ($activeTagsOnly) {
                if ($activeTagsOnly) {
                    $q->where('is_active', true);
                }
                $q->orderBy('name', 'ASC');
            }
        ])
            ->where('duration', '>', 0)
            ->orderBy('views', 'DESC')
            ->limit($limit)
            ->get()
            ->toArray();

        // Comments with whereHasMorph
        $commentsQuery = CommentModel::whereHasMorph('commentable', [PostModel::class, VideoModel::class], function ($q) use ($minViews, $publishedOnly) {
            if ($minViews > 0) {
                $q->where('views', '>=', $minViews);
            }
            if ($publishedOnly) {
                $q->where('is_published', true);
            }
        });
        if ($approvedCommentsOnly) {
            $commentsQuery->where('is_approved', true);
        }
        $results['comments'] = $commentsQuery->with('commentable')
            ->orderBy('created_at', 'DESC')
            ->limit($limit * 2)
            ->get()
            ->toArray();

        // Images with complex conditions
        $imagesQuery = ImageModel::query();
        if ($minImageSize > 0) {
            $imagesQuery->where('size', '>=', $minImageSize);
        }
        if ($minImageWidth > 0) {
            $imagesQuery->where('width', '>=', $minImageWidth);
        }
        if ($minImageHeight > 0) {
            $imagesQuery->where('height', '>=', $minImageHeight);
        }
        $results['images'] = $imagesQuery->with(['imageable' => function ($q) use ($publishedOnly, $minViews) {
            // Note: MorphTo doesn't support direct filtering on related model fields in with()
            // Filtering is done via whereHasMorph on the imageable relationship
        }])
            ->whereHasMorph('imageable', [PostModel::class, VideoModel::class], function ($q) use ($publishedOnly, $minViews) {
                if ($publishedOnly) {
                    $q->where('is_published', true);
                }
                if ($minViews > 0) {
                    $q->where('views', '>=', $minViews);
                }
            })
            ->orderBy('size', 'DESC')
            ->limit($limit * 2)
            ->get()
            ->toArray();

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'Sample data for polymorphic relationships with complex queries',
            'data' => $results,
            'queries' => $queries,
        ]);
    }

    /**
     * Seed sample data for testing polymorphic relationships.
     *
     * POST /api/polymorphic/seed-data
     */
    public function seedPolymorphicData(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $results = DB::transaction(function () {
            $created = [];

            // Create sample posts
            $posts = [];
            for ($i = 1; $i <= 5; $i++) {
                $post = PostModel::create([
                    'title' => "Sample Post {$i}",
                    'slug' => "sample-post-{$i}",
                    'content' => "This is the content for post {$i}",
                    'views' => rand(10, 1000),
                    'is_published' => true,
                ]);

                // Create image for post (MorphOne)
                $post->image()->create([
                    'url' => "https://example.com/post-{$i}.jpg",
                    'alt_text' => "Image for post {$i}",
                    'width' => 800,
                    'height' => 600,
                    'size' => 102400,
                ]);

                // Attach tags (MorphToMany)
                $tagIds = TagModel::inRandomOrder()->limit(rand(2, 4))->pluck('id')->all();
                if (!empty($tagIds)) {
                    $post->tags()->attach($tagIds);
                }

                $posts[] = $post;
            }
            $created['posts'] = count($posts);

            // Create sample videos
            $videos = [];
            for ($i = 1; $i <= 5; $i++) {
                $video = VideoModel::create([
                    'title' => "Sample Video {$i}",
                    'slug' => "sample-video-{$i}",
                    'description' => "This is the description for video {$i}",
                    'video_url' => "https://example.com/video-{$i}.mp4",
                    'duration' => rand(60, 3600), // 1 minute to 1 hour
                    'views' => rand(50, 5000),
                    'is_published' => true,
                ]);

                // Create image for video (MorphOne)
                $video->image()->create([
                    'url' => "https://example.com/video-thumb-{$i}.jpg",
                    'alt_text' => "Thumbnail for video {$i}",
                    'width' => 1280,
                    'height' => 720,
                    'size' => 204800,
                ]);

                // Attach tags (MorphToMany)
                $tagIds = TagModel::inRandomOrder()->limit(rand(2, 4))->pluck('id')->all();
                if (!empty($tagIds)) {
                    $video->tags()->attach($tagIds);
                }

                $videos[] = $video;
            }
            $created['videos'] = count($videos);

            // Create sample comments for posts
            $comments = [];
            foreach ($posts as $post) {
                for ($j = 1; $j <= rand(2, 5); $j++) {
                    $comment = CommentModel::create([
                        'commentable_type' => PostModel::class,
                        'commentable_id' => $post->id,
                        'content' => "Comment {$j} on post {$post->id}",
                        'user_id' => null,
                        'is_approved' => rand(0, 1) === 1,
                    ]);
                    $comments[] = $comment;
                }
            }
            $created['post_comments'] = count($comments);

            // Create sample comments for videos
            $videoComments = [];
            foreach ($videos as $video) {
                for ($j = 1; $j <= rand(2, 5); $j++) {
                    $comment = CommentModel::create([
                        'commentable_type' => VideoModel::class,
                        'commentable_id' => $video->id,
                        'content' => "Comment {$j} on video {$video->id}",
                        'user_id' => null,
                        'is_approved' => rand(0, 1) === 1,
                    ]);
                    $videoComments[] = $comment;
                }
            }
            $created['video_comments'] = count($videoComments);

            return $created;
        });

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'Sample data seeded successfully',
            'data' => $results,
            'queries' => $queries,
        ], 201);
    }
}
