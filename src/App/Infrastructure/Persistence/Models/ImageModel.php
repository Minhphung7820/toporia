<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Image ORM Model for testing polymorphic relationships.
 *
 * @property int $id
 * @property string|null $imageable_type
 * @property int|null $imageable_id
 * @property string $url
 * @property string|null $alt_text
 * @property int $width
 * @property int $height
 * @property int $size
 * @property string $created_at
 * @property string $updated_at
 */
class ImageModel extends Model
{
    protected static string $table = 'images';

    protected static array $fillable = [
        'imageable_type',
        'imageable_id',
        'url',
        'alt_text',
        'width',
        'height',
        'size',
    ];

    protected static array $casts = [
        'imageable_id' => 'int',
        'width' => 'int',
        'height' => 'int',
        'size' => 'int',
    ];

    /**
     * Image belongs to Post or Video (polymorphic inverse - morphTo).
     */
    public function imageable()
    {
        return $this->morphTo('imageable');
    }
}


