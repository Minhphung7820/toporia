<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Create publishers table migration.
 */
class CreatePublishersTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up(): void
    {
        $this->schema->create('publishers', function ($table) {
            $table->id();
            $table->unsignedBigInteger('country_id');
            $table->string('name', 150);
            $table->string('email', 100)->nullable();
            $table->string('website', 200)->nullable();
            $table->year('founded_year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('country_id')->references('countries', 'id')->onDelete('cascade');
            $table->index(['is_active', 'founded_year']);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function down(): void
    {
        $this->schema->dropIfExists('publishers');
    }
}

