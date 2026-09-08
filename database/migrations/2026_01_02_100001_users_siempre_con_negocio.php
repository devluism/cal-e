<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * `users.tenant_id` pasa a ser obligatorio.
 *
 * La invariante de Norte es «no existe un usuario sin negocio» —está escrita en
 * `AuthController::registrar()`, que crea los dos en la misma transacción— pero el esquema
 * la contradecía: la columna era nullable. Y todo estado que el esquema permite, tarde o
 * temprano aparece: bastó un usuario creado a mano por fuera del registro para que el panel
 * respondiera 500, porque `PrecioService` recibe un `Tenant` tipado y le llegaba null.
 *
 * La lección es la de siempre: una invariante que solo vive en el código de la aplicación
 * es una convención; en la base de datos es una garantía. Se pone donde de verdad se
 * cumple.
 *
 * Los usuarios huérfanos que ya existan **no se borran**: se les crea su negocio. Un
 * usuario que entró alguna vez es alguien que probó el producto, y perder su cuenta por una
 * corrección nuestra sería empezar la relación cobrándole el error.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('users')->whereNull('tenant_id')->get() as $usuario) {
            $nombre = $this->nombreDeNegocio($usuario);

            $tenantId = DB::table('tenants')->insertGetId([
                'name' => $nombre,
                'slug' => $this->slugLibre($nombre),
                'rate_source' => 'bcv',
                'rounding' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('users')->where('id', $usuario->id)->update(['tenant_id' => $tenantId]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->change();
        });
    }

    /** Un nombre provisional razonable: el usuario lo renombra en cuanto entre. */
    private function nombreDeNegocio(object $usuario): string
    {
        $base = trim((string) ($usuario->name ?? ''));

        if ($base === '') {
            $base = Str::before((string) $usuario->email, '@');
        }

        return 'Negocio de '.Str::title($base);
    }

    private function slugLibre(string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'negocio';
        $slug = $base;
        $i = 2;

        while (DB::table('tenants')->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
};
