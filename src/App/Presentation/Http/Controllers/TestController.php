<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Presentation\Http\Controllers\BaseController;
use Toporia\Framework\Http\Request;

final class TestController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): void
    {
        $this->json([
            'message' => 'Index method',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $id): void
    {
        $this->json([
            'message' => 'Show method',
            'id' => $id,
        ]);
    }

    /**
     * Create a new product.
     *
     * This method demonstrates creating a product and triggering the observer.
     * The ProductObserver::created() method will log the creation event.
     */
    public function createProduct(Request $request): void
    {
        try {
            // Lấy dữ liệu từ request
            $data = $request->all();

            // Tạo sản phẩm mới
            $product = \App\Domain\Product::create([
                'title' => $data['name'] ?? 'Test Product',
                'description' => $data['description'] ?? 'Test Description',
                'price' => $data['price'] ?? 100.00,
                'stock' => $data['stock'] ?? 10,
                'is_active' => $data['status'] ?? 1,
            ]);

            // Trả về response
            $this->json([
                'success' => true,
                'message' => 'Product created successfully',
                'product' => [
                    'id' => $product->getKey(),
                    'title' => $product->title,
                    'description' => $product->description,
                    'price' => $product->price,
                    'stock' => $product->stock,
                    'is_active' => $product->is_active,
                    'created_at' => $product->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
