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
     * Test MorphOne relationship (Post/Video has one Image).
     *
     * GET /api/polymorphic/test-morph-one?type=post&id=1
     * GET /api/polymorphic/test-morph-one?type=video&id=1
     *
     * Parameters:
     * - type: 'post' or 'video' (required)
     * - id: ID of post or video (required, must exist in database)
     *
     * Example with real data:
     * 1. First get available IDs: GET /api/polymorphic/available-ids
     * 2. Use a real post ID: GET /api/polymorphic/test-morph-one?type=post&id={real_post_id}
     * 3. Use a real video ID: GET /api/polymorphic/test-morph-one?type=video&id={real_video_id}
     *
     * Full example:
     * GET /api/polymorphic/test-morph-one?type=post&id=1
     * GET /api/polymorphic/test-morph-one?type=video&id=1
     */
    public function testMorphOne(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $type = $request->get('type', 'post'); // post or video
        $id = (int) $request->get('id', 1);

        $results = [];

        if ($type === 'post') {
            $post = PostModel::with('image')->find($id);
            if ($post) {
                $results['post'] = $post->toArray();
                $results['image'] = $post->image ? $post->image->toArray() : null;
            } else {
                $results['error'] = 'Post not found';
            }
        } elseif ($type === 'video') {
            $video = VideoModel::with('image')->find($id);
            if ($video) {
                $results['video'] = $video->toArray();
                $results['image'] = $video->image ? $video->image->toArray() : null;
            } else {
                $results['error'] = 'Video not found';
            }
        }

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'MorphOne relationship test',
            'data' => $results,
            'queries' => $queries,
        ]);
    }

    /**
     * Test MorphMany relationship (Post/Video has many Comments).
     *
     * GET /api/polymorphic/test-morph-many?type=post&id=1
     * GET /api/polymorphic/test-morph-many?type=video&id=1
     *
     * Parameters:
     * - type: 'post' or 'video' (required)
     * - id: ID of post or video (required, must exist in database)
     *
     * Example with real data:
     * 1. First get available IDs: GET /api/polymorphic/available-ids
     * 2. Use a real post ID: GET /api/polymorphic/test-morph-many?type=post&id={real_post_id}
     * 3. Use a real video ID: GET /api/polymorphic/test-morph-many?type=video&id={real_video_id}
     *
     * Full example:
     * GET /api/polymorphic/test-morph-many?type=post&id=1
     * GET /api/polymorphic/test-morph-many?type=video&id=1
     */
    public function testMorphMany(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $type = $request->get('type', 'post');
        $id = (int) $request->get('id', 1);

        $results = [];

        if ($type === 'post') {
            $post = PostModel::with(['comments' => function ($q) {
                $q->where('is_approved', true)->orderBy('created_at', 'DESC');
            }])->find($id);
            if ($post) {
                $results['post'] = $post->toArray();
                $results['comments'] = $post->comments->toArray();
                $results['comments_count'] = $post->comments->count();
            } else {
                $results['error'] = 'Post not found';
            }
        } elseif ($type === 'video') {
            $video = VideoModel::with(['comments' => function ($q) {
                $q->where('is_approved', true)->orderBy('created_at', 'DESC');
            }])->find($id);
            if ($video) {
                $results['video'] = $video->toArray();
                $results['comments'] = $video->comments->toArray();
                $results['comments_count'] = $video->comments->count();
            } else {
                $results['error'] = 'Video not found';
            }
        }

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'MorphMany relationship test',
            'data' => $results,
            'queries' => $queries,
        ]);
    }

    /**
     * Test MorphTo relationship (Comment belongs to Post/Video).
     *
     * GET /api/polymorphic/test-morph-to?comment_id=1
     *
     * Parameters:
     * - comment_id: ID of comment (required, must exist in database)
     *
     * Example with real data:
     * 1. First get available IDs: GET /api/polymorphic/available-ids
     * 2. Use a real comment ID: GET /api/polymorphic/test-morph-to?comment_id={real_comment_id}
     *
     * Full example:
     * GET /api/polymorphic/test-morph-to?comment_id=1
     */
    public function testMorphTo(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $commentId = (int) $request->get('comment_id', 1);

        $comment = CommentModel::with('commentable')->find($commentId);

        $results = [];
        if ($comment) {
            $results['comment'] = $comment->toArray();
            $results['commentable'] = $comment->commentable ? $comment->commentable->toArray() : null;
            $results['commentable_type'] = $comment->commentable_type;
            $results['commentable_class'] = $comment->commentable ? get_class($comment->commentable) : null;
        } else {
            $results['error'] = 'Comment not found';
        }

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'MorphTo relationship test',
            'data' => $results,
            'queries' => $queries,
        ]);
    }

    /**
     * Test MorphToMany relationship (Post/Video has many Tags).
     *
     * GET /api/polymorphic/test-morph-to-many?type=post&id=1
     * GET /api/polymorphic/test-morph-to-many?type=video&id=1
     *
     * Parameters:
     * - type: 'post' or 'video' (required)
     * - id: ID of post or video (required, must exist in database)
     *
     * Example with real data:
     * 1. First get available IDs: GET /api/polymorphic/available-ids
     * 2. Use a real post ID: GET /api/polymorphic/test-morph-to-many?type=post&id={real_post_id}
     * 3. Use a real video ID: GET /api/polymorphic/test-morph-to-many?type=video&id={real_video_id}
     *
     * Full example:
     * GET /api/polymorphic/test-morph-to-many?type=post&id=1
     * GET /api/polymorphic/test-morph-to-many?type=video&id=1
     */
    public function testMorphToMany(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $type = $request->get('type', 'post');
        $id = (int) $request->get('id', 1);

        $results = [];

        if ($type === 'post') {
            $post = PostModel::with('tags')->find($id);
            if ($post) {
                $results['post'] = $post->toArray();
                $results['tags'] = $post->tags->map(function ($tag) {
                    $data = $tag->toArray();
                    if ($tag->pivot) {
                        $data['pivot'] = $tag->pivot->toArray();
                    }
                    return $data;
                })->toArray();
                $results['tags_count'] = $post->tags->count();
            } else {
                $results['error'] = 'Post not found';
            }
        } elseif ($type === 'video') {
            $video = VideoModel::with('tags')->find($id);
            if ($video) {
                $results['video'] = $video->toArray();
                $results['tags'] = $video->tags->map(function ($tag) {
                    $data = $tag->toArray();
                    if ($tag->pivot) {
                        $data['pivot'] = $tag->pivot->toArray();
                    }
                    return $data;
                })->toArray();
                $results['tags_count'] = $video->tags->count();
            } else {
                $results['error'] = 'Video not found';
            }
        }

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'MorphToMany relationship test',
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
     * Test all polymorphic relationships together.
     *
     * GET /api/polymorphic/test-all?post_id=1&video_id=1&comment_id=1
     *
     * Parameters (all optional, but recommended to use real IDs):
     * - post_id: ID of post (optional, must exist in database if provided)
     * - video_id: ID of video (optional, must exist in database if provided)
     * - comment_id: ID of comment (optional, must exist in database if provided)
     *
     * Example with real data:
     * 1. First get available IDs: GET /api/polymorphic/available-ids
     * 2. Use real IDs:
     * GET /api/polymorphic/test-all?post_id={real_post_id}&video_id={real_video_id}&comment_id={real_comment_id}
     *
     * Full example:
     * GET /api/polymorphic/test-all?post_id=1&video_id=1&comment_id=1
     */
    public function testAllPolymorphic(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $postId = (int) $request->get('post_id', 1);
        $videoId = (int) $request->get('video_id', 1);
        $commentId = (int) $request->get('comment_id', 1);

        $results = [];

        // Test MorphOne - Post has one Image
        $post = PostModel::with('image')->find($postId);
        if ($post) {
            $results['post_with_image'] = [
                'post' => $post->toArray(),
                'image' => $post->image ? $post->image->toArray() : null,
            ];
        }

        // Test MorphOne - Video has one Image
        $video = VideoModel::with('image')->find($videoId);
        if ($video) {
            $results['video_with_image'] = [
                'video' => $video->toArray(),
                'image' => $video->image ? $video->image->toArray() : null,
            ];
        }

        // Test MorphMany - Post has many Comments
        if ($post) {
            $results['post_with_comments'] = [
                'post' => $post->toArray(),
                'comments' => $post->comments->toArray(),
                'comments_count' => $post->comments->count(),
            ];
        }

        // Test MorphMany - Video has many Comments
        if ($video) {
            $results['video_with_comments'] = [
                'video' => $video->toArray(),
                'comments' => $video->comments->toArray(),
                'comments_count' => $video->comments->count(),
            ];
        }

        // Test MorphTo - Comment belongs to Post/Video
        $comment = CommentModel::with('commentable')->find($commentId);
        if ($comment) {
            $results['comment_with_commentable'] = [
                'comment' => $comment->toArray(),
                'commentable' => $comment->commentable ? $comment->commentable->toArray() : null,
                'commentable_type' => $comment->commentable_type,
            ];
        }

        // Test MorphToMany - Post has many Tags
        if ($post) {
            $post->load('tags');
            $results['post_with_tags'] = [
                'post' => $post->toArray(),
                'tags' => $post->tags->map(function ($tag) {
                    $data = $tag->toArray();
                    if ($tag->pivot) {
                        $data['pivot'] = $tag->pivot->toArray();
                    }
                    return $data;
                })->toArray(),
                'tags_count' => $post->tags->count(),
            ];
        }

        // Test MorphToMany - Video has many Tags
        if ($video) {
            $video->load('tags');
            $results['video_with_tags'] = [
                'video' => $video->toArray(),
                'tags' => $video->tags->map(function ($tag) {
                    $data = $tag->toArray();
                    if ($tag->pivot) {
                        $data['pivot'] = $tag->pivot->toArray();
                    }
                    return $data;
                })->toArray(),
                'tags_count' => $video->tags->count(),
            ];
        }

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'All polymorphic relationships test',
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
     * Get sample data for testing polymorphic relationships.
     *
     * GET /api/polymorphic/sample-data
     */
    public function getSampleData(Request $request): JsonResponseInterface
    {
        DB::enableQueryLog();

        $results = [
            'posts' => PostModel::with(['image', 'comments', 'tags'])->limit(5)->get()->toArray(),
            'videos' => VideoModel::with(['image', 'comments', 'tags'])->limit(5)->get()->toArray(),
            'comments' => CommentModel::with('commentable')->limit(10)->get()->toArray(),
            'images' => ImageModel::with('imageable')->limit(10)->get()->toArray(),
        ];

        $queries = $this->formatQueryLogs(DB::getQueryLog());

        return response()->json([
            'success' => true,
            'message' => 'Sample data for polymorphic relationships',
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
