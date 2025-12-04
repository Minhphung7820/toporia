<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Review ORM Model.
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $user_id
 * @property string|null $name
 * @property string|null $email
 * @property int $rating
 * @property string|null $title
 * @property string|null $comment
 * @property bool $is_approved
 * @property bool $is_featured
 * @property int $helpful_count
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 */
class ReviewModel extends Model
{
    protected static array $fillable = [
        'product_id',
        'user_id',
        'name',
        'email',
        'rating',
        'title',
        'comment',
        'is_approved',
        'is_featured',
        'helpful_count',
    ];

    protected static array $casts = [
        'product_id' => 'int',
        'user_id' => 'int',
        'rating' => 'int',
        'is_approved' => 'bool',
        'is_featured' => 'bool',
        'helpful_count' => 'int',
    ];

    /**
     * Product this review belongs to.
     */
    public function product()
    {
        return $this->belongsTo(ProductModel::class);
    }

    /**
     * User who wrote this review.
     */
    public function user()
    {
        return $this->belongsTo(UserModel::class, 'user_id', 'id');
    }

    /**
     * Scope: Approved reviews.
     */
    public static function approved()
    {
        return static::query()->where('is_approved', true);
    }

    /**
     * Scope: Featured reviews.
     */
    public static function featured()
    {
        return static::query()->where('is_featured', true);
    }
}
