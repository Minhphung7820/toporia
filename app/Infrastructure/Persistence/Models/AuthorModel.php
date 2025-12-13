<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Author Model
 *
 * Relationships demonstrated:
 * - belongsTo: city, user
 * - hasMany: books
 * - hasOne: latestBook
 * - hasOneThrough: country (through city)
 */
class AuthorModel extends Model
{
    protected static string $table = 'authors';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'user_id',
        'city_id',
        'pen_name',
        'bio',
        'books_count',
        'rating',
        'is_verified'
    ];
    protected static array $casts = [
        'user_id' => 'int',
        'city_id' => 'int',
        'books_count' => 'int',
        'rating' => 'float',
        'is_verified' => 'bool',
    ];

    /**
     * belongsTo: An author belongs to a city
     */
    public function city()
    {
        return $this->belongsTo(CityModel::class, 'city_id');
    }

    /**
     * belongsTo: An author optionally belongs to a user account
     */
    public function user()
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    /**
     * hasMany: An author has many books
     */
    public function books()
    {
        return $this->hasMany(BookModel::class, 'author_id');
    }

    /**
     * hasOne: An author's latest book
     */
    public function latestBook()
    {
        return $this->hasOne(BookModel::class, 'author_id')
            ->orderBy('published_year', 'DESC')
            ->orderBy('created_at', 'DESC');
    }

    /**
     * hasOne: An author's bestselling book
     */
    public function bestsellerBook()
    {
        return $this->hasOne(BookModel::class, 'author_id')
            ->where('is_bestseller', true)
            ->orderBy('rating', 'DESC');
    }

    // Note: To get country, use: $author->city->country (nested belongsTo)
    // hasOneThrough is not used here for simplicity
}
