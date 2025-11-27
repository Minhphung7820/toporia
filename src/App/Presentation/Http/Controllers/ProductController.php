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
use Toporia\Framework\Http\Request;

/**
 * Product API Controller
 *
 * Test controller for querying products, categories, reviews, and orders.
 */
final class ProductController extends BaseController
{
    /**
     * Demo method to test different HTTP methods.
     *
     * GET /api/products/test-methods
     * POST /api/products/test-methods
     * PUT /api/products/test-methods
     * PATCH /api/products/test-methods
     * DELETE /api/products/test-methods
     */
    public function testMethods(Request $request): void
    {
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

        $this->json([
            'success' => true,
            'message' => 'HTTP Methods Test',
            'data' => $response,
        ]);
    }

    /**
     * Get products with pagination and filters.
     *
     * GET /api/products?page=1&per_page=20&category_id=1&min_price=100&max_price=500&search=laptop
     */
    public function index(Request $request)
    {
        // Test enterprise Response system with different response types
        $singleProduct = ProductModel::whereDoesntHave('categories')->first();
        return $this->json([
            'success' => true,
            'data' => $singleProduct,
        ]);
    }

    /**
     * Get single product with relationships.
     *
     * GET /api/products/{id}?with=category,reviews,categories
     */
    public function show(Request $request, int $id): void
    {
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

        if (!$product) {
            $this->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
            return;
        }

        $this->json([
            'success' => true,
            'data' => $product->toArray(),
        ]);
    }

    /**
     * Get products with complex queries.
     *
     * GET /api/products/complex?test=aggregates|chunking|eager|subquery|cte
     */
    public function complex(Request $request): void
    {
        $test = $request->input('test', 'aggregates');

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

                $this->json([
                    'success' => true,
                    'test' => 'aggregates',
                    'data' => $stats,
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

                $this->json([
                    'success' => true,
                    'test' => 'chunking',
                    'data' => [
                        'chunked_count' => $count,
                    ],
                ]);
                break;

            case 'eager':
                // Test eager loading
                $products = ProductModel::with([
                    'category',
                    'categories',
                    'reviews' => function ($q) {
                        $q->where('is_approved', true)
                            ->orderBy('rating', 'DESC')
                            ->limit(5);
                    },
                ])
                    ->withCount('reviews')
                    ->withSum('reviews', 'rating')
                    ->limit(10)
                    ->get();

                $data = [];
                foreach ($products as $product) {
                    $data[] = $product->toArray();
                }

                $this->json([
                    'success' => true,
                    'test' => 'eager_loading',
                    'data' => $data,
                ]);
                break;

            case 'subquery':
                // Test subqueries
                $products = ProductModel::query()
                    ->whereIn('id', function ($q) {
                        $q->select('product_id')
                            ->from('reviews')
                            ->where('rating', '>=', 4)
                            ->groupBy('product_id')
                            ->havingRaw('COUNT(*) >= 5');
                    })
                    ->with('category')
                    ->get();

                $data = [];
                foreach ($products as $product) {
                    $data[] = $product->toArray();
                }

                $this->json([
                    'success' => true,
                    'test' => 'subquery',
                    'data' => $data,
                ]);
                break;

            case 'cte':
                // Test CTE (Common Table Expression)
                // Note: Use withCte() for CTE, not with() which is for eager loading
                $connection = ProductModel::newQuery()->getConnection();
                $rows = $connection->table('products')
                    ->withCte('top_rated', function ($q) {
                        $q->select('product_id')
                            ->selectRaw('AVG(rating) as avg_rating')
                            ->from('reviews')
                            ->where('is_approved', true)
                            ->groupBy('product_id')
                            ->havingRaw('AVG(rating) >= 4.5');
                    })
                    ->from('top_rated')
                    ->join('products', 'products.id', '=', 'top_rated.product_id')
                    ->orderBy('top_rated.avg_rating', 'DESC')
                    ->limit(10)
                    ->get();

                // Convert rows to array format
                $data = [];
                foreach ($rows as $row) {
                    $data[] = (array) $row;
                }

                $this->json([
                    'success' => true,
                    'test' => 'cte',
                    'data' => $data,
                ]);
                break;

            default:
                $this->json([
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
    public function categories(Request $request): void
    {
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

        $this->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get product statistics.
     *
     * GET /api/products/stats
     */
    public function stats(Request $request): void
    {
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

        $this->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Test performance with large dataset.
     *
     * GET /api/products/performance?limit=1000
     */
    public function performance(Request $request): void
    {
        $limit = (int) $request->input('limit', 100);

        $startTime = microtime(true);

        $products = ProductModel::with(['category', 'reviews'])
            ->where('is_active', true)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();

        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2); // milliseconds

        $this->json([
            'success' => true,
            'data' => [
                'count' => $products->count(),
                'execution_time_ms' => $executionTime,
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ],
            'products' => array_map(fn($p) => $p->toArray(), $products->all()),
        ]);
    }

    /**
     * Get top rated products.
     *
     * GET /api/products/top-rated?limit=10
     */
    public function topRated(Request $request): void
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

        $this->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get products with reviews.
     *
     * GET /api/products/{id}/reviews
     */
    public function reviews(Request $request, int $id): void
    {
        $product = ProductModel::find($id);

        if (!$product) {
            $this->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
            return;
        }

        $reviews = $product->reviews()
            ->where('is_approved', true)
            ->orderBy('rating', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->paginate(20, (int) $request->input('page', 1));

        $this->json([
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
        ]);
    }

    /**
     * Test complex BelongsToMany relationships with pivot data.
     *
     * GET /api/products/test-belongs-to-many
     */
    public function testBelongsToMany(Request $request): void
    {
        // Test various BelongsToMany features
        $results = [];

        // 1. Basic many-to-many with pivot columns
        $productsWithCategories = ProductModel::with(['categories' => function ($query) {
            $query->withPivot('created_at', 'updated_at', 'sort_order')
                ->withTimestamps()
                ->wherePivot('is_active', true)
                ->orderByPivot('sort_order', 'asc');
        }])
            ->limit(5)
            ->get();

        $results['basic_pivot'] = $productsWithCategories;

        // 2. Complex pivot constraints
        $productsWithFilteredCategories = ProductModel::with(['categories' => function ($query) {
            $query->withPivot('sort_order', 'is_featured', 'created_at')
                ->wherePivotBetween('sort_order', [1, 10])
                ->wherePivotDate('created_at', '>=', '2024-01-01')
                ->wherePivotNotNull('is_featured');
        }])
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

        $this->json([
            'success' => true,
            'message' => 'BelongsToMany relationship tests',
            'data' => $results
        ]);
    }

    /**
     * Test HasMany and HasOne relationships.
     *
     * GET /api/products/test-has-relationships
     */
    public function testHasRelationships(Request $request): void
    {
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

        $this->json([
            'success' => true,
            'message' => 'HasMany relationship tests',
            'data' => $results
        ]);
    }

    /**
     * Test BelongsTo relationships.
     *
     * GET /api/products/test-belongs-to
     */
    public function testBelongsTo(Request $request): void
    {
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

        $this->json([
            'success' => true,
            'message' => 'BelongsTo relationship tests',
            'data' => $results
        ]);
    }

    /**
     * Test complex queries with multiple relationships.
     *
     * GET /api/products/test-complex-queries
     */
    public function testComplexQueries(Request $request): void
    {
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

        $this->json([
            'success' => true,
            'message' => 'Complex query tests',
            'data' => $results
        ]);
    }

    /**
     * Test sync operations for BelongsToMany.
     *
     * POST /api/products/test-sync-operations
     */
    public function testSyncOperations(Request $request): void
    {
        $results = [];
        $productId = $request->input('product_id', 1);

        $product = ProductModel::find($productId);
        if (!$product) {
            $this->json(['error' => 'Product not found'], 404);
            return;
        }

        // 1. Basic sync
        $syncResult1 = $product->categories()->sync([1, 2, 3]);
        $results['basic_sync'] = $syncResult1;

        // 2. Sync with pivot data
        $syncResult2 = $product->categories()->sync([
            1 => ['sort_order' => 1, 'is_featured' => true],
            2 => ['sort_order' => 2, 'is_featured' => false],
            4 => ['sort_order' => 3, 'is_featured' => true],
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
            'sort_order' => 10,
            'is_featured' => false
        ]);
        $results['update_pivot'] = $updateResult;

        $this->json([
            'success' => true,
            'message' => 'Sync operations tests',
            'data' => $results
        ]);
    }

    /**
     * Test performance with large datasets.
     *
     * GET /api/products/test-performance
     */
    public function testPerformance(Request $request): void
    {
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

        $this->json([
            'success' => true,
            'message' => 'Performance tests',
            'data' => $results,
            'total_time' => microtime(true) - $startTime
        ]);
    }

    /**
     * Test pivot table validation and debugging.
     *
     * GET /api/products/test-pivot-validation
     */
    public function testPivotValidation(Request $request): void
    {
        $results = [];
        $productId = $request->input('product_id', 1);

        $product = ProductModel::find($productId);
        if (!$product) {
            $this->json(['error' => 'Product not found'], 404);
            return;
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

        $this->json([
            'success' => true,
            'message' => 'Pivot validation tests',
            'data' => $results
        ]);
    }
}
