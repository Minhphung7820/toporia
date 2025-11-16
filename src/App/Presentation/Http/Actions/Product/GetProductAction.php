<?php

declare(strict_types=1);

namespace App\Presentation\Http\Actions\Product;

use App\Application\UseCases\Product\GetProduct\GetProductQuery;
use App\Application\UseCases\Product\GetProduct\GetProductHandler;
use Toporia\Framework\Presentation\Action\AbstractAction;
use Toporia\Framework\Http\Contracts\RequestInterface;
use Toporia\Framework\Http\Contracts\ResponseInterface;
use InvalidArgumentException;

/**
 * Get Product Action
 *
 * ADR pattern for retrieving a single product.
 *
 * Clean Architecture:
 * - Presentation layer
 * - Delegates to Application layer (Query Handler)
 *
 * SOLID Principles:
 * - Single Responsibility: Handle get product HTTP request
 */
final class GetProductAction extends AbstractAction
{
    /**
     * @param GetProductHandler $handler Get product handler (auto-wired)
     */
    public function __construct(
        private readonly GetProductHandler $handler
    ) {}

    /**
     * Handle get product request.
     *
     * @param RequestInterface $request HTTP request
     * @param ResponseInterface $response HTTP response
     * @param int $id Product ID from route
     * @return mixed Response
     */
    protected function handle(RequestInterface $request, ResponseInterface $response, int $id): mixed
    {
        try {
            // Create query
            $query = new GetProductQuery(id: $id);

            // Execute query
            $product = ($this->handler)($query);

            // Return success response
            return $response->json([
                'success' => true,
                'data' => $product->toArray()
            ]);

        } catch (InvalidArgumentException $e) {
            return $response->json(['error' => $e->getMessage()], 404);

        } catch (\Throwable $e) {
            return $response->json([
                'error' => 'An error occurred while retrieving the product'
            ], 500);
        }
    }
}
