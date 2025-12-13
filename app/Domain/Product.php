<?php

declare(strict_types=1);

namespace App\Domain;

use App\Application\Observers\ProductObserver;
use Toporia\Framework\Database\ORM\Model;

final class Product extends Model
{
    /**
     * The observers registered for this model.
     *
     * @var array<string>
     */
    protected static array $observers = [
        ProductObserver::class,
    ];

    /**
     * The table associated with the model.
     */
    protected static string $table = 'products';

    /**
     * The primary key for the model.
     */
    protected static string $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     */
    protected static array $fillable = [
        'title',
        'description',
        'price',
        'stock',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected static array $hidden = [
        //
    ];

    /**
     * The attributes that should be cast.
     */
    protected static array $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
