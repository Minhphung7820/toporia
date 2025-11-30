<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Add Optimized Indexes to Reviews Table
 *
 * Performance optimization for CTE queries with GROUP BY and AVG(rating).
 * Composite index on (is_approved, product_id, rating) allows:
 * - Fast filtering by is_approved
 * - Efficient GROUP BY product_id
 * - Covering index for rating aggregation
 */
class AddOptimizedIndexesToReviewsTable extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->schema->table('reviews', function ($table) {
            // Composite index for CTE queries: is_approved + product_id + rating
            // This index covers the entire CTE query, making it a covering index
            // MySQL can use this index to:
            // 1. Filter is_approved = true (index scan)
            // 2. Group by product_id (index scan)
            // 3. Calculate AVG(rating) (index scan, no table lookup needed)
            $table->index(['is_approved', 'product_id', 'rating'], 'idx_reviews_approved_product_rating');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->schema->table('reviews', function ($table) {
            $table->dropIndex('idx_reviews_approved_product_rating');
        });
    }
}
