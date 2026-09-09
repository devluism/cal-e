<?php

namespace App\Console\Commands;

use App\Services\HistorialService;
use Illuminate\Console\Command;

/** Guarda la foto diaria de precios de todos los negocios. Programado en `routes/console.php`. */
class TomarFotoPrecios extends Command
{
    protected $signature = 'precios:snapshot';

    protected $description = 'Guarda la foto de hoy del precio de cada producto, para el historial';

    public function handle(HistorialService $historial): int
    {
        $resultado = $historial->tomarFotoDeTodos();

        $this->info("Fotografiados {$resultado['productos']} producto(s) de {$resultado['negocios']} negocio(s).");

        return self::SUCCESS;
    }
}
