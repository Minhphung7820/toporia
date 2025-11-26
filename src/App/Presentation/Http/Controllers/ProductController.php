<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Infrastructure\Persistence\Models\CategoryModel;
use App\Infrastructure\Persistence\Models\OrderModel;
use App\Infrastructure\Persistence\Models\ProductModel;
use App\Infrastructure\Persistence\Models\ReviewModel;
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
    public function index(Request $request): void
    {
        $query = ProductModel::query();

        // Filters
        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('in_stock')) {
            $query->where('stock', '>', 0);
        }

        if ($request->has('on_sale')) {
            $query->whereNotNull('sale_price')
                ->whereColumn('sale_price', '<', 'price');
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'DESC');
        $query->orderBy($sortBy, $sortOrder);

        // Eager loading
        $with = $request->input('with', '');
        if ($with) {
            $relations = explode(',', $with);
            $relations = array_map('trim', $relations); // Trim whitespace
            $query->with(...$relations);
        }

        // Pagination
        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);
        // dd($query->categories()->get());
        $paginator = $query->paginate($perPage, $page);

        $items = [];
        foreach ($paginator->items() as $item) {
            $items[] = $item->toArray();
        }

        $this->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Get single product with relationships.
     *
     * GET /api/products/{id}?with=category,reviews,categories
     */
    public function show(Request $request, int $id): void
    {
        $query = ProductModel::query()->where('id', $id);

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
                $connection = ProductModel::query()->getConnection();
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

        $products = ProductModel::query()
            ->with(['category', 'reviews'])
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

        $products = ProductModel::query()
            ->where('rating_count', '>', 0)
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
}
