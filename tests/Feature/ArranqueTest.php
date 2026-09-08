<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Que el proyecto arranque de cero: `migrate:fresh --seed` y las factories.
 *
 * Esto existe por un fallo concreto. Se endureció `users.tenant_id` a NOT NULL para imponer
 * la invariante «no existe un usuario sin negocio», pero no se revisó **quién más escribe
 * usuarios**: la factory y el seeder por defecto de Laravel los creaban sueltos, así que
 * `migrate:fresh --seed` —lo primero que corre quien clona el repositorio— reventaba.
 *
 * Y el motivo de que pasara desapercibido es la lección que este archivo protege: **todos
 * los tests creaban sus usuarios a mano**, así que la factory nunca se ejercitaba y las
 * pruebas seguían verdes con el arranque roto. Un camino que nadie prueba es un camino que
 * está roto y todavía no lo sabes.
 */
class ArranqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_factory_produce_un_usuario_que_la_base_acepta(): void
    {
        $usuario = User::factory()->create();

        // Si la factory no le pone negocio, la base lo rechaza y esto ni llega acá.
        $this->assertNotNull($usuario->tenant_id);
        $this->assertNotNull($usuario->tenant);
        $this->assertTrue($usuario->active);
    }

    public function test_cada_usuario_de_factory_estrena_su_propio_negocio(): void
    {
        $uno = User::factory()->create();
        $otro = User::factory()->create();

        // En la V1 una cuenta es un negocio.
        $this->assertNotSame($uno->tenant_id, $otro->tenant_id);
    }

    public function test_se_pueden_crear_varios_usuarios_en_el_mismo_negocio(): void
    {
        $negocio = Tenant::factory()->create();

        $uno = User::factory()->create(['tenant_id' => $negocio->id]);
        $otro = User::factory()->create(['tenant_id' => $negocio->id]);

        // El `tenant_id` explícito gana: es lo que van a necesitar los empleados en la V2.
        $this->assertSame($negocio->id, $uno->tenant_id);
        $this->assertSame($negocio->id, $otro->tenant_id);
        $this->assertSame(1, Tenant::count());
    }

    public function test_el_seeder_deja_el_proyecto_usable(): void
    {
        $this->seed(DatabaseSeeder::class);

        $usuario = User::where('email', 'admin@mail.com')->sole();

        $this->assertNotNull($usuario->tenant_id);
        $this->assertSame('Bodega Demo', $usuario->tenant->name);

        // Y con catálogo de ejemplo, para que quien clona vea el producto funcionando.
        $this->assertGreaterThan(0, Product::delNegocio($usuario->tenant_id)->count());
    }

    public function test_el_catalogo_de_ejemplo_cubre_los_dos_tipos_de_costo(): void
    {
        $this->seed(DatabaseSeeder::class);

        $negocio = Tenant::where('slug', 'bodega-demo')->sole();
        $productos = Product::delNegocio($negocio->id)->get();

        // Sin un costo declarado en bolívares, quien clona el repositorio no ve funcionando
        // la pieza central del producto: el anclaje del costo con su tasa de referencia.
        $enBolivares = $productos->firstWhere('cost_currency', Product::VES);

        $this->assertNotNull($enBolivares, 'Falta un producto con costo en bolívares.');
        $this->assertNotNull($enBolivares->cost_rate, 'Un costo en Bs sin tasa no significa nada.');
        $this->assertGreaterThan(0, (float) $enBolivares->cost_usd);
    }

    public function test_correr_el_seeder_dos_veces_no_duplica_nada(): void
    {
        $this->seed(DatabaseSeeder::class);
        $productos = Product::sinNegocio()->count();

        // Volver a sembrar es lo que hace cualquiera que retoma el proyecto.
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@mail.com')->count());
        $this->assertSame(1, Tenant::where('slug', 'bodega-demo')->count());
        $this->assertSame($productos, Product::sinNegocio()->count());
    }
}
