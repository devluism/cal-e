<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PrecioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El motor de precios: lo único que este producto tiene que hacer bien.
 *
 * Norte existe porque el emprendedor se descapitaliza vendiendo al costo de ayer. Si el
 * cálculo se equivoca, no le estamos resolviendo el problema: se lo estamos automatizando.
 * Por eso el caso que más se cuida acá es el del **costo declarado en bolívares**, que es
 * donde está la trampa: unos bolívares de hace un mes no valen lo mismo hoy, y tratarlos
 * como si valieran es reproducir adentro el error que el usuario comete afuera.
 */
class PrecioVivoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $negocio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = Tenant::create([
            'name' => 'Bodega La Esquina',
            'slug' => 'bodega-la-esquina',
            'rate_source' => Tenant::TASA_BCV,
        ]);

        $this->actingAs(User::create([
            'tenant_id' => $this->negocio->id,
            'name' => 'María',
            'email' => 'maria@ejemplo.com',
            'password' => 'secreto123',
            'active' => true,
        ]));
    }

    private function precios(): PrecioService
    {
        return app(PrecioService::class);
    }

    private function producto(array $atributos): Product
    {
        return Product::create([
            'tenant_id' => $this->negocio->id,
            'name' => 'Producto',
            'margin' => 30,
            ...$atributos,
        ]);
    }

    public function test_un_costo_en_dolares_se_recalcula_con_la_tasa_de_hoy(): void
    {
        ExchangeRate::create(['price' => 40, 'source' => ExchangeRate::BCV]);

        $producto = $this->producto(['cost' => 2, 'cost_currency' => Product::USD, 'margin' => 50]);

        $hoy = $this->precios()->calcular($producto, $this->precios()->tasaDe($this->negocio));

        // 2 $ + 50% = 3 $; a 40 Bs/$ son 120 Bs.
        $this->assertSame(3.0, $hoy['precio_usd']);
        $this->assertSame(120.0, $hoy['precio_bs']);
    }

    public function test_el_precio_en_bolivares_sube_solo_cuando_sube_la_tasa(): void
    {
        ExchangeRate::create(['price' => 40, 'source' => ExchangeRate::BCV]);
        $producto = $this->producto(['cost' => 2, 'cost_currency' => Product::USD, 'margin' => 50]);

        $antes = $this->precios()->calcular($producto, $this->precios()->tasaDe($this->negocio));

        ExchangeRate::create(['price' => 60, 'source' => ExchangeRate::BCV]);
        $despues = $this->precios()->calcular($producto, $this->precios()->tasaDe($this->negocio));

        // Es la promesa entera del producto: el usuario no tocó nada y su precio ya está al día.
        $this->assertSame(120.0, $antes['precio_bs']);
        $this->assertSame(180.0, $despues['precio_bs']);
        // Y en dólares gana lo mismo: el margen no se erosionó.
        $this->assertSame($antes['precio_usd'], $despues['precio_usd']);
    }

    public function test_un_costo_en_bolivares_se_ancla_con_la_tasa_del_dia_en_que_se_declaro(): void
    {
        // La bodeguera pagó 80 Bs por la harina cuando el dólar estaba a 40: costó 2 $.
        $producto = $this->producto([
            'cost' => 80,
            'cost_currency' => Product::VES,
            'cost_rate' => 40,
            'margin' => 50,
        ]);

        $this->assertSame(2.0, (float) $producto->cost_usd);

        // Hoy el dólar está a 60. Reponer esa harina le cuesta 120 Bs, no 80.
        ExchangeRate::create(['price' => 60, 'source' => ExchangeRate::BCV]);

        $hoy = $this->precios()->calcular($producto, $this->precios()->tasaDe($this->negocio));

        // Sin anclar, el cálculo daría 80 × 1,5 = 120 Bs y estaría vendiendo a pérdida:
        // recuperaría 2 $ de los 3 que vale su mercancía. Anclado da 180 Bs.
        $this->assertSame(3.0, $hoy['precio_usd']);
        $this->assertSame(180.0, $hoy['precio_bs']);
    }

    public function test_un_costo_en_bolivares_sin_tasa_de_referencia_no_inventa_un_precio(): void
    {
        ExchangeRate::create(['price' => 60, 'source' => ExchangeRate::BCV]);

        $producto = $this->producto(['cost' => 80, 'cost_currency' => Product::VES, 'cost_rate' => null]);

        // Sin la tasa a la que se declaró, esos bolívares no significan nada. Se devuelve
        // cero para que se note en pantalla, en vez de mostrar una cifra verosímil e
        // incorrecta — que es peor, porque el usuario le creería.
        $this->assertSame(0.0, $this->precios()->calcular($producto, 60)['precio_bs']);
    }

    public function test_el_negocio_puede_usar_su_propia_tasa(): void
    {
        ExchangeRate::create(['price' => 40, 'source' => ExchangeRate::BCV]);

        $this->negocio->update(['rate_source' => Tenant::TASA_PROPIA, 'custom_rate' => 55]);

        // Mucha gente vende a una tasa distinta de la oficial. Sin esta opción seguiría
        // ajustando a mano, que es el trabajo que vinimos a quitarle.
        $this->assertSame(55.0, $this->precios()->tasaDe($this->negocio->fresh()));
    }

    public function test_el_redondeo_va_siempre_hacia_arriba(): void
    {
        $precios = $this->precios();

        // 187,43 Bs no se puede cobrar: nadie tiene ese vuelto.
        $this->assertSame(190.0, $precios->redondear(187.43, 10));
        $this->assertSame(188.0, $precios->redondear(187.43, 1));
        $this->assertSame(187.5, $precios->redondear(187.43, 0.5));

        // Hacia abajo se estaría comiendo el margen que el producto existe para proteger.
        $this->assertGreaterThanOrEqual(187.43, $precios->redondear(187.43, 5));

        // Sin redondeo configurado no se toca más allá de los dos decimales.
        $this->assertSame(187.43, $precios->redondear(187.43, 0));
    }

    public function test_sin_tasa_cargada_no_se_inventa_ninguna(): void
    {
        // Un número inventado acá se convierte en un precio mal cobrado en el mostrador.
        $this->assertSame(0.0, $this->precios()->tasaDe($this->negocio));
    }

    public function test_un_negocio_no_ve_los_productos_de_otro(): void
    {
        $this->producto(['cost' => 1, 'cost_currency' => Product::USD]);

        $otro = Tenant::create(['name' => 'Otra Bodega', 'slug' => 'otra-bodega']);
        Product::create([
            'tenant_id' => $otro->id,
            'name' => 'Producto ajeno',
            'cost' => 5,
            'cost_currency' => Product::USD,
            'margin' => 10,
        ]);

        // El scope global es lo único que separa los datos de un negocio de los de otro:
        // un `Product::all()` sin filtrar le mostraría a la bodeguera los costos de la
        // competencia.
        $this->assertSame(1, Product::count());
        $this->assertSame(2, Product::sinNegocio()->count());
    }

    public function test_el_producto_nuevo_hereda_el_negocio_de_la_sesion(): void
    {
        // Sin esto quedaría una fila huérfana que después no aparece en ninguna parte.
        $producto = Product::create(['name' => 'Sin negocio explícito', 'cost' => 1, 'margin' => 10]);

        $this->assertSame($this->negocio->id, $producto->tenant_id);
    }
}
