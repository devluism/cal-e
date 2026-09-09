<?php

namespace App\Services;

use App\Models\PriceSnapshot;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * La foto diaria del precio de cada producto, y el historial que se arma con ellas.
 *
 * Es lo que hace que el usuario *crea* que la herramienta trabaja, en vez de tener que
 * creernos: sin esto, «tus precios se ajustan solos» es una promesa en el texto de la
 * pantalla de entrada y nada más. Con el historial, el usuario ve la línea subir sola día
 * tras día — la prueba está en su propia lista, no en lo que decimos de ella.
 *
 * No se recalcula el historial al leerlo: es una FOTO. `PrecioService::calcular()` con la
 * tasa de hoy dice cuánto vale un producto *ahora*; esta tabla dice cuánto valía *entonces*,
 * y esos dos números tienen que poder ser distintos —la tasa de ayer ya no existe en
 * ninguna parte una vez que cambió— o el historial dejaría de significar nada.
 */
class HistorialService
{
    public function __construct(private PrecioService $precios) {}

    /**
     * Guarda la foto de un negocio para una fecha (hoy, si no se indica otra).
     *
     * Sin tasa cargada no se fotografía nada: un snapshot en 0,00 Bs mentiría en el
     * historial exactamente igual que mostrarlo en pantalla, y además sería imposible de
     * distinguir después de un "de verdad no valía nada ese día".
     *
     * El `updateOrCreate` sobre `(product_id, date)` es lo que hace que correr el comando
     * dos veces el mismo día actualice la foto en vez de dejarla duplicada o de reventar
     * contra el índice único.
     */
    public function tomarFoto(Tenant $negocio, ?Carbon $fecha = null): int
    {
        $tasa = $this->precios->tasaDe($negocio);

        if ($tasa <= 0) {
            return 0;
        }

        $fecha ??= now()->startOfDay();
        $redondeo = (float) $negocio->rounding;

        $productos = Product::delNegocio($negocio->id)->activos()->get();

        foreach ($productos as $producto) {
            $calculo = $this->precios->calcular($producto, $tasa, $redondeo);

            PriceSnapshot::updateOrCreate(
                ['product_id' => $producto->id, 'date' => $fecha->toDateString()],
                [
                    // Explícito y no dejado al scope global: este método corre desde un
                    // comando de consola, sin usuario en sesión, así que no hay de dónde
                    // más sacar el inquilino.
                    'tenant_id' => $negocio->id,
                    'rate' => $tasa,
                    'price_usd' => $calculo['precio_usd'],
                    'price_bs' => $calculo['precio_bs'],
                ],
            );
        }

        return $productos->count();
    }

    /**
     * Recorre todos los negocios de la plataforma. Es lo que llama el comando programado.
     *
     * `Tenant::each()` recorre en bloques en vez de cargar todos los negocios en memoria de
     * una vez: hoy son pocos, pero es la forma que no hay que volver a tocar cuando dejen
     * de serlo.
     */
    public function tomarFotoDeTodos(?Carbon $fecha = null): array
    {
        $negocios = 0;
        $productos = 0;

        Tenant::query()->each(function (Tenant $negocio) use ($fecha, &$negocios, &$productos) {
            $fotografiados = $this->tomarFoto($negocio, $fecha);

            if ($fotografiados > 0) {
                $negocios++;
                $productos += $fotografiados;
            }
        });

        return ['negocios' => $negocios, 'productos' => $productos];
    }

    /** El historial de un producto, del más viejo al más reciente, para graficarlo. */
    public function historialDe(Product $producto, int $dias = 30): Collection
    {
        return PriceSnapshot::where('product_id', $producto->id)
            ->where('date', '>=', now()->subDays($dias)->toDateString())
            ->orderBy('date')
            ->get();
    }
}
