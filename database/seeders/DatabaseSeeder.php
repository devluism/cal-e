<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Sin un plan y una cuenta de cobro no se puede cobrar: no son datos de demostración.
        $this->call(PlanSeeder::class);

        /*
         * Negocio y usuario de prueba, creados **juntos**.
         *
         * `users.tenant_id` es NOT NULL: en Norte no existe un usuario sin negocio. Crear el
         * usuario suelto —como hacía el seeder por defecto de Laravel— revienta la
         * migración con seeders, que es justo lo primero que corre alguien que clona el
         * repositorio.
         */
        $negocio = Tenant::firstOrCreate(
            ['slug' => 'bodega-demo'],
            ['name' => 'Bodega Demo', 'rate_source' => Tenant::TASA_BCV, 'rounding' => 1],
        );

        $usuario = User::firstOrCreate(
            ['email' => 'admin@mail.com'],
            [
                'tenant_id' => $negocio->id,
                'name' => 'Admin',
                'password' => Hash::make('1234'),
                'role' => 'owner',
                'active' => true,
                'email_verified_at' => now(),
            ],
        );

        if (! config('app.debug')) {
            return;
        }

        /*
         * Catálogo de ejemplo, solo en desarrollo.
         *
         * Cubre los dos casos que importan del modelo de costo: el declarado en dólares y el
         * declarado en bolívares con su tasa de referencia. Sin el segundo, quien clona el
         * repositorio no ve funcionando la pieza central del producto.
         */
        $ejemplos = [
            ['Harina de maíz 1 kg', 'Víveres', 1.10, Product::USD, null, 35],
            ['Aceite 1 L', 'Víveres', 3.40, Product::USD, null, 28],
            ['Café molido 250 g', 'Víveres', 2.20, Product::USD, null, 40],
            // Costo declarado en bolívares a una tasa vieja: se ancla y se re-infla solo.
            ['Jabón de baño', 'Limpieza', 250.00, Product::VES, 300.00, 45],
            ['Detergente 1 kg', 'Limpieza', 480.00, Product::VES, 300.00, 38],
        ];

        foreach ($ejemplos as $i => [$nombre, $categoria, $costo, $moneda, $tasaCosto, $margen]) {
            Product::updateOrCreate(
                ['tenant_id' => $negocio->id, 'name' => $nombre],
                [
                    'category' => $categoria,
                    'cost' => $costo,
                    'cost_currency' => $moneda,
                    'cost_rate' => $tasaCosto,
                    'margin' => $margen,
                    'active' => true,
                    'position' => $i,
                ],
            );
        }

        $this->command?->info("Entra con {$usuario->email} / 1234");
    }
}
