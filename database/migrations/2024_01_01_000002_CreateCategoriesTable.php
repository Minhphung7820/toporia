<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create categories table migration.
 */
class CreateCategoriesTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('categories', function ($table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('slug');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('categories');
    }
}
