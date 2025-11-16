<?php

declare(strict_types=1);

namespace App\Presentation\Http\Actions\Product;

use App\Application\UseCases\Product\CreateProduct\CreateProductCommand;
use App\Application\UseCases\Product\CreateProduct\CreateProductHandler;
use Toporia\Framework\Presentation\Action\AbstractAction;
use Toporia\Framework\Http\Contracts\RequestInterface;
use Toporia\Framework\Http\Contracts\ResponseInterface;
use InvalidArgumentException;

/**
 * Create Product Action
 *
 * ADR (Action-Domain-Responder) pattern.
 * Handles HTTP request for creating a product.
 *
 * Clean Architecture:
 * - Presentation layer
 * - Delegates to Application layer (Use Case Handler)
 * - HTTP concerns only
 *
 * SOLID Principles:
 * - Single Responsibility: Handle product creation HTTP request
 * - Dependency Inversion: Depends on handler abstraction
 */
final class CreateProductAction extends AbstractAction
{
    /**
     * @param CreateProductHandler $handler Create product handler (auto-wired)
     */
    public function __construct(
        private readonly CreateProductHandler $handler
    ) {}

    /**
     * Handle product creation request.
     *
     * Expected POST data:
     * - title: string (required, min 3 chars)
     * - price: float (required, >= 0)
     * - currency: string (optional, default VND)
     * - sku: string (optional, unique)
     * - description: string (optional)
     * - stock: int (optional, >= 0)
     *
     * @param RequestInterface $request HTTP request
     * @param ResponseInterface $response HTTP response
     * @return mixed Response
     */
    protected function handle(RequestInterface $request, ResponseInterface $response): mixed
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
            $command = new CreateProductCommand(
                title: $title,
                price: (float) $price,
                currency: $request->input('currency', 'VND'),
                sku: $request->input('sku'),
                description: $request->input('description'),
                stock: (int) $request->input('stock', 0)
            );

            // Execute use case
            $product = ($this->handler)($command);

            // Return success response
            return $response->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product->toArray()
            ], 201);

        } catch (InvalidArgumentException $e) {
            // Business logic error
            return $response->json([
                'error' => $e->getMessage()
            ], 400);

        } catch (\Throwable $e) {
            // Unexpected error
            return $response->json([
                'error' => 'An error occurred while creating the product'
            ], 500);
        }
    }
}
