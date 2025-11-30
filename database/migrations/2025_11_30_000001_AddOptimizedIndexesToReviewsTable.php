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
            //
            // Index order explanation:
            // 1. is_approved (first) - WHERE filter, reduces data scan significantly
            // 2. product_id (second) - GROUP BY column, already sorted for efficient grouping
            // 3. rating (third) - Used in AVG() calculation, covering index (no table lookup)
            //
            // Why this order (not rating before product_id)?
            // - MySQL uses leftmost prefix: index is sorted by (is_approved, then product_id, then rating)
            // - GROUP BY product_id benefits from having product_id sorted (2nd position)
            // - rating doesn't need to be sorted for AVG(), just needs to be in index (covering)
            // - If we put rating before product_id: (is_approved, rating, product_id)
            //   -> GROUP BY product_id would be slower (not sorted by product_id)
            //
            // Query order vs Index order:
            // - Query: WHERE is_approved, WHERE rating IS NOT NULL, GROUP BY product_id
            // - Index: (is_approved, product_id, rating) - optimized for GROUP BY
            // - MySQL optimizer handles WHERE order automatically
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
