<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un plan de suscripción. El precio vive en dólares — ver la migración. */
class Plan extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'price_usd', 'days', 'max_products', 'active', 'position',
    ];

    protected function casts(): array
    {
        return [
            'price_usd' => 'decimal:2',
            'days' => 'integer',
            'max_products' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('active', true)->orderBy('position')->orderBy('price_usd');
    }
}
