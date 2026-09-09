<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SuscripcionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El catálogo: cargar, editar y borrar productos.
 *
 * Dos cosas se cuidan acá. La primera es el **anclaje del costo**: si el costo viene en
 * bolívares, la tasa de referencia es obligatoria, porque sin ella esos bolívares no
 * significan nada y el precio saldría en cero — y un precio en cero es peor que un error,
 * porque se puede cobrar.
 *
 * La segunda es la promesa que hace `RequiereSuscripcion`: **suspender no es echar al
 * cliente**. Sigue viendo su catálogo —lo cargó él— y pierde el precio calculado, que es lo
 * que paga. Si esa distinción se rompe, el módulo empieza a castigar a quien se atrasó un
 * día con el Pago Móvil.
 */
class CatalogoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Tenant $negocio;

    protected function setUp(): void
    {
        parent::setUp();

        ExchangeRate::create(['price' => 100, 'source' => ExchangeRate::BCV]);

        $this->usuario = User::factory()->create();
        $this->negocio = $this->usuario->tenant;
        $this->actingAs($this->usuario);
    }

    private function suspender(): void
    {
        app(SuscripcionService::class)->para($this->negocio)
            ->forceFill(['trial_ends_at' => now()->subMonth(), 'status' => Subscription::SUSPENDIDA])
            ->save();
    }

    public function test_se_carga_un_producto_con_costo_en_dolares(): void
    {
        $this->post(route('productos.store'), [
            'name' => 'Harina de maíz',
            'cost' => 1.10,
            'cost_currency' => Product::USD,
            'margin' => 30,
        ])->assertRedirect();

        $producto = Product::sole();

        $this->assertSame('Harina de maíz', $producto->name);
        // Un costo en dólares ya está anclado: guardarle una tasa sería dejar un dato que el
        // cálculo ignora y que confundiría a quien lea la fila.
        $this->assertNull($producto->cost_rate);
    }

    public function test_un_costo_en_bolivares_exige_la_tasa_de_referencia(): void
    {
        // Sin ella el precio saldría en cero, y un precio en cero se puede cobrar.
        $this->post(route('productos.store'), [
            'name' => 'Jabón',
            'cost' => 250,
            'cost_currency' => Product::VES,
            'margin' => 40,
        ])->assertSessionHasErrors('cost_rate');

        $this->assertSame(0, Product::count());
    }

    public function test_el_costo_en_bolivares_queda_anclado_a_dolares(): void
    {
        $this->post(route('productos.store'), [
            'name' => 'Jabón',
            'cost' => 250,
            'cost_currency' => Product::VES,
            'cost_rate' => 100,
            'margin' => 40,
        ])->assertRedirect();

        // 250 Bs a 100 Bs/$ son 2,50 $. De ahí en adelante el precio sube con la tasa.
        $this->assertSame(2.5, (float) Product::sole()->cost_usd);
    }

    public function test_el_producto_nace_en_el_negocio_de_quien_lo_carga(): void
    {
        $this->post(route('productos.store'), [
            'name' => 'Café',
            'cost' => 2,
            'cost_currency' => Product::USD,
            'margin' => 25,
        ]);

        // Lo pone el scope global, no el controlador: una fila sin negocio no aparecería
        // después en ninguna parte.
        $this->assertSame($this->negocio->id, Product::sole()->tenant_id);
    }

    public function test_pasar_el_costo_a_dolares_limpia_la_tasa_vieja(): void
    {
        $producto = Product::create([
            'tenant_id' => $this->negocio->id,
            'name' => 'Jabón',
            'cost' => 250,
            'cost_currency' => Product::VES,
            'cost_rate' => 100,
            'margin' => 40,
        ]);

        $this->put(route('productos.update', $producto->id), [
            'name' => 'Jabón',
            'cost' => 2.5,
            'cost_currency' => Product::USD,
            'cost_rate' => 100,   // el formulario podría seguir mandándola
            'margin' => 40,
        ])->assertRedirect();

        $this->assertNull($producto->fresh()->cost_rate);
    }

    public function test_se_acepta_una_tasa_con_la_precision_que_publica_el_bcv(): void
    {
        /*
         * Regresión: el formulario prellenaba la tasa del día (814,6908 — cuatro decimales,
         * como la publica el BCV) en un campo con `step="0.01"`, y el navegador la rechazaba
         * **en silencio**, con un mensaje suyo en inglés. El usuario llenaba todo, tocaba
         * guardar y no pasaba nada.
         *
         * Este test fija la expectativa del lado del servidor: la precisión que el sistema
         * guarda es la que el alta tiene que aceptar.
         */
        $this->post(route('productos.store'), [
            'name' => 'Papel higiénico',
            'cost' => 1200,
            'cost_currency' => Product::VES,
            'cost_rate' => 814.6908,
            'margin' => 35,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(814.6908, (float) Product::sole()->cost_rate);
    }

    public function test_un_margen_absurdo_se_rechaza(): void
    {
        // El error típico: teclear el precio en bolívares donde va el por ciento.
        $this->post(route('productos.store'), [
            'name' => 'Café',
            'cost' => 2,
            'cost_currency' => Product::USD,
            'margin' => 5000,
        ])->assertSessionHasErrors('margin');
    }

    public function test_la_lista_trae_el_precio_calculado(): void
    {
        Product::create([
            'tenant_id' => $this->negocio->id,
            'name' => 'Café',
            'cost' => 2,
            'cost_currency' => Product::USD,
            'margin' => 50,
        ]);

        $this->getJson(route('productos.list'))
            ->assertOk()
            ->assertJsonPath('con_acceso', true)
            // 2 $ + 50% = 3 $; a 100 Bs/$ son 300 Bs.
            ->assertJsonPath('productos.0.precio_usd', fn ($v) => (float) $v === 3.0)
            ->assertJsonPath('productos.0.precio_bs', fn ($v) => (float) $v === 300.0);
    }

    public function test_un_negocio_no_ve_el_catalogo_de_otro(): void
    {
        Product::create([
            'tenant_id' => $this->negocio->id,
            'name' => 'Mío',
            'cost' => 1,
            'cost_currency' => Product::USD,
            'margin' => 10,
        ]);

        $ajeno = User::factory()->create();
        Product::create([
            'tenant_id' => $ajeno->tenant_id,
            'name' => 'Ajeno',
            'cost' => 9,
            'cost_currency' => Product::USD,
            'margin' => 10,
        ]);

        $this->getJson(route('productos.list'))
            ->assertOk()
            ->assertJsonCount(1, 'productos')
            ->assertJsonPath('productos.0.name', 'Mío');
    }

    public function test_no_se_puede_editar_el_producto_de_otro_negocio(): void
    {
        $ajeno = User::factory()->create();
        $producto = Product::create([
            'tenant_id' => $ajeno->tenant_id,
            'name' => 'Ajeno',
            'cost' => 9,
            'cost_currency' => Product::USD,
            'margin' => 10,
        ]);

        // El scope global hace que ni siquiera exista para este usuario: 404, no 403.
        $this->put(route('productos.update', $producto->id), [
            'name' => 'Robado',
            'cost' => 1,
            'cost_currency' => Product::USD,
            'margin' => 10,
        ])->assertNotFound();

        $this->assertSame('Ajeno', $producto->fresh()->name);
    }

    public function test_se_quita_un_producto_de_la_lista(): void
    {
        $producto = Product::create([
            'tenant_id' => $this->negocio->id,
            'name' => 'Café',
            'cost' => 2,
            'cost_currency' => Product::USD,
            'margin' => 25,
        ]);

        $this->delete(route('productos.destroy', $producto->id))->assertRedirect();

        $this->assertSame(0, Product::count());
    }

    // ---------------------------------------------------- suscripción vencida

    public function test_un_suspendido_sigue_viendo_su_catalogo(): void
    {
        Product::create([
            'tenant_id' => $this->negocio->id,
            'name' => 'Café',
            'cost' => 2,
            'cost_currency' => Product::USD,
            'margin' => 50,
        ]);

        $this->suspender();

        // Sus costos y márgenes los cargó él: quitárselos de la vista al vencer sería la
        // forma más segura de que no vuelva.
        $this->get(route('productos.index'))->assertOk();

        $respuesta = $this->getJson(route('productos.list'))->assertOk();

        $this->assertSame('Café', $respuesta->json('productos.0.name'));
        $this->assertSame(2.0, (float) $respuesta->json('productos.0.cost'));
    }

    public function test_un_suspendido_no_recibe_el_precio_calculado(): void
    {
        Product::create([
            'tenant_id' => $this->negocio->id,
            'name' => 'Café',
            'cost' => 2,
            'cost_currency' => Product::USD,
            'margin' => 50,
        ]);

        $this->suspender();

        $respuesta = $this->getJson(route('productos.list'))->assertOk();

        // El precio no viaja al navegador. Calcularlo y esconderlo con CSS no sería
        // esconderlo: basta abrir las herramientas del navegador.
        $this->assertFalse($respuesta->json('con_acceso'));
        $this->assertArrayNotHasKey('precio_bs', $respuesta->json('productos.0'));
    }

    public function test_un_suspendido_no_puede_modificar_el_catalogo(): void
    {
        $this->suspender();

        $this->post(route('productos.store'), [
            'name' => 'Café',
            'cost' => 2,
            'cost_currency' => Product::USD,
            'margin' => 25,
        ])->assertRedirect(route('suscripcion'));

        $this->assertSame(0, Product::count());
    }
}
