<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Log ORM Model (MongoDB)
 *
 * Example MongoDB model demonstrating $collection property usage.
 * This model uses MongoDB connection and collection instead of SQL table.
 *
 * Clean Architecture:
 * - Infrastructure layer (depends on framework)
 * - Uses MongoDBGrammar automatically
 * - Collection name specified via $collection property
 *
 * @property string $_id MongoDB document ID
 * @property int $user_id User ID
 * @property string $level Log level (info, warning, error)
 * @property string $message Log message
 * @property array $metadata Additional metadata
 * @property string $created_at Creation timestamp
 */
class LogModel extends Model
{
    /**
     * MongoDB connection name.
     */
    protected static ?string $connection = 'mongodb';

    /**
     * MongoDB collection name.
     * For MongoDB, use $collection instead of $table.
     */
    protected static string $collection = 'logs';

    /**
     * Fillable attributes.
     */
    protected static array $fillable = [
        'user_id',
        'level',
        'message',
        'metadata',
        'created_at',
    ];

    /**
     * Hidden attributes from JSON output.
     */
    protected static array $hidden = ['internal_id'];

    /**
     * Attribute casts.
     * MongoDB supports nested arrays/objects natively.
     */
    protected static array $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Primary key for MongoDB (usually _id).
     * MongoDB uses _id by default, but we can use 'id' for consistency.
     */
    protected static string $primaryKey = '_id';
}











