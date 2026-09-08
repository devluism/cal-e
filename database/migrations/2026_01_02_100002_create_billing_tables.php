<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suscripciones y cobros.
 *
 * ## Lo que de verdad importa acá no es cómo entra la plata
 *
 * En Venezuela el riel de cobro es la parte inestable: hoy es Pago Móvil reportado a mano,
 * mañana puede ser C2P con acuerdo bancario, Binance Pay o una pasarela local. Lo que **no**
 * cambia es la máquina de estados del acceso: cuándo alguien puede usar la herramienta y
 * cuándo no. Por eso el diseño separa las dos cosas:
 *
 * - `subscriptions` manda sobre el acceso y no sabe nada de bancos.
 * - `payments` es el registro de que entró dinero, con el riel que sea.
 *
 * Conectar una confirmación automática después es escribir una clase que marque un pago
 * como confirmado. Nada del control de acceso se entera.
 *
 * ## La máquina de estados, y por qué tiene un período de gracia
 *
 *     prueba → activa → gracia → suspendida
 *                ↑         ↓
 *                └── pago ─┘
 *
 * **La gracia no es generosidad, es realismo.** El público cobra por Pago Móvil, tiene
 * ingresos irregulares y paga cuando puede — cortarle el acceso a la medianoche del día que
 * vence es perder un cliente que iba a pagar el jueves. Y el costo de dejarlo entrar tres
 * días más es cero.
 *
 * ## Suspendida no es "afuera"
 *
 * Un suspendido **sigue viendo su catálogo**: sus costos y sus márgenes son suyos y los
 * cargó él. Lo que pierde es la parte viva —el precio recalculado con la tasa de hoy y la
 * exportación—, que es exactamente lo que paga. Borrarle los datos o dejarlo fuera del todo
 * es la forma más segura de que no vuelva nunca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();

            // El precio se lleva en dólares y se convierte a bolívares al momento de pagar.
            // Guardarlo en Bs obligaría a re-tarifar cada vez que se mueve la tasa, que es
            // justo el trabajo del que este producto libera a sus usuarios.
            $table->decimal('price_usd', 10, 2)->default(0);

            $table->unsignedSmallInteger('days')->default(30);

            // Tope de productos del plan. Null = sin tope.
            $table->unsignedInteger('max_products')->nullable();

            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            // Se suscribe el NEGOCIO, no el usuario: cuando haya empleados, todos comparten
            // el plan del negocio. Único por inquilino — una suscripción vigente por vez.
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();

            $table->string('status', 12)->default('prueba');

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });

        /*
         * A dónde le paga el cliente. En una tabla y no en `config/` porque son datos que
         * cambian sin despliegue —el negocio cambia de banco, agrega otro método— y porque
         * el usuario los tiene que ver en pantalla para copiarlos al hacer el Pago Móvil.
         */
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();

            // pago_movil | transferencia | binance | efectivo
            $table->string('method', 20);

            $table->string('label');
            $table->string('bank')->nullable();
            $table->string('id_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('holder')->nullable();
            $table->string('extra')->nullable();

            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();

            // Copia de a dónde pagó: cambiar de banco no puede reescribir los pagos viejos.
            // Misma razón por la que IGA congela el método en `order_payments`.
            $table->string('method_snapshot')->nullable();

            /*
             * El monto se congela en LAS DOS monedas, con la tasa usada.
             *
             * Es la lección de `order_payments` en IGA: si el equivalente en bolívares se
             * recalculara al leer, un pago de ayer aparecería hoy con otro monto y el
             * cliente diría —con razón— que pagó lo que le pidieron. La tasa del momento
             * queda escrita.
             */
            $table->decimal('amount_usd', 12, 2)->default(0);
            $table->decimal('amount_bs', 14, 2)->default(0);
            $table->decimal('exchange_rate', 14, 4)->default(0);

            // Referencia del Pago Móvil / transferencia. Es lo que permite cotejarlo.
            $table->string('reference')->nullable();
            $table->date('paid_on')->nullable();

            // reportado | confirmado | rechazado
            $table->string('status', 12)->default('reportado');

            $table->text('notes')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);

            /*
             * Dos pagos no pueden compartir referencia: es lo que impide que alguien reporte
             * el mismo comprobante dos veces para estirar su suscripción. Es nullable porque
             * un pago en efectivo puede no tenerla, y Postgres permite varios nulos en un
             * índice único.
             */
            $table->unique('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_accounts');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
