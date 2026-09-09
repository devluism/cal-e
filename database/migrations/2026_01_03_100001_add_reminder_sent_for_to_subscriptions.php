<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué aviso de vencimiento fue el último que se mandó, para no repetirlo.
 *
 * `suscripcion:avisar` corre todos los días; sin guardar esto mandaría el mismo correo cada
 * vez que corriera mientras la suscripción se quede en el mismo hito (por ejemplo, todos los
 * días que faltan más de 3 y menos de 1). El valor es el hito mismo ('3', '1', 'vencida'), no
 * un booleano: cambia solo cuando el hito cambia, así que se resetea solo en cada ciclo nuevo
 * de pago sin necesidad de limpiarlo a mano (ver `SuscripcionService::hitoDeAviso`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('reminder_sent_for')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_for');
        });
    }
};
