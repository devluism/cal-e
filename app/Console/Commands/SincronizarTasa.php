<?php

namespace App\Console\Commands;

use App\Services\TasaService;
use Illuminate\Console\Command;

/**
 * Trae la tasa del día. Es el latido de Norte: sin esto el producto no hace nada.
 *
 * Corre una vez y sirve a toda la plataforma — la tasa del BCV es la misma para todos los
 * negocios (ver la migración de `exchange_rates`).
 */
class SincronizarTasa extends Command
{
    protected $signature = 'tasa:sync';

    protected $description = 'Trae la tasa oficial del día desde los espejos del BCV';

    public function handle(TasaService $tasas): int
    {
        $tasa = $tasas->sincronizar();

        if (! $tasa) {
            // Se devuelve fallo para que el programador de tareas lo registre, pero NO se
            // pisa la última tasa buena: es preferible operar con la de ayer —diciéndolo en
            // pantalla— que con un cero.
            $this->error('Ningún proveedor respondió. Se mantiene la última tasa conocida.');

            return self::FAILURE;
        }

        $this->info("Tasa del día: {$tasa->price} Bs/\$ ({$tasa->provider})");

        return self::SUCCESS;
    }
}
