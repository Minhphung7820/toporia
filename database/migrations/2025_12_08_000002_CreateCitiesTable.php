<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create cities table migration.
 */
class CreateCitiesTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('cities', function ($table) {
            $table->id();
            $table->unsignedBigInteger('country_id');
            $table->string('name', 100);
            $table->integer('population')->default(0);
            $table->boolean('is_capital')->default(false);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->foreign('country_id')->references('countries', 'id')->onDelete('cascade');
            $table->index(['country_id', 'is_capital']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('cities');
    }
}

