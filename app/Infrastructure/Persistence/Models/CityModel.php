<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * City Model
 *
 * Relationships demonstrated:
 * - belongsTo: country
 * - hasMany: authors
 * - hasManyThrough: books (through authors)
 */
class CityModel extends Model
{
    protected static string $table = 'cities';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'country_id',
        'name',
        'population',
        'is_capital',
        'latitude',
        'longitude'
    ];
    protected static array $casts = [
        'country_id' => 'int',
        'population' => 'int',
        'is_capital' => 'bool',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * belongsTo: A city belongs to a country
     */
    public function country()
    {
        return $this->belongsTo(CountryModel::class, 'country_id');
    }

    /**
     * hasMany: A city has many authors
     */
    public function authors()
    {
        return $this->hasMany(AuthorModel::class, 'city_id');
    }

    /**
     * hasManyThrough: A city has many books through authors
     * Complex: City -> Authors -> Books
     */
    public function books()
    {
        return $this->hasManyThrough(
            BookModel::class,
            AuthorModel::class,
            'city_id',      // Foreign key on authors table
            'author_id',    // Foreign key on books table
            'id',           // Local key on cities table
            'id'            // Local key on authors table
        )->where('rating', '>', 4);
    }

    /**
     * hasOne: A city might have one capital author (verified with highest rating)
     */
    public function topAuthor()
    {
        return $this->hasOne(AuthorModel::class, 'city_id')
            ->where('is_verified', true)
            ->orderBy('rating', 'DESC');
    }
}
