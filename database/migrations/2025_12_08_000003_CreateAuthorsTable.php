<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create authors table migration.
 */
class CreateAuthorsTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('authors', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('city_id');
            $table->string('pen_name', 100);
            $table->string('bio', 500)->nullable();
            $table->integer('books_count')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('users', 'id')->onDelete('set null');
            $table->foreign('city_id')->references('cities', 'id')->onDelete('cascade');
            $table->index(['city_id', 'is_verified']);
            $table->index(['rating', 'books_count']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('authors');
    }
}

