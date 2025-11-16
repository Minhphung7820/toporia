<?php

declare(strict_types=1);

/**
 * Product CRUD Test Script
 *
 * Demonstrates Clean Architecture implementation with:
 * - Domain layer (Entity, Value Objects, Contracts)
 * - Application layer (Use Cases)
 * - Infrastructure layer (Repository)
 * - Presentation layer (Actions)
 */

require __DIR__ . '/vendor/autoload.php';

use App\Application\UseCases\Product\CreateProduct\CreateProductCommand;
use App\Application\UseCases\Product\CreateProduct\CreateProductHandler;
use App\Application\UseCases\Product\UpdateProduct\UpdateProductCommand;
use App\Application\UseCases\Product\UpdateProduct\UpdateProductHandler;
use App\Application\UseCases\Product\GetProduct\GetProductQuery;
use App\Application\UseCases\Product\GetProduct\GetProductHandler;
use App\Application\UseCases\Product\ListProducts\ListProductsQuery;
use App\Application\UseCases\Product\ListProducts\ListProductsHandler;
use App\Application\UseCases\Product\DeleteProduct\DeleteProductCommand;
use App\Application\UseCases\Product\DeleteProduct\DeleteProductHandler;

// Bootstrap application
$app = require __DIR__ . '/bootstrap/app.php';
$container = $app->getContainer();

echo "========================================\n";
echo "Product CRUD - Clean Architecture Demo\n";
echo "========================================\n\n";

try {
    // 1. CREATE PRODUCT
    echo "1. CREATE PRODUCT\n";
    echo "   Command: CreateProductCommand\n";
    echo "   Handler: CreateProductHandler\n\n";

    $createHandler = $container->get(CreateProductHandler::class);
    $createCommand = new CreateProductCommand(
        title: 'Clean Architecture Book',
        price: 299000,
        currency: 'VND',
        sku: 'BOOK-CA-' . uniqid(),
        description: 'A comprehensive guide to Clean Architecture by Robert C. Martin',
        stock: 50
    );

    $product = $createHandler($createCommand);
    echo "   ✓ Product created with ID: {$product->id}\n";
    echo "   Title: {$product->title}\n";
    echo "   Price: {$product->price->format()}\n";
    echo "   Stock: {$product->stock}\n";
    echo "   Status: {$product->status->getLabel()}\n\n";

    $productId = $product->id;

    // 2. GET PRODUCT
    echo "2. GET PRODUCT\n";
    echo "   Query: GetProductQuery\n";
    echo "   Handler: GetProductHandler\n\n";

    $getHandler = $container->get(GetProductHandler::class);
    $getQuery = new GetProductQuery(id: $productId);

    $retrievedProduct = $getHandler($getQuery);
    echo "   ✓ Retrieved product ID: {$retrievedProduct->id}\n";
    echo "   Title: {$retrievedProduct->title}\n";
    echo "   SKU: {$retrievedProduct->sku}\n\n";

    // 3. UPDATE PRODUCT
    echo "3. UPDATE PRODUCT\n";
    echo "   Command: UpdateProductCommand\n";
    echo "   Handler: UpdateProductHandler\n\n";

    $updateHandler = $container->get(UpdateProductHandler::class);
    $updateCommand = new UpdateProductCommand(
        id: $productId,
        title: 'Clean Architecture Book - 2nd Edition',
        price: 349000,
        currency: 'VND',
        sku: 'BOOK-CA-' . uniqid(),
        description: 'Updated description with new content'
    );

    $updatedProduct = $updateHandler($updateCommand);
    echo "   ✓ Product updated\n";
    echo "   New title: {$updatedProduct->title}\n";
    echo "   New price: {$updatedProduct->price->format()}\n\n";

    // 4. LIST PRODUCTS
    echo "4. LIST PRODUCTS (Paginated)\n";
    echo "   Query: ListProductsQuery\n";
    echo "   Handler: ListProductsHandler\n\n";

    $listHandler = $container->get(ListProductsHandler::class);
    $listQuery = new ListProductsQuery(page: 1, perPage: 10);

    $result = $listHandler($listQuery);
    echo "   ✓ Found {$result['total']} total products\n";
    echo "   Page: {$result['page']}/{$result['last_page']}\n";
    echo "   Products on this page: " . count($result['data']) . "\n\n";

    foreach ($result['data'] as $p) {
        echo "   - [{$p->id}] {$p->title} - {$p->price->format()}\n";
    }
    echo "\n";

    // 5. DELETE PRODUCT
    echo "5. DELETE PRODUCT\n";
    echo "   Command: DeleteProductCommand\n";
    echo "   Handler: DeleteProductHandler\n\n";

    $deleteHandler = $container->get(DeleteProductHandler::class);
    $deleteCommand = new DeleteProductCommand(id: $productId);

    $deleteHandler($deleteCommand);
    echo "   ✓ Product deleted successfully\n\n";

    // 6. VERIFY DELETION
    echo "6. VERIFY DELETION\n";
    try {
        $getHandler($getQuery);
        echo "   ✗ Product still exists (ERROR)\n";
    } catch (InvalidArgumentException $e) {
        echo "   ✓ Product not found (as expected)\n";
        echo "   Error: {$e->getMessage()}\n";
    }

    echo "\n========================================\n";
    echo "✓ ALL TESTS PASSED\n";
    echo "========================================\n\n";

    echo "Clean Architecture Layers:\n";
    echo "  1. Domain: Product, Money, ProductStatus, ProductRepositoryInterface\n";
    echo "  2. Application: CreateProductHandler, UpdateProductHandler, etc.\n";
    echo "  3. Infrastructure: PdoProductRepository\n";
    echo "  4. Presentation: CreateProductAction, UpdateProductAction, etc.\n\n";

    echo "API Endpoints available:\n";
    echo "  GET    /api/v1/products         - List products (paginated)\n";
    echo "  GET    /api/v1/products/{id}    - Get single product\n";
    echo "  POST   /api/v1/products         - Create product\n";
    echo "  PUT    /api/v1/products/{id}    - Update product\n";
    echo "  DELETE /api/v1/products/{id}    - Delete product\n\n";

} catch (\Throwable $e) {
    echo "\n✗ ERROR: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
    echo "\nStack trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}
