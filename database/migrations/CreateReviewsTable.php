<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create reviews table migration.
 */
class CreateReviewsTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('reviews', function ($table) {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('user_id')->nullable();
            $table->string('name', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->integer('rating')->unsigned();
            $table->string('title', 255)->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->integer('helpful_count')->unsigned()->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys (commented out - add after ensuring data types match)
            // $table->foreign('product_id')
            //       ->references('products', 'id')
            //       ->onDelete('cascade');

            // $table->foreign('user_id')
            //       ->references('users', 'id')
            //       ->onDelete('set null');

            // Indexes
            $table->index('product_id');
            $table->index('user_id');
            $table->index('rating');
            $table->index('is_approved');
            $table->index(['product_id', 'is_approved']);
            $table->index('created_at');
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('reviews');
    }
}
