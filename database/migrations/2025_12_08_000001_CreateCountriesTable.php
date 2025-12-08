<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create countries table migration.
 */
class CreateCountriesTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('countries', function ($table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 2)->unique();
            $table->string('continent', 50);
            $table->integer('population')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'continent']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('countries');
    }
}

