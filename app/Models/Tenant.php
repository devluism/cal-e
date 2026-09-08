<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute as EloquentAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un negocio. Es el inquilino: todo lo demás cuelga de acá.
 */
class Tenant extends Model
{
    use HasFactory;

    public const TASA_BCV = 'bcv';

    public const TASA_PROPIA = 'propia';

    protected $fillable = [
        'name',
        'slug',
        'phone',
        'logo',
        'rate_source',
        'custom_rate',
        'rounding',
    ];

    protected function casts(): array
    {
        return [
            'custom_rate' => 'decimal:4',
            'rounding' => 'decimal:2',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected function usaTasaPropia(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: fn () => $this->rate_source === self::TASA_PROPIA && (float) $this->custom_rate > 0,
        );
    }
}
