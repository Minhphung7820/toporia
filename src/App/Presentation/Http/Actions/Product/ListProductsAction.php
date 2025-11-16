<?php

declare(strict_types=1);

namespace App\Presentation\Http\Actions\Product;

use App\Application\UseCases\Product\ListProducts\ListProductsQuery;
use App\Application\UseCases\Product\ListProducts\ListProductsHandler;
use Toporia\Framework\Presentation\Action\AbstractAction;
use Toporia\Framework\Http\Contracts\RequestInterface;
use Toporia\Framework\Http\Contracts\ResponseInterface;

/**
 * List Products Action
 *
 * ADR pattern for listing products with pagination.
 *
 * Clean Architecture:
 * - Presentation layer
 * - Delegates to Application layer (Query Handler)
 *
 * SOLID Principles:
 * - Single Responsibility: Handle product listing HTTP request
 */
final class ListProductsAction extends AbstractAction
{
    /**
     * @param ListProductsHandler $handler List products handler (auto-wired)
     */
    public function __construct(
        private readonly ListProductsHandler $handler
    ) {}

    /**
     * Handle product listing request.
     *
     * Query parameters:
     * - page: int (optional, default 1)
     * - per_page: int (optional, default 15)
     *
     * @param RequestInterface $request HTTP request
     * @param ResponseInterface $response HTTP response
     * @return mixed Response
     */
    protected function handle(RequestInterface $request, ResponseInterface $response): mixed
    {
        try {
            // Extract pagination parameters
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('per_page', 15)));

            // Create query
            $query = new ListProductsQuery(
                page: $page,
                perPage: $perPage
            );

            // Execute query
            $result = ($this->handler)($query);

            // Convert products to arrays
            $result['data'] = array_map(
                fn($product) => $product->toArray(),
                $result['data']
            );

            // Return success response
            return $response->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => [
                    'total' => $result['total'],
                    'page' => $result['page'],
                    'per_page' => $result['per_page'],
                    'last_page' => $result['last_page'],
                ]
            ]);

        } catch (\Throwable $e) {
            return $response->json([
                'error' => 'An error occurred while listing products'
            ], 500);
        }
    }
}
