<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlNegocio;
use Illuminate\Database\Eloquent\Casts\Attribute as EloquentAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un producto o servicio del catálogo. Ver la migración para el porqué del modelo de costo.
 */
class Product extends Model
{
    use HasFactory, PerteneceAlNegocio;

    public const USD = 'USD';

    public const VES = 'VES';

    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'cost',
        'cost_currency',
        'cost_rate',
        'margin',
        'active',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:4',
            'cost_rate' => 'decimal:4',
            'margin' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PriceSnapshot::class);
    }

    /**
     * El costo anclado a dólares.
     *
     * Es el número sobre el que se calcula todo. Un costo declarado en bolívares se ancla
     * con la tasa del día en que se declaró: sin eso, el costo se erosionaría con la tasa
     * y la herramienta reproduciría adentro el problema que viene a resolver.
     */
    protected function costUsd(): EloquentAttribute
    {
        return EloquentAttribute::make(get: function () {
            $costo = (float) $this->cost;

            if ($this->cost_currency === self::USD) {
                return round($costo, 4);
            }

            $tasa = (float) $this->cost_rate;

            // Sin tasa de referencia el costo en bolívares no significa nada: se devuelve
            // cero para que el precio salga en cero y se note, en vez de inventar un número.
            return $tasa > 0 ? round($costo / $tasa, 4) : 0.0;
        });
    }

    public function scopeActivos($query)
    {
        return $query->where('active', true);
    }
}
