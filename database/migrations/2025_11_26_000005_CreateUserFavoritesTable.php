<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create user_favorites pivot table for many-to-many relationship.
 */
class CreateUserFavoritesTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('user_favorites', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('product_id');
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);

            $table->foreign('user_id')
                ->references('users', 'id')
                ->onDelete('cascade');
            $table->foreign('product_id')
                ->references('products', 'id')
                ->onDelete('cascade');
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('user_favorites');
    }
}
