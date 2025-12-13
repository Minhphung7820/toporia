<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

/**
 * Country Model
 *
 * Relationships demonstrated:
 * - hasMany: cities, publishers
 * - hasManyThrough: authors (through cities)
 */
class CountryModel extends Model
{
    protected static string $table = 'countries';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'name', 'code', 'continent', 'population', 'is_active'
    ];
    protected static array $casts = [
        'population' => 'int',
        'is_active' => 'bool',
    ];

    /**
     * hasMany: A country has many cities
     */
    public function cities()
    {
        return $this->hasMany(CityModel::class, 'country_id');
    }

    /**
     * hasMany: A country has many publishers
     */
    public function publishers()
    {
        return $this->hasMany(PublisherModel::class, 'country_id');
    }

    /**
     * hasManyThrough: A country has many authors through cities
     * Complex: Country -> Cities -> Authors
     */
    public function authors()
    {
        return $this->hasManyThrough(
            AuthorModel::class,    // Final model
            CityModel::class,      // Through model
            'country_id',          // Foreign key on through table (cities.country_id)
            'city_id',             // Foreign key on final table (authors.city_id)
            'id',                  // Local key on this table (countries.id)
            'id'                   // Local key on through table (cities.id)
        );
    }

    /**
     * hasManyThrough: A country has many books through authors
     * Ultra Complex: Country -> Cities -> Authors -> Books
     */
    public function booksViaAuthors()
    {
        return $this->hasManyThrough(
            BookModel::class,
            AuthorModel::class,
            'country_id',      // Wrong! This should use cities as intermediate
            'author_id',
            'id',
            'id'
        );
    }
}

