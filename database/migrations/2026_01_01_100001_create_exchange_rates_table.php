<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tasa del día. **Infraestructura compartida, no dato de cada negocio.**
 *
 * La tasa oficial del BCV es la misma para todo el país, así que no lleva `tenant_id`: un
 * solo cron la trae una vez al día y sirve a toda la plataforma. Ponerle inquilino sería
 * guardar la misma cifra mil veces y pagar mil llamadas al proveedor por el mismo número.
 *
 * Lo que sí es de cada negocio es *cuál* usa (`tenants.rate_source`): la oficial o una
 * propia. Esa decisión vive en el inquilino; el valor de la oficial vive acá.
 *
 * `source` distingue en el historial lo que trajo el cron de lo que alguien cargó a mano
 * el día que el proveedor no respondió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();

            $table->decimal('price', 14, 4);
            $table->string('source', 20)->default('bcv');
            $table->string('provider')->nullable();

            $table->timestamps();

            // La consulta caliente es siempre "la más reciente".
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
