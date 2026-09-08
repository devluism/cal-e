<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\Tenant;

/**
 * El cálculo del precio vivo. **Única fórmula del precio en toda la aplicación.**
 *
 * Es la misma disciplina que en IGA, donde el total de una orden se deriva siempre de un
 * solo método: dos fórmulas repartidas terminan discrepando en el redondeo, y esa
 * diferencia aparece después como un precio que no cuadra con el que se le mostró al
 * cliente. Acá pasa igual, y peor: el precio se muestra en pantalla, se exporta a WhatsApp
 * y se guarda en el historial. Si cada una lo calculara por su lado, el usuario vería tres
 * números distintos para el mismo producto y dejaría de confiar en la herramienta — que es
 * lo único que tenemos.
 *
 * La cadena es siempre la misma:
 *
 *     costo_usd  = costo anclado a dólares (lo resuelve `Product::cost_usd`)
 *     precio_usd = costo_usd × (1 + margen/100)
 *     precio_bs  = redondear(precio_usd × tasa_del_negocio)
 */
class PrecioService
{
    /**
     * La tasa que le toca a este negocio.
     *
     * La oficial del BCV, o la suya propia si la configuró. Devuelve 0 cuando no hay
     * ninguna cargada, y el llamador decide qué hacer: nunca se inventa una tasa, porque un
     * número inventado acá se convierte en un precio mal cobrado en el mostrador.
     */
    public function tasaDe(Tenant $negocio): float
    {
        if ($negocio->usa_tasa_propia) {
            return round((float) $negocio->custom_rate, 4);
        }

        return round((float) (ExchangeRate::vigente()?->price ?? 0), 4);
    }

    /**
     * Calcula el precio de un producto a una tasa dada.
     *
     * @return array{costo_usd: float, precio_usd: float, precio_bs: float, margen: float}
     */
    public function calcular(Product $producto, float $tasa, float $redondeo = 0): array
    {
        $costoUsd = (float) $producto->cost_usd;
        $margen = (float) $producto->margin;

        $precioUsd = round($costoUsd * (1 + $margen / 100), 2);
        $precioBs = $this->redondear($precioUsd * $tasa, $redondeo);

        return [
            'costo_usd' => round($costoUsd, 2),
            'precio_usd' => $precioUsd,
            'precio_bs' => $precioBs,
            'margen' => $margen,
        ];
    }

    /** Calcula todo el catálogo de un negocio de una pasada. */
    public function calcularCatalogo(Tenant $negocio, $productos = null): array
    {
        $tasa = $this->tasaDe($negocio);
        $redondeo = (float) $negocio->rounding;

        $productos ??= Product::delNegocio($negocio->id)->activos()
            ->orderBy('position')->orderBy('name')->get();

        return [
            'tasa' => $tasa,
            'productos' => $productos->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category,
                ...$this->calcular($p, $tasa, $redondeo),
            ])->values()->all(),
        ];
    }

    /**
     * Redondea el precio en bolívares al múltiplo que use el negocio.
     *
     * **Hacia arriba, siempre.** Un precio de 187,43 Bs no se puede cobrar en un mostrador
     * —nadie tiene ese vuelto—, así que el comerciante lo redondea igual; si la herramienta
     * no lo hace, lo hace él a mano y volvimos al trabajo que vinimos a quitarle. Y se
     * redondea hacia arriba porque hacia abajo estaríamos comiéndonos el margen que este
     * producto existe para proteger: un céntimo por venta, todos los días, es exactamente la
     * fuga lenta contra la que se construyó esto.
     *
     * Con `$redondeo` en 0 no se toca nada más allá de los dos decimales.
     */
    public function redondear(float $monto, float $redondeo): float
    {
        if ($redondeo <= 0) {
            return round($monto, 2);
        }

        return round(ceil($monto / $redondeo) * $redondeo, 2);
    }
}
