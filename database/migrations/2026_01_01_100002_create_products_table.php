<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo del negocio: lo que vende y a qué costo.
 *
 * ## La decisión que hace que el producto funcione
 *
 * El usuario declara su costo en la moneda que a él le sirva: la manicurista compró el
 * esmalte en dólares, la bodeguera pagó la harina en bolívares. Las dos son válidas y
 * obligarlo a convertir sería la primera fricción que lo haría abandonar.
 *
 * Pero un costo en bolívares **no es un costo estable**: son bolívares de un día concreto,
 * a una tasa concreta. Si mañana la tasa sube y seguimos calculando sobre esos mismos
 * bolívares, el precio de venta sube en Bs pero en dólares vale menos — que es exactamente
 * la descapitalización lenta que este producto viene a resolver. Guardar solo el número
 * sería reproducir el problema del usuario dentro de la herramienta.
 *
 * Por eso, cuando el costo se declara en bolívares se guarda **también la tasa de ese día**
 * (`cost_rate`). Con ella el costo se ancla a dólares una sola vez, y de ahí en adelante el
 * precio en bolívares se recalcula solo:
 *
 *     costo_usd  = cost_currency = 'USD' ? cost : cost / cost_rate
 *     precio_usd = costo_usd × (1 + margin/100)
 *     precio_bs  = redondear(precio_usd × tasa_de_hoy)
 *
 * El costo en dólares es el ancla; la tasa del día es la corriente. Es literalmente la
 * metáfora de la marca.
 *
 * ## Por qué el margen es porcentaje y no monto fijo
 *
 * Un monto fijo en bolívares se erosiona con la tasa igual que el costo. Un porcentaje se
 * mantiene. Se deja la puerta abierta a monto fijo en dólares más adelante, pero el MVP no
 * lo necesita y cada opción extra es una decisión más que tomar en el alta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('name');

            // Agrupa la lista al exportarla a WhatsApp. Texto libre y no una tabla aparte:
            // pedirle al usuario que primero cree categorías es una pantalla más antes del
            // "aha", y el aha tiene que ocurrir en menos de dos minutos.
            $table->string('category')->nullable();

            $table->decimal('cost', 14, 4)->default(0);
            $table->string('cost_currency', 3)->default('USD');

            // La tasa a la que se declaró el costo, si vino en bolívares. Ver el PHPDoc.
            $table->decimal('cost_rate', 14, 4)->nullable();

            // Margen sobre el costo, en por ciento.
            $table->decimal('margin', 8, 2)->default(0);

            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            // Toda consulta arranca por el negocio; el orden de la lista es el segundo eje.
            $table->index(['tenant_id', 'active']);
        });

        /*
         * Historial de precios.
         *
         * No es un lujo de auditoría: es lo que hace que el usuario *confíe* en que la
         * herramienta está trabajando. Sin verlo, tiene que creernos que los precios se
         * ajustaron solos, y el público objetivo —que hoy lo hace a ojo— no le cree a un
         * número que no puede contrastar.
         *
         * Se escribe una foto por producto y por día, no en cada lectura: el precio se
         * deriva siempre al vuelo del costo y la tasa (una sola fórmula, en `PrecioService`),
         * y esta tabla solo guarda la marca de cómo quedó ese día para poder graficarlo.
         */
        Schema::create('price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->date('date');

            $table->decimal('rate', 14, 4);
            $table->decimal('price_usd', 14, 2);
            $table->decimal('price_bs', 14, 2);

            $table->timestamps();

            // Una foto por producto por día: volver a correr el cálculo el mismo día
            // actualiza la del día en vez de apilar otra.
            $table->unique(['product_id', 'date']);
            $table->index(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_snapshots');
        Schema::dropIfExists('products');
    }
};
