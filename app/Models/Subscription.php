<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute as EloquentAttribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La suscripción de un negocio. Manda sobre el acceso y no sabe nada de bancos.
 *
 * No usa el scope de inquilino a propósito: el proceso que vence suscripciones recorre
 * todas las de la plataforma, y el middleware la consulta antes de que haya contexto de
 * negocio establecido.
 */
class Subscription extends Model
{
    public const PRUEBA = 'prueba';

    public const ACTIVA = 'activa';

    public const GRACIA = 'gracia';

    public const SUSPENDIDA = 'suspendida';

    public const CANCELADA = 'cancelada';

    /** Días que se deja entrar después de vencer. Ver el porqué en la migración. */
    public const DIAS_GRACIA = 3;

    public const DIAS_PRUEBA = 14;

    protected $fillable = [
        'tenant_id', 'plan_id', 'status', 'trial_ends_at', 'ends_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** ¿Puede usar la parte viva del producto? */
    protected function daAcceso(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: fn () => in_array($this->status, [self::PRUEBA, self::ACTIVA, self::GRACIA], true),
        );
    }

    /** Cuándo se le acaba lo que tiene pago (o la prueba). */
    protected function venceEl(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: fn () => $this->status === self::PRUEBA ? $this->trial_ends_at : $this->ends_at,
        );
    }

    /** Días que le quedan. Negativo si ya venció. */
    protected function diasRestantes(): EloquentAttribute
    {
        return EloquentAttribute::make(get: function () {
            $vence = $this->vence_el;

            return $vence ? (int) now()->startOfDay()->diffInDays($vence->startOfDay(), false) : 0;
        });
    }
}
