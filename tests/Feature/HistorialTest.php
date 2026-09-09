<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\PriceSnapshot;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\HistorialService;
use App\Services\SuscripcionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La foto diaria de precios y el historial que se arma con ellas.
 *
 * Lo que sostiene que el historial signifique algo: una foto por producto y por día (no
 * duplicados, no perdida al correr el comando dos veces), ninguna foto sin tasa real detrás
 * (un snapshot en cero mentiría igual que mostrarlo en pantalla), y el mismo aislamiento por
 * negocio que el resto del catálogo.
 */
class HistorialTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create();
        $this->actingAs($this->usuario);
    }

    private function producto(array $overrides = []): Product
    {
        return Product::create([
            'tenant_id' => $this->usuario->tenant_id,
            'name' => 'Café',
            'cost' => 2,
            'cost_currency' => Product::USD,
            'margin' => 50,
            ...$overrides,
        ]);
    }

    public function test_sin_tasa_cargada_no_se_guarda_ninguna_foto(): void
    {
        $this->producto();

        $fotografiados = app(HistorialService::class)->tomarFoto($this->usuario->tenant);

        // Un snapshot en 0,00 mentiría en el historial igual que mostrarlo en pantalla.
        $this->assertSame(0, $fotografiados);
        $this->assertSame(0, PriceSnapshot::count());
    }

    public function test_la_foto_guarda_el_precio_calculado_con_la_tasa_de_hoy(): void
    {
        ExchangeRate::create(['price' => 100, 'source' => ExchangeRate::BCV]);
        $producto = $this->producto();

        app(HistorialService::class)->tomarFoto($this->usuario->tenant);

        // 2 $ + 50% = 3 $; a 100 Bs/$ son 300 Bs.
        $foto = PriceSnapshot::sole();
        $this->assertSame($producto->id, $foto->product_id);
        $this->assertSame(3.0, (float) $foto->price_usd);
        $this->assertSame(300.0, (float) $foto->price_bs);
        $this->assertSame(100.0, (float) $foto->rate);
    }

    public function test_correr_la_foto_dos_veces_el_mismo_dia_actualiza_en_vez_de_duplicar(): void
    {
        ExchangeRate::create(['price' => 100, 'source' => ExchangeRate::BCV]);
        $this->producto();

        app(HistorialService::class)->tomarFoto($this->usuario->tenant);

        // La tasa subió y se vuelve a correr el mismo día: no puede quedar la vieja al lado
        // de la nueva, o el historial de hoy tendría dos precios.
        ExchangeRate::create(['price' => 120, 'source' => ExchangeRate::BCV]);
        app(HistorialService::class)->tomarFoto($this->usuario->tenant);

        $this->assertSame(1, PriceSnapshot::count());
        $this->assertSame(120.0, (float) PriceSnapshot::sole()->rate);
    }

    public function test_un_negocio_no_ve_el_historial_de_otro(): void
    {
        ExchangeRate::create(['price' => 100, 'source' => ExchangeRate::BCV]);

        $mio = $this->producto();

        $otroUsuario = User::factory()->create();
        $ajeno = Product::create([
            'tenant_id' => $otroUsuario->tenant_id,
            'name' => 'Ajeno',
            'cost' => 1,
            'cost_currency' => Product::USD,
            'margin' => 10,
        ]);

        app(HistorialService::class)->tomarFotoDeTodos();

        // `sinNegocio()` porque la comprobación es sobre lo que quedó escrito en la base,
        // no sobre lo que el scope global del usuario actual dejaría ver.
        $this->assertSame(1, PriceSnapshot::sinNegocio()->where('product_id', $mio->id)->count());
        $this->assertSame(1, PriceSnapshot::sinNegocio()->where('product_id', $ajeno->id)->count());

        // El endpoint solo puede ver el propio: el scope global hace que el ajeno ni exista.
        $this->getJson(route('productos.historial', $ajeno->id))->assertNotFound();
    }

    public function test_el_endpoint_devuelve_el_historial_ordenado(): void
    {
        ExchangeRate::create(['price' => 100, 'source' => ExchangeRate::BCV]);
        $producto = $this->producto();

        $servicio = app(HistorialService::class);
        $servicio->tomarFoto($this->usuario->tenant, now()->subDays(2));
        $servicio->tomarFoto($this->usuario->tenant, now()->subDay());
        $servicio->tomarFoto($this->usuario->tenant, now());

        $respuesta = $this->getJson(route('productos.historial', $producto->id))->assertOk();

        $this->assertCount(3, $respuesta->json('historial'));
        $this->assertSame(
            now()->subDays(2)->toDateString(),
            $respuesta->json('historial.0.fecha'),
        );
    }

    public function test_un_suspendido_no_puede_ver_el_historial(): void
    {
        ExchangeRate::create(['price' => 100, 'source' => ExchangeRate::BCV]);
        $producto = $this->producto();

        app(SuscripcionService::class)->para($this->usuario->tenant)
            ->forceFill(['trial_ends_at' => now()->subMonth(), 'status' => Subscription::SUSPENDIDA])
            ->save();

        // Es valor vivo, igual que el precio calculado en el panel: se paga.
        $this->getJson(route('productos.historial', $producto->id))->assertStatus(402);
    }
}
