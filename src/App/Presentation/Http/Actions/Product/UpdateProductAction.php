<?php

declare(strict_types=1);

namespace App\Presentation\Http\Actions\Product;

use App\Application\UseCases\Product\UpdateProduct\UpdateProductCommand;
use App\Application\UseCases\Product\UpdateProduct\UpdateProductHandler;
use Toporia\Framework\Presentation\Action\AbstractAction;
use Toporia\Framework\Http\Contracts\RequestInterface;
use Toporia\Framework\Http\Contracts\ResponseInterface;
use InvalidArgumentException;

/**
 * Update Product Action
 *
 * ADR pattern for updating a product.
 *
 * Clean Architecture:
 * - Presentation layer
 * - Delegates to Application layer
 *
 * SOLID Principles:
 * - Single Responsibility: Handle product update HTTP request
 */
final class UpdateProductAction extends AbstractAction
{
    /**
     * @param UpdateProductHandler $handler Update product handler (auto-wired)
     */
    public function __construct(
        private readonly UpdateProductHandler $handler
    ) {}

    /**
     * Handle product update request.
     *
     * Expected PUT/PATCH data:
     * - title: string (required)
     * - price: float (required)
     * - currency: string (optional)
     * - sku: string (optional)
     * - description: string (optional)
     *
     * @param RequestInterface $request HTTP request
     * @param ResponseInterface $response HTTP response
     * @param int $id Product ID from route
     * @return mixed Response
     */
    protected function handle(RequestInterface $request, ResponseInterface $response, int $id): mixed
    {
        try {
            // Extract and validate input
            $title = $request->input('title');
            $price = $request->input('price');

            if (empty($title)) {
                return $response->json(['error' => 'Title is required'], 400);
            }

            if ($price === null || !is_numeric($price) || $price < 0) {
                return $response->json(['error' => 'Valid price is required'], 400);
            }

            // Create command
            $command = new UpdateProductCommand(
                id: $id,
                title: $title,
                price: (float) $price,
                currency: $request->input('currency', 'VND'),
                sku: $request->input('sku'),
                description: $request->input('description')
            );

            // Execute use case
            $product = ($this->handler)($command);

            // Return success response
            return $response->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product->toArray()
            ]);

        } catch (InvalidArgumentException $e) {
            return $response->json(['error' => $e->getMessage()], 400);

        } catch (\Throwable $e) {
            return $response->json([
                'error' => 'An error occurred while updating the product'
            ], 500);
        }
    }
}
