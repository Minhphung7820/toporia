<?php

declare(strict_types=1);

namespace App\Presentation\Http\Actions\Product;

use App\Application\UseCases\Product\DeleteProduct\DeleteProductCommand;
use App\Application\UseCases\Product\DeleteProduct\DeleteProductHandler;
use Toporia\Framework\Presentation\Action\AbstractAction;
use Toporia\Framework\Http\Contracts\RequestInterface;
use Toporia\Framework\Http\Contracts\ResponseInterface;
use InvalidArgumentException;

/**
 * Delete Product Action
 *
 * ADR pattern for deleting a product.
 *
 * Clean Architecture:
 * - Presentation layer
 * - Delegates to Application layer
 *
 * SOLID Principles:
 * - Single Responsibility: Handle product deletion HTTP request
 */
final class DeleteProductAction extends AbstractAction
{
    /**
     * @param DeleteProductHandler $handler Delete product handler (auto-wired)
     */
    public function __construct(
        private readonly DeleteProductHandler $handler
    ) {}

    /**
     * Handle product deletion request.
     *
     * @param RequestInterface $request HTTP request
     * @param ResponseInterface $response HTTP response
     * @param int $id Product ID from route
     * @return mixed Response
     */
    protected function handle(RequestInterface $request, ResponseInterface $response, int $id): mixed
    {
        try {
            // Create command
            $command = new DeleteProductCommand(id: $id);

            // Execute use case
            ($this->handler)($command);

            // Return success response
            return $response->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);

        } catch (InvalidArgumentException $e) {
            return $response->json(['error' => $e->getMessage()], 404);

        } catch (\Throwable $e) {
            return $response->json([
                'error' => 'An error occurred while deleting the product'
            ], 500);
        }
    }
}
