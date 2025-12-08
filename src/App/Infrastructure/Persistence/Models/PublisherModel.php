<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;

class PublisherModel extends Model
{
    protected static string $table = 'publishers';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [
        'country_id', 'name', 'email', 'website', 'founded_year', 'is_active'
    ];
    protected static array $casts = [
        'country_id' => 'int',
        'founded_year' => 'int',
        'is_active' => 'bool',
    ];

    public function country()
    {
        return $this->belongsTo(CountryModel::class, 'country_id');
    }

    public function books()
    {
        return $this->hasMany(BookModel::class, 'publisher_id');
    }
}

