<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Domain\Contracts\Repository\ProductRepository;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Product Controller
 *
 * Demonstrates transformer usage in controllers.
 *
 * Clean Architecture:
 * - Presentation layer (Controller)
 * - Uses Domain ProductRepository
 * - Uses Infrastructure transformers via helpers
 */
final class ProductController extends BaseController
{
    /**
     * Get product by ID.
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param ProductRepository $repository Product repository
     * @param int $id Product ID
     * @return void
     */
    public function show(Request $request, Response $response, ProductRepository $repository, int $id): void
    {
        $product = $repository->findProductById($id);

        if ($product === null) {
            $response->json(['error' => 'Product not found'], 404);
            return;
        }

        // Transform entity to resource
        $resource = resource($product, [
            'include' => ['formatted_price', 'availability']
        ]);

        $response->json($resource->toArray());
    }

    /**
     * List products.
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @param ProductRepository $repository Product repository
     * @return void
     */
    public function index(Request $request, Response $response, ProductRepository $repository): void
    {
        $products = $repository->findAll();

        // Transform collection to resource collection
        $collection = resource_collection($products, [
            'include' => ['formatted_price']
        ], [
            'count' => count($products),
            'timestamp' => time()
        ]);

        $response->json($collection->toArray());
    }
}
