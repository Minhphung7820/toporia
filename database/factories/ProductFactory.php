<?php

declare(strict_types=1);

use Toporia\Framework\Database\Factories\Factory;
use App\Infrastructure\Persistence\Models\ProductModel;

/**
 * Product Factory
 *
 * Example factory demonstrating Toporia Faker Provider usage.
 *
 * Features demonstrated:
 * - numerify(): Generate numeric patterns
 * - lexify(): Generate letter patterns
 * - bothify(): Generate mixed patterns
 * - regexify(): Generate regex patterns
 * - Helper functions: fake_code(), fake_id()
 * - State modifiers for variants
 *
 * @see \Toporia\Framework\Database\Faker\ToportaFakerProvider
 */
class ProductFactory extends Factory
{
    /**
     * The model the factory creates.
     */
    protected string $model = ProductModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // SKU: 3 letters + 3 digits (e.g., 'SKU-abc-123')
            'sku' => $this->faker->bothify('SKU-???-###'),

            // Barcode: 12 digits (EAN-13 format)
            'barcode' => $this->faker->numerify('############'),

            // Serial Number: 3 uppercase letters + 7 digits (e.g., 'ABC1234567')
            'serial_number' => $this->faker->regexify('[A-Z]{3}[0-9]{7}'),

            // Product Code: 8 alphanumeric chars (e.g., 'PROD-a8x4k2m1')
            'product_code' => $this->faker->bothify('PROD-********'),

            // Batch Number: 6 digits prefixed with 'BATCH-'
            'batch_number' => $this->faker->numerify('BATCH-######'),

            // Internal ID: 8 digits (using helper function)
            'internal_id' => fake_id('numeric-8'),

            // Tracking Code: Custom format with helper
            'tracking_code' => fake_code('serial', '????-####-????'),

            // Regular fields
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'cost' => $this->faker->randomFloat(2, 5, 500),
            'stock' => $this->faker->numberBetween(0, 1000),
            'weight' => $this->faker->randomFloat(2, 0.1, 100),
            'is_active' => $this->faker->boolean(80), // 80% true
        ];
    }

    /**
     * Indicate that the product is premium.
     *
     * Premium products have:
     * - Special SKU prefix
     * - Higher price range
     * - Higher stock levels
     *
     * @return static
     */
    public function premium(): static
    {
        return $this->state([
            'sku' => $this->faker->bothify('PREM-???-###'),
            'price' => $this->faker->randomFloat(2, 500, 5000),
            'stock' => $this->faker->numberBetween(100, 500),
        ]);
    }

    /**
     * Indicate that the product is on sale (discounted).
     *
     * Discounted products have:
     * - Discount code
     * - Discount percentage
     * - Sale price
     *
     * @return static
     */
    public function discounted(): static
    {
        $discountPercent = $this->faker->numberBetween(10, 50);

        return $this->state(function (array $attributes) use ($discountPercent) {
            $originalPrice = $attributes['price'] ?? 100;
            $salePrice = $originalPrice * (1 - $discountPercent / 100);

            return [
                'discount_code' => fake_code('coupon'),
                'discount_percent' => $discountPercent,
                'sale_price' => round($salePrice, 2),
                'is_on_sale' => true,
            ];
        });
    }

    /**
     * Indicate that the product is out of stock.
     *
     * @return static
     */
    public function outOfStock(): static
    {
        return $this->state([
            'stock' => 0,
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the product is a new arrival.
     *
     * New arrivals have:
     * - Special SKU prefix 'NEW-'
     * - Recent created_at timestamp
     * - High stock levels
     *
     * @return static
     */
    public function newArrival(): static
    {
        return $this->state([
            'sku' => $this->faker->bothify('NEW-???-###'),
            'stock' => $this->faker->numberBetween(200, 1000),
            'is_new' => true,
        ]);
    }

    /**
     * Indicate that the product is digital (no physical stock).
     *
     * Digital products have:
     * - License key
     * - Download URL pattern
     * - Zero weight
     *
     * @return static
     */
    public function digital(): static
    {
        return $this->state([
            'sku' => $this->faker->bothify('DIG-???-###'),
            'license_key' => fake_code('license'),
            'download_code' => $this->faker->regexify('[A-Z0-9]{32}'),
            'weight' => 0,
            'is_digital' => true,
        ]);
    }

    /**
     * Indicate that the product has a voucher.
     *
     * @return static
     */
    public function withVoucher(): static
    {
        return $this->state([
            'voucher_code' => fake_code('voucher'),
            'voucher_value' => $this->faker->randomFloat(2, 5, 100),
        ]);
    }

    /**
     * Create a product with custom ID pattern.
     *
     * @param string $pattern Custom pattern for product code
     * @return static
     */
    public function withCustomCode(string $pattern): static
    {
        return $this->state([
            'product_code' => $this->faker->bothify($pattern),
        ]);
    }

    /**
     * Create a batch of products with sequential batch numbers.
     *
     * @param int $batchNumber Starting batch number
     * @return static
     */
    public function inBatch(int $batchNumber): static
    {
        return $this->state([
            'batch_number' => sprintf('BATCH-%06d', $batchNumber),
        ]);
    }
}
