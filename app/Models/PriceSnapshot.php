<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlNegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** La foto del precio de un producto en un día. Ver la migración. */
class PriceSnapshot extends Model
{
    use PerteneceAlNegocio;

    protected $fillable = ['tenant_id', 'product_id', 'date', 'rate', 'price_usd', 'price_bs'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'rate' => 'decimal:4',
            'price_usd' => 'decimal:2',
            'price_bs' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
