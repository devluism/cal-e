<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El negocio (inquilino) y sus usuarios.
 *
 * **Norte es multi-inquilino desde la primera migración, y eso no es opcional.** IGA —el
 * sistema del que sale buena parte de este código— es de un solo negocio: `settings` es
 * global, la tasa es "la tasa de la tienda", y los usuarios son empleados de *ese* local.
 * Meterle inquilinos a un esquema así después es de las migraciones más caras que existen:
 * hay que tocar cada tabla, cada consulta y cada índice, y un solo `where` olvidado le
 * muestra a un negocio los precios de otro. Por eso se paga el costo ahora.
 *
 * ### La forma: una base, esquema compartido, `tenant_id` + scope global
 *
 * Se descartó base-por-inquilino a propósito. A 3–5 $/mes por cuenta, mantener y migrar N
 * bases cuesta más que el producto entero. Con esquema compartido hay una sola migración,
 * un solo backup y un solo cron. El riesgo —una consulta sin filtrar— se cubre con un
 * scope global en el modelo base (`App\Models\Concerns\PerteneceAlNegocio`), no con la
 * disciplina de acordarse en cada `where`.
 *
 * ### Lo que NO lleva `tenant_id`
 *
 * `exchange_rates` es infraestructura compartida: la tasa del BCV es la misma para todos
 * los negocios del país. Un cron la trae una vez y sirve a toda la plataforma. Lo que sí es
 * de cada negocio es si la usa o prefiere la suya (ver `tenants.rate_source`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            // Para la URL pública del catálogo y para identificarlo en soporte.
            $table->string('slug')->unique();

            $table->string('phone')->nullable();
            $table->string('logo')->nullable();

            /*
             * Qué tasa usa este negocio para convertir a bolívares.
             *
             * `bcv` es la oficial que trae el cron. `propia` es para quien vende a una tasa
             * distinta —muy común: el que factura a tasa paralela o le suma unos puntos—.
             * Sin esta opción el producto sería inútil para media clientela, que igual
             * terminaría ajustando a mano, que es justo el trabajo que venimos a quitar.
             */
            $table->string('rate_source', 12)->default('bcv');
            $table->decimal('custom_rate', 14, 4)->nullable();

            /*
             * Redondeo del precio final, en bolívares.
             *
             * Un precio de 187,43 Bs no se puede cobrar en un mostrador: nadie tiene ese
             * vuelto. El comerciante redondea siempre, y si la herramienta no lo hace por
             * él, lo hace a mano — y volvemos al problema original. Se redondea hacia
             * ARRIBA por defecto: hacia abajo se estaría comiendo el margen que el producto
             * existe para proteger.
             */
            $table->decimal('rounding', 10, 2)->default(0);

            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();

            /*
             * Un usuario pertenece a un negocio. En la V1 es uno por cuenta (el dueño), pero
             * la columna se pone desde ahora: agregar empleados después es sumar filas, no
             * migrar el esquema.
             */
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();

            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();

            // Entra con Google o con correo: el público no siempre recuerda contraseñas.
            $table->string('google_id')->nullable()->unique();
            $table->string('avatar')->nullable();

            $table->string('role', 20)->default('owner');
            $table->boolean('active')->default(true);

            $table->rememberToken();
            $table->timestamps();

            $table->index('tenant_id');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('tenants');
    }
};
