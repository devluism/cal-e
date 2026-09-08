<?php

namespace App\Http\Middleware;

use App\Services\SuscripcionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege lo que se paga: la parte viva del producto.
 *
 * Un suspendido **no queda fuera de la aplicación**. Sigue entrando y sigue viendo su
 * catálogo —sus costos y sus márgenes los cargó él y son suyos—; lo que pierde es el precio
 * recalculado con la tasa de hoy y la exportación, que es exactamente lo que paga. Dejarlo
 * fuera del todo o borrarle los datos es la forma más segura de que no vuelva.
 *
 * Por eso este middleware va sobre las rutas del valor vivo, no sobre toda la sesión: lo
 * manda a la pantalla de suscripción en vez de al login.
 */
class RequiereSuscripcion
{
    public function __construct(private SuscripcionService $suscripciones) {}

    public function handle(Request $request, Closure $next): Response
    {
        $negocio = $request->user()?->tenant;

        if (! $negocio) {
            abort(403);
        }

        if (! $this->suscripciones->para($negocio)->da_acceso) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Tu suscripción venció. Renuévala para volver a ver tus precios al día.'], 402)
                : redirect()->route('suscripcion')->with('error', 'Tu suscripción venció. Renuévala para volver a ver tus precios al día.');
        }

        return $next($request);
    }
}
