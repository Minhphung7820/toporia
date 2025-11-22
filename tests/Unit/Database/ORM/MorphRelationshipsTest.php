<?php

declare(strict_types=1);

namespace Tests\Unit\Database\ORM;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Database\ORM\Relations\{MorphOne, MorphMany, MorphTo, MorphToMany};
use Toporia\Framework\Database\ORM\ModelCollection;
use Toporia\Framework\Database\ORM\ModelQueryBuilder;

/**
 * Test Morph Relationships
 *
 * Comprehensive tests for polymorphic relationships:
 * - MorphOne: one-to-one polymorphic
 * - MorphMany: one-to-many polymorphic
 * - MorphTo: inverse polymorphic
 * - MorphToMany: many-to-many polymorphic
 *
 * @author      Phungtruong7820 <minhphung485@gmail.com>
 * @copyright   Copyright (c) 2025 Toporia Framework
 * @license     MIT
 * @version     1.0.0
 */
class MorphRelationshipsTest extends DatabaseTestCase
{
    protected function createTables(): void
    {
        // Create posts table (morphable)
        $this->createTable('posts', "
            CREATE TABLE posts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                content TEXT,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        ");

        // Create videos table (morphable)
        $this->createTable('videos', "
            CREATE TABLE videos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                url VARCHAR(255),
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        ");

        // Create comments table (morphable - has morphable_type and morphable_id)
        $this->createTable('comments', "
            CREATE TABLE comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                morphable_type VARCHAR(255) NOT NULL,
                morphable_id INT NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        ");

        // Create images table (morphable - has imageable_type and imageable_id)
        $this->createTable('images', "
            CREATE TABLE images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                imageable_type VARCHAR(255) NOT NULL,
                imageable_id INT NOT NULL,
                url VARCHAR(255) NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        ");

        // Create tags table
        $this->createTable('tags', "
            CREATE TABLE tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        ");

        // Create taggables pivot table (polymorphic many-to-many)
        $this->createTable('taggables', "
            CREATE TABLE taggables (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tag_id INT NOT NULL,
                taggable_type VARCHAR(255) NOT NULL,
                taggable_id INT NOT NULL,
                created_at DATETIME NULL,
                FOREIGN KEY (tag_id) REFERENCES tags(id)
            )
        ");
    }

    protected function dropTables(): void
    {
        $this->dropTable('taggables');
        $this->dropTable('tags');
        $this->dropTable('images');
        $this->dropTable('comments');
        $this->dropTable('videos');
        $this->dropTable('posts');
    }

    // ============================================
    // MORPHONE TESTS
    // ============================================

    /**
     * Test morphOne relationship returns MorphOne relation instance
     */
    public function test_morph_one_returns_morph_one_relation(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $relation = $post->image();

        $this->assertInstanceOf(MorphOne::class, $relation);
    }

    /**
     * Test morphOne relationship returns null when no related record
     */
    public function test_morph_one_returns_null_when_no_related_record(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $image = $post->image()->getResults();

        $this->assertNull($image);
    }

    /**
     * Test morphOne relationship returns related model when exists
     */
    public function test_morph_one_returns_related_model_when_exists(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        // Create image for post
        $this->executeQuery(
            "INSERT INTO images (imageable_type, imageable_id, url) VALUES (?, ?, ?)",
            [PostMorphModel::class, $post->id, 'image.jpg']
        );

        $image = $post->image()->getResults();

        $this->assertInstanceOf(ImageMorphModel::class, $image);
        $this->assertEquals('image.jpg', $image->url);
        $this->assertEquals(PostMorphModel::class, $image->imageable_type);
        $this->assertEquals($post->id, $image->imageable_id);
    }

    /**
     * Test morphOne works with different morphable types
     */
    public function test_morph_one_works_with_different_morphable_types(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $video = new VideoMorphModel(['title' => 'Test Video', 'url' => 'video.mp4']);
        $video->save();

        // Create images for both
        $this->executeQuery(
            "INSERT INTO images (imageable_type, imageable_id, url) VALUES (?, ?, ?)",
            [PostMorphModel::class, $post->id, 'post-image.jpg']
        );
        $this->executeQuery(
            "INSERT INTO images (imageable_type, imageable_id, url) VALUES (?, ?, ?)",
            [VideoMorphModel::class, $video->id, 'video-image.jpg']
        );

        $postImage = $post->image()->getResults();
        $videoImage = $video->image()->getResults();

        $this->assertNotNull($postImage);
        $this->assertEquals('post-image.jpg', $postImage->url);

        $this->assertNotNull($videoImage);
        $this->assertEquals('video-image.jpg', $videoImage->url);
    }

    // ============================================
    // MORPHMANY TESTS
    // ============================================

    /**
     * Test morphMany relationship returns MorphMany relation instance
     */
    public function test_morph_many_returns_morph_many_relation(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $relation = $post->comments();

        $this->assertInstanceOf(MorphMany::class, $relation);
    }

    /**
     * Test morphMany relationship returns empty collection when no related records
     */
    public function test_morph_many_returns_empty_collection_when_no_comments(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $comments = $post->comments()->getResults();

        $this->assertInstanceOf(ModelCollection::class, $comments);
        $this->assertTrue($comments->isEmpty());
    }

    /**
     * Test morphMany relationship returns related models when exist
     */
    public function test_morph_many_returns_related_models_when_exist(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        // Create comments for post
        $this->executeQuery(
            "INSERT INTO comments (morphable_type, morphable_id, content) VALUES (?, ?, ?)",
            [PostMorphModel::class, $post->id, 'Comment 1']
        );
        $this->executeQuery(
            "INSERT INTO comments (morphable_type, morphable_id, content) VALUES (?, ?, ?)",
            [PostMorphModel::class, $post->id, 'Comment 2']
        );

        $comments = $post->comments()->getResults();

        $this->assertInstanceOf(ModelCollection::class, $comments);
        $this->assertCount(2, $comments);
    }

    /**
     * Test morphMany works with different morphable types
     */
    public function test_morph_many_works_with_different_morphable_types(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $video = new VideoMorphModel(['title' => 'Test Video', 'url' => 'video.mp4']);
        $video->save();

        // Create comments for both
        $this->executeQuery(
            "INSERT INTO comments (morphable_type, morphable_id, content) VALUES (?, ?, ?)",
            [PostMorphModel::class, $post->id, 'Post Comment']
        );
        $this->executeQuery(
            "INSERT INTO comments (morphable_type, morphable_id, content) VALUES (?, ?, ?)",
            [VideoMorphModel::class, $video->id, 'Video Comment']
        );

        $postComments = $post->comments()->getResults();
        $videoComments = $video->comments()->getResults();

        $this->assertCount(1, $postComments);
        $this->assertEquals('Post Comment', $postComments->first()->content);

        $this->assertCount(1, $videoComments);
        $this->assertEquals('Video Comment', $videoComments->first()->content);
    }

    // ============================================
    // MORPHTO TESTS
    // ============================================

    /**
     * Test morphTo relationship returns MorphTo relation instance
     */
    public function test_morph_to_returns_morph_to_relation(): void
    {
        $comment = new CommentMorphModel([
            'morphable_type' => PostMorphModel::class,
            'morphable_id' => 1,
            'content' => 'Test Comment'
        ]);

        $relation = $comment->morphable();

        $this->assertInstanceOf(MorphTo::class, $relation);
    }

    /**
     * Test morphTo relationship returns parent model when exists
     */
    public function test_morph_to_returns_parent_model_when_exists(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $comment = new CommentMorphModel([
            'morphable_type' => PostMorphModel::class,
            'morphable_id' => $post->id,
            'content' => 'Test Comment'
        ]);
        $comment->save();

        $morphable = $comment->morphable()->getResults();

        $this->assertInstanceOf(PostMorphModel::class, $morphable);
        $this->assertEquals($post->id, $morphable->id);
        $this->assertEquals('Test Post', $morphable->title);
    }

    /**
     * Test morphTo works with different parent types
     */
    public function test_morph_to_works_with_different_parent_types(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $video = new VideoMorphModel(['title' => 'Test Video', 'url' => 'video.mp4']);
        $video->save();

        // Create comments for both
        $comment1 = new CommentMorphModel([
            'morphable_type' => PostMorphModel::class,
            'morphable_id' => $post->id,
            'content' => 'Post Comment'
        ]);
        $comment1->save();

        $comment2 = new CommentMorphModel([
            'morphable_type' => VideoMorphModel::class,
            'morphable_id' => $video->id,
            'content' => 'Video Comment'
        ]);
        $comment2->save();

        $postCommentable = $comment1->morphable()->getResults();
        $videoCommentable = $comment2->morphable()->getResults();

        $this->assertInstanceOf(PostMorphModel::class, $postCommentable);
        $this->assertInstanceOf(VideoMorphModel::class, $videoCommentable);
    }

    /**
     * Test morphTo returns null when parent doesn't exist
     */
    public function test_morph_to_returns_null_when_parent_does_not_exist(): void
    {
        $comment = new CommentMorphModel([
            'morphable_type' => PostMorphModel::class,
            'morphable_id' => 999,
            'content' => 'Test Comment'
        ]);
        $comment->save();

        $morphable = $comment->morphable()->getResults();

        $this->assertNull($morphable);
    }

    // ============================================
    // MORPHTOMANY TESTS
    // ============================================

    /**
     * Test morphToMany relationship returns MorphToMany relation instance
     */
    public function test_morph_to_many_returns_morph_to_many_relation(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $relation = $post->tags();

        $this->assertInstanceOf(MorphToMany::class, $relation);
    }

    /**
     * Test morphToMany relationship returns empty collection when no related records
     */
    public function test_morph_to_many_returns_empty_collection_when_no_tags(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $tags = $post->tags()->getResults();

        $this->assertInstanceOf(ModelCollection::class, $tags);
        $this->assertTrue($tags->isEmpty());
    }

    /**
     * Test morphToMany attach adds related model to pivot table
     */
    public function test_morph_to_many_attach_adds_related_model(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $tag = new TagMorphModel(['name' => 'PHP']);
        $tag->save();

        // Attach tag to post
        $result = $post->tags()->attach($tag->id);

        $this->assertTrue($result);

        // Verify pivot table has record
        $this->assertTableHas('taggables', [
            'taggable_type' => PostMorphModel::class,
            'taggable_id' => $post->id,
            'tag_id' => $tag->id
        ]);
    }

    /**
     * Test morphToMany detach removes related model from pivot table
     */
    public function test_morph_to_many_detach_removes_related_model(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $tag = new TagMorphModel(['name' => 'PHP']);
        $tag->save();

        // Attach tag
        $post->tags()->attach($tag->id);
        $this->assertTableHas('taggables', [
            'taggable_type' => PostMorphModel::class,
            'taggable_id' => $post->id,
            'tag_id' => $tag->id
        ]);

        // Detach tag
        $deleted = $post->tags()->detach($tag->id);

        $this->assertEquals(1, $deleted);
        $this->assertTableMissing('taggables', [
            'taggable_type' => PostMorphModel::class,
            'taggable_id' => $post->id,
            'tag_id' => $tag->id
        ]);
    }

    /**
     * Test morphToMany sync replaces all relationships
     */
    public function test_morph_to_many_sync_replaces_all_relationships(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $tag1 = new TagMorphModel(['name' => 'PHP']);
        $tag1->save();

        $tag2 = new TagMorphModel(['name' => 'Laravel']);
        $tag2->save();

        $tag3 = new TagMorphModel(['name' => 'Vue']);
        $tag3->save();

        // Attach tag1 and tag2
        $post->tags()->attach($tag1->id);
        $post->tags()->attach($tag2->id);

        $this->assertTableCount('taggables', 2, [
            'taggable_type' => PostMorphModel::class,
            'taggable_id' => $post->id
        ]);

        // Sync with tag2 and tag3
        $post->tags()->sync([$tag2->id, $tag3->id]);

        // Should have tag2 and tag3 only
        $this->assertTableCount('taggables', 2, [
            'taggable_type' => PostMorphModel::class,
            'taggable_id' => $post->id
        ]);
        $this->assertTableHas('taggables', [
            'taggable_type' => PostMorphModel::class,
            'taggable_id' => $post->id,
            'tag_id' => $tag2->id
        ]);
        $this->assertTableHas('taggables', [
            'taggable_type' => PostMorphModel::class,
            'taggable_id' => $post->id,
            'tag_id' => $tag3->id
        ]);
    }

    /**
     * Test morphToMany works with different morphable types
     */
    public function test_morph_to_many_works_with_different_morphable_types(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $video = new VideoMorphModel(['title' => 'Test Video', 'url' => 'video.mp4']);
        $video->save();

        $tag = new TagMorphModel(['name' => 'PHP']);
        $tag->save();

        // Attach tag to both post and video
        $post->tags()->attach($tag->id);
        $video->tags()->attach($tag->id);

        // Both should have the tag
        $this->assertTableHas('taggables', [
            'taggable_type' => PostMorphModel::class,
            'taggable_id' => $post->id,
            'tag_id' => $tag->id
        ]);
        $this->assertTableHas('taggables', [
            'taggable_type' => VideoMorphModel::class,
            'taggable_id' => $video->id,
            'tag_id' => $tag->id
        ]);
    }

    /**
     * Test morphToMany returns related models when exist
     */
    public function test_morph_to_many_returns_related_models_when_exist(): void
    {
        $post = new PostMorphModel(['title' => 'Test Post', 'content' => 'Content']);
        $post->save();

        $tag1 = new TagMorphModel(['name' => 'PHP']);
        $tag1->save();

        $tag2 = new TagMorphModel(['name' => 'Laravel']);
        $tag2->save();

        // Attach tags
        $post->tags()->attach($tag1->id);
        $post->tags()->attach($tag2->id);

        // Get tags via relationship
        $tags = $post->tags()->getResults();

        $this->assertInstanceOf(ModelCollection::class, $tags);
        $this->assertCount(2, $tags);
    }
}

/**
 * Post model (morphable)
 */
class PostMorphModel extends Model
{
    protected static string $table = 'posts';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['title', 'content'];

    public function image(): MorphOne
    {
        return $this->morphOne(ImageMorphModel::class, 'imageable');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(CommentMorphModel::class, 'morphable');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(TagMorphModel::class, 'taggable', 'taggables', 'taggable_id', 'tag_id');
    }

    public function save(): bool
    {
        if (!$this->exists) {
            $attributes = $reflection = new \ReflectionClass($this); $property = $reflection->getProperty("attributes"); $property->setAccessible(true); $attributes = $property->getValue($this); $attributes = array_filter($attributes, fn($v) => $v !== null);
            $columns = "`" . implode("`, `", array_keys($attributes)) . "`";
            $placeholders = ':' . implode(', :', array_keys($attributes));

            $sql = "INSERT INTO posts ({$columns}) VALUES ({$placeholders})";
            $stmt = $this->getConnection()->getPdo()->prepare($sql);

            foreach ($attributes as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }

            $stmt->execute();
            $this->setAttribute('id', (int) $this->getConnection()->getPdo()->lastInsertId());
            $this->exists = true;
            $this->syncOriginal();
            return true;
        }
        return true;
    }

    public function getKey(): mixed
    {
        return $this->getAttribute('id');
    }

    protected static function getConnection(): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        return parent::getConnection();
    }

    public static function query(): ModelQueryBuilder
    {
        return parent::query();
    }
}

/**
 * Video model (morphable)
 */
class VideoMorphModel extends Model
{
    protected static string $table = 'videos';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['title', 'url'];

    public function image(): MorphOne
    {
        return $this->morphOne(ImageMorphModel::class, 'imageable');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(CommentMorphModel::class, 'morphable');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(TagMorphModel::class, 'taggable', 'taggables', 'taggable_id', 'tag_id');
    }

    public function save(): bool
    {
        if (!$this->exists) {
            $attributes = $reflection = new \ReflectionClass($this); $property = $reflection->getProperty("attributes"); $property->setAccessible(true); $attributes = $property->getValue($this); $attributes = array_filter($attributes, fn($v) => $v !== null);
            $columns = "`" . implode("`, `", array_keys($attributes)) . "`";
            $placeholders = ':' . implode(', :', array_keys($attributes));

            $sql = "INSERT INTO videos ({$columns}) VALUES ({$placeholders})";
            $stmt = $this->getConnection()->getPdo()->prepare($sql);

            foreach ($attributes as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }

            $stmt->execute();
            $this->setAttribute('id', (int) $this->getConnection()->getPdo()->lastInsertId());
            $this->exists = true;
            $this->syncOriginal();
            return true;
        }
        return true;
    }

    public function getKey(): mixed
    {
        return $this->getAttribute('id');
    }

    protected static function getConnection(): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        return parent::getConnection();
    }

    public static function query(): ModelQueryBuilder
    {
        return parent::query();
    }
}

/**
 * Comment model (morphable - has morphable_type and morphable_id)
 */
class CommentMorphModel extends Model
{
    protected static string $table = 'comments';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['morphable_type', 'morphable_id', 'content'];

    public function morphable(): MorphTo
    {
        return $this->morphTo();
    }

    public function save(): bool
    {
        if (!$this->exists) {
            $attributes = $reflection = new \ReflectionClass($this); $property = $reflection->getProperty("attributes"); $property->setAccessible(true); $attributes = $property->getValue($this); $attributes = array_filter($attributes, fn($v) => $v !== null);
            $columns = "`" . implode("`, `", array_keys($attributes)) . "`";
            $placeholders = ':' . implode(', :', array_keys($attributes));

            $sql = "INSERT INTO comments ({$columns}) VALUES ({$placeholders})";
            $stmt = $this->getConnection()->getPdo()->prepare($sql);

            foreach ($attributes as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }

            $stmt->execute();
            $this->setAttribute('id', (int) $this->getConnection()->getPdo()->lastInsertId());
            $this->exists = true;
            $this->syncOriginal();
            return true;
        }
        return true;
    }

    public function getKey(): mixed
    {
        return $this->getAttribute('id');
    }

    protected static function getConnection(): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        return parent::getConnection();
    }

    public static function query(): ModelQueryBuilder
    {
        return parent::query();
    }
}

/**
 * Image model (morphable - has imageable_type and imageable_id)
 */
class ImageMorphModel extends Model
{
    protected static string $table = 'images';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['imageable_type', 'imageable_id', 'url'];

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function getConnection(): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        return parent::getConnection();
    }

    public static function query(): ModelQueryBuilder
    {
        return parent::query();
    }
}

/**
 * Tag model
 */
class TagMorphModel extends Model
{
    protected static string $table = 'tags';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;

    protected static array $fillable = ['name'];

    public function save(): bool
    {
        if (!$this->exists) {
            $attributes = $reflection = new \ReflectionClass($this); $property = $reflection->getProperty("attributes"); $property->setAccessible(true); $attributes = $property->getValue($this); $attributes = array_filter($attributes, fn($v) => $v !== null);
            $columns = "`" . implode("`, `", array_keys($attributes)) . "`";
            $placeholders = ':' . implode(', :', array_keys($attributes));

            $sql = "INSERT INTO tags ({$columns}) VALUES ({$placeholders})";
            $stmt = $this->getConnection()->getPdo()->prepare($sql);

            foreach ($attributes as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }

            $stmt->execute();
            $this->setAttribute('id', (int) $this->getConnection()->getPdo()->lastInsertId());
            $this->exists = true;
            $this->syncOriginal();
            return true;
        }
        return true;
    }

    public function getKey(): mixed
    {
        return $this->getAttribute('id');
    }

    protected static function getConnection(): \Toporia\Framework\Database\Contracts\ConnectionInterface
    {
        return parent::getConnection();
    }

    public static function query(): ModelQueryBuilder
    {
        return parent::query();
    }

    public static function hydrate(array $rows): ModelCollection
    {
        $models = [];
        foreach ($rows as $row) {
            $model = new static($row);
            $model->exists = true;
            $model->syncOriginal();
            $models[] = $model;
        }
        return new ModelCollection($models);
    }
}


