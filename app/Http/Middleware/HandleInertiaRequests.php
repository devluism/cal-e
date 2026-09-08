<?php

namespace App\Http\Middleware;

use App\Models\ExchangeRate;
use App\Services\PrecioService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props presentes en todas las páginas.
     *
     * La tasa se comparte acá y no se pide por fetch en cada pantalla: es el dato que el
     * usuario mira primero al abrir la app —«¿a cómo está hoy?»— y tenerlo desde la primera
     * carga es la diferencia entre que la herramienta se sienta viva o se sienta lenta.
     */
    public function share(Request $request): array
    {
        $usuario = $request->user();
        $negocio = $usuario?->tenant;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $usuario ? [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                    'avatar' => $usuario->avatar,
                ] : null,
                'negocio' => $negocio ? [
                    'id' => $negocio->id,
                    'name' => $negocio->name,
                    'slug' => $negocio->slug,
                    'rate_source' => $negocio->rate_source,
                    'rounding' => (float) $negocio->rounding,
                ] : null,
            ],
            'tasa' => $this->tasa($negocio),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * La tasa vigente y cuándo se actualizó.
     *
     * `actualizada_hace` no es decorativo: con conectividad inestable el cron puede no haber
     * corrido, y el usuario tiene derecho a saber que está viendo la tasa de ayer antes de
     * mandarle la lista de precios a un cliente. Mostrar la última buena y decir de cuándo es
     * siempre es mejor que romperse o que mentir con un número viejo sin avisar.
     */
    private function tasa($negocio): ?array
    {
        if (! $negocio) {
            return null;
        }

        $vigente = ExchangeRate::vigente();

        return [
            'valor' => app(PrecioService::class)->tasaDe($negocio),
            'fuente' => $negocio->rate_source,
            'actualizada' => $vigente?->created_at?->diffForHumans(),
            'es_de_hoy' => (bool) $vigente?->created_at?->isToday(),
        ];
    }
}
