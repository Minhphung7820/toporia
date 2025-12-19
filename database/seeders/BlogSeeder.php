<?php

declare(strict_types=1);

namespace Database\Seeders;

use Toporia\Framework\Database\Seeder;
use App\Infrastructure\Persistence\Models\UserModel;
use App\Infrastructure\Persistence\Models\CategoryModel;
use App\Infrastructure\Persistence\Models\TagModel;

/**
 * BlogSeeder
 *
 * Seeds users, categories, and tags for the blog system.
 * Posts are imported separately via CSV for performance (1M+ records).
 */
final class BlogSeeder extends Seeder
{
    protected int $batchSize = 500;

    public function dependencies(): array
    {
        return [];
    }

    protected function seed(): void
    {
        $this->seedUsers();
        $this->seedCategories();
        $this->seedTags();
    }

    private function seedUsers(): void
    {
        $this->line('Creating users...');

        // Create admin user
        UserModel::create([
            'name' => 'Admin User',
            'email' => 'admin@toporia.com',
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'role' => 'admin',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'is_active' => true,
        ]);

        // Create test user
        UserModel::create([
            'name' => 'Test User',
            'email' => 'user@toporia.com',
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'role' => 'user',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'is_active' => true,
        ]);

        // Create additional authors for posts
        $authors = [
            ['name' => 'John Writer', 'email' => 'john@toporia.com'],
            ['name' => 'Sarah Editor', 'email' => 'sarah@toporia.com'],
            ['name' => 'Mike Blogger', 'email' => 'mike@toporia.com'],
            ['name' => 'Emma Author', 'email' => 'emma@toporia.com'],
            ['name' => 'David Content', 'email' => 'david@toporia.com'],
            ['name' => 'Lisa Tech', 'email' => 'lisa@toporia.com'],
            ['name' => 'James News', 'email' => 'james@toporia.com'],
            ['name' => 'Anna Review', 'email' => 'anna@toporia.com'],
        ];

        foreach ($authors as $author) {
            UserModel::create([
                'name' => $author['name'],
                'email' => $author['email'],
                'password' => password_hash('password', PASSWORD_BCRYPT),
                'role' => 'user',
                'email_verified_at' => date('Y-m-d H:i:s'),
                'is_active' => true,
            ]);
        }

        $this->line('  Created 10 users');
    }

    private function seedCategories(): void
    {
        $this->line('Creating categories...');

        $categories = [
            ['name' => 'Technology', 'slug' => 'technology', 'description' => 'Tech news, reviews, and tutorials'],
            ['name' => 'Programming', 'slug' => 'programming', 'description' => 'Coding tutorials and best practices'],
            ['name' => 'Web Development', 'slug' => 'web-development', 'description' => 'Frontend and backend development'],
            ['name' => 'Mobile', 'slug' => 'mobile', 'description' => 'Mobile app development and news'],
            ['name' => 'AI & Machine Learning', 'slug' => 'ai-ml', 'description' => 'Artificial intelligence and ML'],
            ['name' => 'DevOps', 'slug' => 'devops', 'description' => 'DevOps practices and tools'],
            ['name' => 'Database', 'slug' => 'database', 'description' => 'Database design and optimization'],
            ['name' => 'Security', 'slug' => 'security', 'description' => 'Cybersecurity and best practices'],
            ['name' => 'Cloud', 'slug' => 'cloud', 'description' => 'Cloud computing and services'],
            ['name' => 'Career', 'slug' => 'career', 'description' => 'Tech career advice and tips'],
            ['name' => 'News', 'slug' => 'news', 'description' => 'Latest tech industry news'],
            ['name' => 'Tutorials', 'slug' => 'tutorials', 'description' => 'Step-by-step guides'],
            ['name' => 'Reviews', 'slug' => 'reviews', 'description' => 'Product and tool reviews'],
            ['name' => 'Opinion', 'slug' => 'opinion', 'description' => 'Opinion pieces and analysis'],
            ['name' => 'Lifestyle', 'slug' => 'lifestyle', 'description' => 'Tech lifestyle and culture'],
        ];

        foreach ($categories as $index => $category) {
            CategoryModel::create([
                'name' => $category['name'],
                'slug' => $category['slug'],
                'description' => $category['description'],
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }

        $this->line('  Created ' . count($categories) . ' categories');
    }

    private function seedTags(): void
    {
        $this->line('Creating tags...');

        $tags = [
            ['name' => 'PHP', 'slug' => 'php', 'color' => '#777BB4'],
            ['name' => 'JavaScript', 'slug' => 'javascript', 'color' => '#F7DF1E'],
            ['name' => 'Python', 'slug' => 'python', 'color' => '#3776AB'],
            ['name' => 'Laravel', 'slug' => 'laravel', 'color' => '#FF2D20'],
            ['name' => 'Vue.js', 'slug' => 'vuejs', 'color' => '#4FC08D'],
            ['name' => 'React', 'slug' => 'react', 'color' => '#61DAFB'],
            ['name' => 'Node.js', 'slug' => 'nodejs', 'color' => '#339933'],
            ['name' => 'TypeScript', 'slug' => 'typescript', 'color' => '#3178C6'],
            ['name' => 'Docker', 'slug' => 'docker', 'color' => '#2496ED'],
            ['name' => 'Kubernetes', 'slug' => 'kubernetes', 'color' => '#326CE5'],
            ['name' => 'AWS', 'slug' => 'aws', 'color' => '#FF9900'],
            ['name' => 'MySQL', 'slug' => 'mysql', 'color' => '#4479A1'],
            ['name' => 'PostgreSQL', 'slug' => 'postgresql', 'color' => '#4169E1'],
            ['name' => 'Redis', 'slug' => 'redis', 'color' => '#DC382D'],
            ['name' => 'Git', 'slug' => 'git', 'color' => '#F05032'],
            ['name' => 'Linux', 'slug' => 'linux', 'color' => '#FCC624'],
            ['name' => 'API', 'slug' => 'api', 'color' => '#009688'],
            ['name' => 'REST', 'slug' => 'rest', 'color' => '#00BCD4'],
            ['name' => 'GraphQL', 'slug' => 'graphql', 'color' => '#E10098'],
            ['name' => 'Testing', 'slug' => 'testing', 'color' => '#8BC34A'],
            ['name' => 'CI/CD', 'slug' => 'ci-cd', 'color' => '#FF5722'],
            ['name' => 'Performance', 'slug' => 'performance', 'color' => '#E91E63'],
            ['name' => 'Security', 'slug' => 'security', 'color' => '#F44336'],
            ['name' => 'Best Practices', 'slug' => 'best-practices', 'color' => '#9C27B0'],
            ['name' => 'Tutorial', 'slug' => 'tutorial', 'color' => '#2196F3'],
            ['name' => 'Beginner', 'slug' => 'beginner', 'color' => '#4CAF50'],
            ['name' => 'Advanced', 'slug' => 'advanced', 'color' => '#FF5722'],
            ['name' => 'Tips', 'slug' => 'tips', 'color' => '#FFC107'],
            ['name' => 'How-to', 'slug' => 'how-to', 'color' => '#03A9F4'],
            ['name' => 'Open Source', 'slug' => 'open-source', 'color' => '#795548'],
        ];

        foreach ($tags as $tag) {
            TagModel::create([
                'name' => $tag['name'],
                'slug' => $tag['slug'],
                'color' => $tag['color'],
                'is_active' => true,
                'usage_count' => 0,
            ]);
        }

        $this->line('  Created ' . count($tags) . ' tags');
    }

    public function useTransaction(): bool
    {
        return true;
    }
}
