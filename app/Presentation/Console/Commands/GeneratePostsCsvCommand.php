<?php

declare(strict_types=1);

namespace App\Presentation\Console\Commands;

use Toporia\Framework\Console\Command;

/**
 * Generate Posts CSV Command
 *
 * Generates a large CSV file with fake posts data for import testing.
 * Optimized for memory efficiency - uses streaming write.
 */
final class GeneratePostsCsvCommand extends Command
{
    protected string $signature = 'posts:generate-csv {count=1000000 : Number of posts to generate} {output=storage/app/posts.csv : Output file path}';

    protected string $description = 'Generate a CSV file with fake posts data for import';

    private array $titles = [
        'Getting Started with %s: A Complete Guide',
        'How to Master %s in 2025',
        'Top 10 %s Tips Every Developer Should Know',
        'Why %s is the Future of Development',
        'Understanding %s: Deep Dive Tutorial',
        'Best Practices for %s Development',
        '%s Performance Optimization Guide',
        'Building Scalable Applications with %s',
        'A Beginner\'s Guide to %s',
        'Advanced %s Techniques',
        '%s Security Best Practices',
        'Migrating to %s: Step by Step',
        '%s vs Competitors: A Comparison',
        'Real-World %s Use Cases',
        'Testing Strategies for %s Projects',
        '%s Architecture Patterns',
        'Debugging %s Applications',
        '%s Deployment Strategies',
        'Optimizing %s for Production',
        'Common %s Mistakes to Avoid',
    ];

    private array $topics = [
        'PHP', 'JavaScript', 'Python', 'Laravel', 'Vue.js', 'React',
        'Node.js', 'TypeScript', 'Docker', 'Kubernetes', 'AWS', 'MySQL',
        'PostgreSQL', 'Redis', 'GraphQL', 'REST API', 'Microservices',
        'CI/CD', 'DevOps', 'Machine Learning', 'Cloud Computing',
        'Web Security', 'Performance', 'Testing', 'Clean Architecture',
    ];

    private array $contentTemplates = [
        "In this comprehensive guide, we'll explore the fundamentals of %s and how it can transform your development workflow. Whether you're a beginner or an experienced developer, this article will provide valuable insights.\n\n## Introduction\n\nThe world of software development is constantly evolving, and staying up-to-date with the latest technologies is crucial. %s has emerged as one of the most important tools in modern development.\n\n## Key Concepts\n\nUnderstanding the core concepts is essential before diving deeper. Here are the fundamental aspects you need to know:\n\n1. **Basic Setup** - Getting your environment ready\n2. **Core Features** - Understanding the main functionality\n3. **Best Practices** - Following industry standards\n4. **Common Pitfalls** - Avoiding typical mistakes\n\n## Conclusion\n\nBy following the guidelines in this article, you'll be well on your way to mastering %s. Remember, practice makes perfect, and the best way to learn is by building real projects.",

        "Welcome to this tutorial on %s. Today we'll cover everything you need to know to get started and become productive quickly.\n\n## Prerequisites\n\nBefore we begin, make sure you have the following installed and configured on your system. This will ensure a smooth learning experience.\n\n## Getting Started\n\n%s is known for its developer-friendly approach and powerful features. Let's explore what makes it special:\n\n- Easy to learn and use\n- Great community support\n- Extensive documentation\n- Regular updates and improvements\n\n## Practical Examples\n\nThe best way to learn is through hands-on practice. We'll walk through several real-world examples that demonstrate key concepts.\n\n## Summary\n\nWe've covered a lot of ground in this tutorial about %s. Keep experimenting and building projects to solidify your knowledge.",

        "Are you looking to improve your %s skills? You've come to the right place. This article provides actionable tips and strategies.\n\n## Why %s Matters\n\nIn today's competitive landscape, proficiency in %s can set you apart from other developers. Companies are actively seeking professionals with these skills.\n\n## Tips for Success\n\n### Tip 1: Start with Fundamentals\nDon't rush into advanced topics. Build a solid foundation first.\n\n### Tip 2: Practice Daily\nConsistency is key. Even 30 minutes of daily practice will compound over time.\n\n### Tip 3: Build Projects\nTheory alone isn't enough. Apply what you learn in real projects.\n\n### Tip 4: Join the Community\nEngage with other developers. Share knowledge and learn from others.\n\n## Resources\n\nHere are some excellent resources for continuing your learning journey with %s.",
    ];

    public function handle(): int
    {
        $count = (int) $this->argument('count');
        $outputPath = $this->argument('output');

        // Ensure directory exists
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->info("Generating {$count} posts to {$outputPath}...");
        $this->line('');

        // Open file for writing
        $handle = fopen($outputPath, 'w');
        if ($handle === false) {
            $this->error("Cannot open file: {$outputPath}");
            return 1;
        }

        // Write CSV header
        fputcsv($handle, [
            'title', 'slug', 'content', 'excerpt', 'author_id', 'category_id',
            'is_published', 'is_featured', 'views', 'reading_time', 'published_at'
        ], ',', '"', '\\');

        $authorIds = range(1, 10);    // 10 authors
        $categoryIds = range(1, 15);  // 15 categories
        $startTime = microtime(true);
        $lastProgressTime = $startTime;
        $batchSize = 10000;

        for ($i = 1; $i <= $count; $i++) {
            $topic = $this->topics[array_rand($this->topics)];
            $titleTemplate = $this->titles[array_rand($this->titles)];
            $title = sprintf($titleTemplate, $topic);
            $slug = $this->generateSlug($title, $i);

            $contentTemplate = $this->contentTemplates[array_rand($this->contentTemplates)];
            // Count placeholders and provide enough arguments
            $placeholderCount = substr_count($contentTemplate, '%s');
            $args = array_fill(0, $placeholderCount, $topic);
            $content = sprintf($contentTemplate, ...$args);
            $excerpt = substr(strip_tags($content), 0, 200) . '...';

            $authorId = $authorIds[array_rand($authorIds)];
            $categoryId = $categoryIds[array_rand($categoryIds)];

            $isPublished = rand(1, 100) <= 90 ? 1 : 0; // 90% published
            $isFeatured = rand(1, 100) <= 5 ? 1 : 0;   // 5% featured
            $views = rand(0, 50000);
            $readingTime = rand(2, 15);

            // Random published date within last 2 years
            $publishedAt = $isPublished
                ? date('Y-m-d H:i:s', strtotime('-' . rand(0, 730) . ' days'))
                : null;

            fputcsv($handle, [
                $title,
                $slug,
                $content,
                $excerpt,
                $authorId,
                $categoryId,
                $isPublished,
                $isFeatured,
                $views,
                $readingTime,
                $publishedAt,
            ], ',', '"', '\\');

            // Show progress every batch
            if ($i % $batchSize === 0) {
                $currentTime = microtime(true);
                $elapsed = $currentTime - $startTime;
                $rate = $i / $elapsed;
                $remaining = ($count - $i) / $rate;
                $percent = round(($i / $count) * 100, 1);

                $this->line(sprintf(
                    "  Progress: %s / %s (%s%%) - %.0f rows/sec - ETA: %s",
                    number_format($i),
                    number_format($count),
                    $percent,
                    $rate,
                    $this->formatTime($remaining)
                ));
            }
        }

        fclose($handle);

        $totalTime = microtime(true) - $startTime;
        $fileSize = filesize($outputPath);

        $this->line('');
        $this->success(sprintf(
            "Generated %s posts in %s",
            number_format($count),
            $this->formatTime($totalTime)
        ));
        $this->line(sprintf("  File size: %s", $this->formatBytes($fileSize)));
        $this->line(sprintf("  Output: %s", realpath($outputPath)));

        return 0;
    }

    private function generateSlug(string $title, int $index): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug . '-' . $index;
    }

    private function formatTime(float $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%.1fs', $seconds);
        }

        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;

        if ($minutes < 60) {
            return sprintf('%dm %ds', $minutes, $secs);
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return sprintf('%dh %dm', $hours, $mins);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;

        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return sprintf('%.2f %s', $bytes, $units[$unit]);
    }
}
