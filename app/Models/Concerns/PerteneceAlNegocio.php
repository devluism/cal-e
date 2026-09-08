<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Ata un modelo al negocio del usuario que está en sesión.
 *
 * **Esta es la pieza que sostiene la multi-inquilinancia entera.** Con esquema compartido,
 * lo único que separa los datos de un negocio de los de otro es un `where tenant_id = …`, y
 * confiar en que alguien se acuerde de escribirlo en cada consulta es cuestión de tiempo
 * para que se filtre: basta un `Product::all()` en un endpoint nuevo para mostrarle a un
 * bodeguero los costos de la competencia.
 *
 * Por eso el filtro es un **scope global**: se aplica solo, en toda consulta, sin que nadie
 * tenga que recordarlo. Y el `tenant_id` se rellena solo al crear, por la misma razón — un
 * insert sin inquilino deja una fila huérfana que después no aparece en ninguna parte.
 *
 * Para las tareas de consola (el cron de precios recorre todos los negocios) el scope se
 * desactiva a propósito con `sinNegocio()`, que es explícito y se ve en el código.
 */
trait PerteneceAlNegocio
{
    public static function bootPerteneceAlNegocio(): void
    {
        static::addGlobalScope('negocio', function (Builder $query) {
            $tenantId = Auth::user()?->tenant_id;

            // Sin sesión no se filtra por un id nulo (traería todo): se corta la consulta.
            // Es la opción segura — mejor no devolver nada que devolverlo todo.
            if (! Auth::check()) {
                return;
            }

            $query->where($query->getModel()->getTable().'.tenant_id', $tenantId);
        });

        static::creating(function ($modelo) {
            $modelo->tenant_id ??= Auth::user()?->tenant_id;
        });
    }

    /**
     * Consulta sin el filtro de inquilino.
     *
     * Solo para procesos que operan sobre toda la plataforma —el cron que recalcula precios
     * de todos los negocios—. Que sea explícito es el punto: se ve en el código quién se
     * está saltando el aislamiento.
     */
    public static function sinNegocio(): Builder
    {
        return static::query()->withoutGlobalScope('negocio');
    }

    /** Consulta acotada a un negocio concreto, sin depender de la sesión. */
    public static function delNegocio(int $tenantId): Builder
    {
        return static::sinNegocio()->where('tenant_id', $tenantId);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
