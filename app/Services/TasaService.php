<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Trae la tasa oficial del día desde los espejos públicos del BCV.
 *
 * Trasplantado de IGA, donde lleva tiempo en producción. Las dos reglas que lo hacen
 * confiable, y que acá importan todavía más porque la tasa ES el producto:
 *
 *  1. **Si ningún proveedor responde, NO se escribe nada.** Es preferible quedarse con la
 *     última tasa buena que pisarla con un cero o con basura: esa cifra multiplica los
 *     precios de todos los negocios de la plataforma a la vez. En IGA un error afectaba a
 *     una bodega; acá afectaría a todos los clientes el mismo día.
 *  2. **El valor se valida contra un rango de cordura** antes de guardarse. Un cambio de
 *     formato en el JSON del proveedor se manifiesta como un número absurdo, no como un
 *     error HTTP: sin el rango, entraría igual.
 *
 * La tasa es infraestructura compartida (ver la migración): una corrida sirve a toda la
 * plataforma, no una por negocio.
 */
class TasaService
{
    /**
     * Consulta los proveedores en orden y devuelve el primer valor utilizable.
     *
     * @return array{price: float, provider: string}
     *
     * @throws RuntimeException si ninguno responde con algo válido
     */
    public function consultar(): array
    {
        $fallos = [];

        foreach (config('exchange.providers') as $proveedor) {
            try {
                $respuesta = Http::timeout(config('exchange.timeout'))
                    ->acceptJson()
                    ->get($proveedor['url']);

                if (! $respuesta->successful()) {
                    $fallos[] = "{$proveedor['name']}: HTTP {$respuesta->status()}";

                    continue;
                }

                $valor = data_get($respuesta->json(), $proveedor['path']);

                if (! $this->esRazonable($valor)) {
                    $fallos[] = "{$proveedor['name']}: valor fuera de rango (".var_export($valor, true).')';

                    continue;
                }

                return ['price' => round((float) $valor, 4), 'provider' => $proveedor['name']];
            } catch (\Throwable $e) {
                $fallos[] = "{$proveedor['name']}: {$e->getMessage()}";
            }
        }

        throw new RuntimeException('Ningún proveedor devolvió una tasa válida. '.implode(' | ', $fallos));
    }

    /** Consulta y guarda. Devuelve la fila, o null si no se pudo y ya quedó constancia. */
    public function sincronizar(): ?ExchangeRate
    {
        try {
            ['price' => $precio, 'provider' => $proveedor] = $this->consultar();
        } catch (RuntimeException $e) {
            Log::warning("No se pudo actualizar la tasa del día: {$e->getMessage()}");

            return null;
        }

        $vigente = ExchangeRate::vigente();

        // No ensuciar el historial con una fila idéntica por cada corrida del programador.
        if ($vigente && (float) $vigente->price === $precio && $vigente->created_at->isToday()) {
            return $vigente;
        }

        return ExchangeRate::create([
            'price' => $precio,
            'source' => ExchangeRate::BCV,
            'provider' => $proveedor,
        ]);
    }

    private function esRazonable($valor): bool
    {
        if (! is_numeric($valor)) {
            return false;
        }

        $valor = (float) $valor;

        return $valor >= config('exchange.min') && $valor <= config('exchange.max');
    }
}
