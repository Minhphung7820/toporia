<?php

declare(strict_types=1);

namespace App\Infrastructure\Transformer;

use App\Domain\Product\Product;
use App\Infrastructure\Transformer\Resource;

/**
 * Product Transformer
 *
 * Transforms Product domain entities to API resources.
 *
 * Clean Architecture:
 * - Infrastructure layer implementation
 * - Transforms Domain entities to Presentation resources
 *
 * SOLID Principles:
 * - Single Responsibility: Transforms Product entities only
 * - Open/Closed: Extensible via inheritance
 *
 * Performance:
 * - Cached transformations
 * - Efficient data mapping
 */
final class ProductTransformer extends BaseTransformer
{
    /**
     * {@inheritdoc}
     */
    public function getEntityClass(): string
    {
        return Product::class;
    }

    /**
     * {@inheritdoc}
     */
    protected function transformEntity(mixed $entity, array $context = []): Resource
    {
        /** @var Product $entity */
        $data = [
            'id' => $entity->id,
            'title' => $entity->title,
            'sku' => $entity->sku,
            'description' => $entity->description,
            'price' => $this->formatPrice($entity->price),
            'stock' => $entity->stock,
            'is_active' => $entity->isActive,
            'status' => $entity->status,
        ];

        // Add additional fields based on context
        if (isset($context['include']) && in_array('formatted_price', $context['include'], true)) {
            $data['formatted_price'] = $this->formatCurrency($entity->price);
        }

        if (isset($context['include']) && in_array('availability', $context['include'], true)) {
            $data['availability'] = $this->getAvailability($entity);
        }

        // Hide sensitive data if needed
        if (isset($context['hide']) && in_array('price', $context['hide'], true)) {
            unset($data['price'], $data['formatted_price']);
        }

        return Resource::make($data);
    }

    /**
     * Format price for display.
     *
     * @param float $price Price value
     * @return float Formatted price
     */
    private function formatPrice(float $price): float
    {
        return round($price, 2);
    }

    /**
     * Format price as currency string.
     *
     * @param float $price Price value
     * @return string Formatted currency
     */
    private function formatCurrency(float $price): string
    {
        return number_format($price, 2, '.', ',') . ' VND';
    }

    /**
     * Get product availability status.
     *
     * @param Product $product Product entity
     * @return string Availability status
     */
    private function getAvailability(Product $product): string
    {
        if (!$product->isActive) {
            return 'inactive';
        }

        if ($product->stock <= 0) {
            return 'out_of_stock';
        }

        if ($product->stock < 10) {
            return 'low_stock';
        }

        return 'in_stock';
    }
}
