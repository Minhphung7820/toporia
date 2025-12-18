<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;

/**
 * Add priority column to feedback table.
 */
class AddPriorityToFeedbackTable extends Migration
{
    public function up(): void
    {
        $this->schema->table('feedback', function ($table) {
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])
                ->default('normal')
                ->after('status');

            $table->index('priority', 'idx_feedback_priority');
        });
    }

    public function down(): void
    {
        $this->schema->table('feedback', function ($table) {
            $table->dropIndex('idx_feedback_priority');
            $table->dropColumn('priority');
        });
    }
}
