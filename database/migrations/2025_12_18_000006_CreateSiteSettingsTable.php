<?php

declare(strict_types=1);

use Toporia\Framework\Database\Migration\Migration;
use Toporia\Framework\Support\Accessors\QueryBuilder;

/**
 * Create site_settings table for dynamic configuration.
 */
class CreateSiteSettingsTable extends Migration
{
    public function up(): void
    {
        $this->schema->create('site_settings', function ($table) {
            $table->id();

            // Setting identification
            $table->string('key', 100)->unique();
            $table->string('group', 50)->default('general'); // general, blog, seo, social, mail, etc.

            // Value storage
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string, boolean, integer, float, json, array

            // Metadata
            $table->string('label', 100)->nullable(); // Human-readable label
            $table->string('description', 500)->nullable();

            // Access control
            $table->boolean('is_public')->default(false); // Expose to frontend?
            $table->boolean('is_readonly')->default(false); // Prevent modification?

            $table->timestamps();

            // Indexes
            $table->index('group', 'idx_settings_group');
            $table->index('is_public', 'idx_settings_public');
        });

        // Insert default settings
        $this->seedDefaultSettings();
    }

    public function down(): void
    {
        $this->schema->dropIfExists('site_settings');
    }

    /**
     * Seed default site settings.
     */
    private function seedDefaultSettings(): void
    {
        $settings = [
            // General
            ['key' => 'site_name', 'group' => 'general', 'value' => 'Toporia Blog', 'type' => 'string', 'is_public' => true],
            ['key' => 'site_tagline', 'group' => 'general', 'value' => 'A modern PHP framework blog', 'type' => 'string', 'is_public' => true],
            ['key' => 'site_logo', 'group' => 'general', 'value' => null, 'type' => 'string', 'is_public' => true],
            ['key' => 'site_favicon', 'group' => 'general', 'value' => null, 'type' => 'string', 'is_public' => true],

            // Blog
            ['key' => 'posts_per_page', 'group' => 'blog', 'value' => '10', 'type' => 'integer', 'is_public' => true],
            ['key' => 'featured_posts_count', 'group' => 'blog', 'value' => '5', 'type' => 'integer', 'is_public' => true],
            ['key' => 'popular_posts_count', 'group' => 'blog', 'value' => '5', 'type' => 'integer', 'is_public' => true],
            ['key' => 'related_posts_count', 'group' => 'blog', 'value' => '4', 'type' => 'integer', 'is_public' => true],
            ['key' => 'comments_enabled', 'group' => 'blog', 'value' => '1', 'type' => 'boolean', 'is_public' => true],
            ['key' => 'comments_require_approval', 'group' => 'blog', 'value' => '1', 'type' => 'boolean', 'is_public' => false],
            ['key' => 'comments_max_depth', 'group' => 'blog', 'value' => '3', 'type' => 'integer', 'is_public' => true],

            // SEO
            ['key' => 'meta_title', 'group' => 'seo', 'value' => 'Toporia Blog - Modern PHP Framework', 'type' => 'string', 'is_public' => true],
            ['key' => 'meta_description', 'group' => 'seo', 'value' => 'Official blog for Toporia PHP framework', 'type' => 'string', 'is_public' => true],
            ['key' => 'meta_keywords', 'group' => 'seo', 'value' => '["php","framework","toporia","blog"]', 'type' => 'json', 'is_public' => true],

            // Social
            ['key' => 'social_github', 'group' => 'social', 'value' => 'https://github.com/Minhphung7820/toporia', 'type' => 'string', 'is_public' => true],
            ['key' => 'social_twitter', 'group' => 'social', 'value' => null, 'type' => 'string', 'is_public' => true],
            ['key' => 'social_facebook', 'group' => 'social', 'value' => null, 'type' => 'string', 'is_public' => true],
        ];

        $now = date('Y-m-d H:i:s');
        foreach ($settings as $setting) {
            $setting['created_at'] = $now;
            $setting['updated_at'] = $now;
            $setting['is_public'] = $setting['is_public'] ? 1 : 0;
            $setting['is_readonly'] = 0;

            QueryBuilder::table('site_settings')->insert($setting);
        }
    }
}
