<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Models\PostModel;
use App\Infrastructure\Persistence\Models\VideoModel;
use App\Infrastructure\Persistence\Models\CommentModel;
use App\Infrastructure\Persistence\Models\ImageModel;
use App\Infrastructure\Persistence\Models\TagModel;
use Toporia\Framework\Database\Seeder;

/**
 * Polymorphic Relationships Seeder
 *
 * Seeds data for testing polymorphic relationships:
 * - MorphOne: Post/Video → Image
 * - MorphMany: Post/Video → Comments
 * - MorphTo: Comment → Post/Video
 * - MorphToMany: Post/Video → Tags
 *
 * Usage:
 *   php console db:seed --class=PolymorphicRelationshipsSeeder
 *   php console db:seed --class=PolymorphicRelationshipsSeeder --posts=10 --videos=10 --comments=50
 */
final class PolymorphicRelationshipsSeeder extends Seeder
{
    /**
     * Get seeder dependencies.
     *
     * @return array<string>
     */
    public function dependencies(): array
    {
        return [
            // Tags should exist before seeding posts/videos
            // Assuming TagSeeder or similar exists
        ];
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    protected function seed(): void
    {
        $postsCount = $this->getCount('posts', 10);
        $videosCount = $this->getCount('videos', 10);
        $commentsPerPost = $this->getCount('comments_per_post', 5);
        $commentsPerVideo = $this->getCount('comments_per_video', 5);
        $tagsPerPost = $this->getCount('tags_per_post', 3);
        $tagsPerVideo = $this->getCount('tags_per_video', 3);

        $this->info("Seeding polymorphic relationships...");
        $this->info("Posts: {$postsCount}, Videos: {$videosCount}");

        // Seed posts with images and tags
        $this->seedPosts($postsCount, $tagsPerPost);

        // Seed videos with images and tags
        $this->seedVideos($videosCount, $tagsPerVideo);

        // Seed comments for posts
        $this->seedCommentsForPosts($commentsPerPost);

        // Seed comments for videos
        $this->seedCommentsForVideos($commentsPerVideo);

        $this->info("Polymorphic relationships seeded successfully!");
    }

    /**
     * Seed posts with images (MorphOne) and tags (MorphToMany).
     *
     * @param int $count Number of posts to create
     * @param int $tagsPerPost Number of tags per post
     * @return void
     */
    protected function seedPosts(int $count, int $tagsPerPost): void
    {
        $this->info("Seeding {$count} posts...");

        $posts = [];
        for ($i = 1; $i <= $count; $i++) {
            $post = PostModel::create([
                'title' => "Sample Post {$i}",
                'slug' => "sample-post-{$i}",
                'content' => "This is the content for post {$i}. " . str_repeat("Lorem ipsum dolor sit amet. ", 10),
                'views' => rand(10, 1000),
                'is_published' => rand(0, 1) === 1,
            ]);

            // Create image for post (MorphOne)
            $post->image()->create([
                'url' => "https://picsum.photos/800/600?random={$i}",
                'alt_text' => "Featured image for post {$i}",
                'width' => 800,
                'height' => 600,
                'size' => rand(50000, 500000), // 50KB to 500KB
            ]);

            $posts[] = $post;

            if ($i % 10 === 0) {
                $this->info("Created {$i} posts...");
            }
        }

        // Attach tags to posts (MorphToMany)
        $this->info("Attaching tags to posts...");
        $tagIds = TagModel::pluck('id')->all();
        if (!empty($tagIds)) {
            foreach ($posts as $post) {
                $count = min($tagsPerPost, count($tagIds));
                shuffle($tagIds);
                $selectedTags = array_slice($tagIds, 0, $count);
                if (!empty($selectedTags)) {
                    $post->tags()->attach($selectedTags);
                }
            }
        }

        $this->info("✓ Created {$count} posts with images and tags");
    }

    /**
     * Seed videos with images (MorphOne) and tags (MorphToMany).
     *
     * @param int $count Number of videos to create
     * @param int $tagsPerVideo Number of tags per video
     * @return void
     */
    protected function seedVideos(int $count, int $tagsPerVideo): void
    {
        $this->info("Seeding {$count} videos...");

        $videos = [];
        for ($i = 1; $i <= $count; $i++) {
            $video = VideoModel::create([
                'title' => "Sample Video {$i}",
                'slug' => "sample-video-{$i}",
                'description' => "This is the description for video {$i}. " . str_repeat("Lorem ipsum dolor sit amet. ", 10),
                'video_url' => "https://example.com/videos/video-{$i}.mp4",
                'duration' => rand(60, 7200), // 1 minute to 2 hours
                'views' => rand(50, 10000),
                'is_published' => rand(0, 1) === 1,
            ]);

            // Create thumbnail image for video (MorphOne)
            $video->image()->create([
                'url' => "https://picsum.photos/1280/720?random={$i}",
                'alt_text' => "Thumbnail for video {$i}",
                'width' => 1280,
                'height' => 720,
                'size' => rand(100000, 1000000), // 100KB to 1MB
            ]);

            $videos[] = $video;

            if ($i % 10 === 0) {
                $this->info("Created {$i} videos...");
            }
        }

        // Attach tags to videos (MorphToMany)
        $this->info("Attaching tags to videos...");
        $tagIds = TagModel::pluck('id')->all();
        if (!empty($tagIds)) {
            foreach ($videos as $video) {
                $count = min($tagsPerVideo, count($tagIds));
                shuffle($tagIds);
                $selectedTags = array_slice($tagIds, 0, $count);
                if (!empty($selectedTags)) {
                    $video->tags()->attach($selectedTags);
                }
            }
        }

        $this->info("✓ Created {$count} videos with thumbnails and tags");
    }

    /**
     * Seed comments for posts (MorphMany).
     *
     * @param int $commentsPerPost Number of comments per post
     * @return void
     */
    protected function seedCommentsForPosts(int $commentsPerPost): void
    {
        $this->info("Seeding comments for posts...");

        $posts = PostModel::all();
        $totalComments = 0;

        foreach ($posts as $post) {
            $commentCount = rand(2, $commentsPerPost);
            for ($i = 1; $i <= $commentCount; $i++) {
                CommentModel::create([
                    'commentable_type' => PostModel::class,
                    'commentable_id' => $post->id,
                    'content' => "Comment {$i} on post '{$post->title}'. " . str_repeat("Great post! ", 3),
                    'user_id' => null,
                    'is_approved' => rand(0, 1) === 1,
                ]);
                $totalComments++;
            }
        }

        $this->info("✓ Created {$totalComments} comments for posts");
    }

    /**
     * Seed comments for videos (MorphMany).
     *
     * @param int $commentsPerVideo Number of comments per video
     * @return void
     */
    protected function seedCommentsForVideos(int $commentsPerVideo): void
    {
        $this->info("Seeding comments for videos...");

        $videos = VideoModel::all();
        $totalComments = 0;

        foreach ($videos as $video) {
            $commentCount = rand(2, $commentsPerVideo);
            for ($i = 1; $i <= $commentCount; $i++) {
                CommentModel::create([
                    'commentable_type' => VideoModel::class,
                    'commentable_id' => $video->id,
                    'content' => "Comment {$i} on video '{$video->title}'. " . str_repeat("Awesome video! ", 3),
                    'user_id' => null,
                    'is_approved' => rand(0, 1) === 1,
                ]);
                $totalComments++;
            }
        }

        $this->info("✓ Created {$totalComments} comments for videos");
    }

    /**
     * Get count from option or use default.
     *
     * @param string $key Option key
     * @param int $default Default value
     * @return int
     */
    private function getCount(string $key, int $default = 0): int
    {
        $option = $this->getOption($key);
        if ($option !== null) {
            return (int) $option;
        }
        return $default;
    }

    /**
     * Whether to use transaction for this seeder.
     *
     * @return bool
     */
    public function useTransaction(): bool
    {
        return true;
    }
}

