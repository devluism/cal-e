<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SuscripcionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Suscripción, cobro y control de acceso.
 *
 * Lo que se protege acá es que la máquina de estados **no dependa de un cron**: el acceso se
 * recalcula al leer, así que una suscripción no se queda diciendo «activa» tres semanas
 * después de vencer porque el programador de tareas no corrió.
 *
 * Y que suspender **no sea echar al cliente**: su catálogo sigue ahí. Lo que pierde es la
 * parte viva, que es lo que paga.
 */
class SuscripcionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $negocio;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        ExchangeRate::create(['price' => 40, 'source' => ExchangeRate::BCV]);

        $this->negocio = Tenant::create(['name' => 'Bodega', 'slug' => 'bodega']);
        $this->usuario = User::create([
            'tenant_id' => $this->negocio->id,
            'name' => 'María',
            'email' => 'maria@ejemplo.com',
            'password' => 'secreto123',
            'active' => true,
        ]);
    }

    private function servicio(): SuscripcionService
    {
        return app(SuscripcionService::class);
    }

    private function plan(): Plan
    {
        return Plan::where('code', 'mensual')->sole();
    }

    public function test_un_negocio_nuevo_arranca_con_prueba_gratis(): void
    {
        $suscripcion = $this->servicio()->para($this->negocio);

        $this->assertSame(Subscription::PRUEBA, $suscripcion->status);
        $this->assertTrue($suscripcion->da_acceso);
        $this->assertSame(Subscription::DIAS_PRUEBA, $suscripcion->dias_restantes);
    }

    public function test_el_estado_se_recalcula_al_leer_sin_depender_de_un_cron(): void
    {
        $suscripcion = $this->servicio()->para($this->negocio);

        // Se fuerza el vencimiento sin tocar el estado, como si hubiera pasado el tiempo.
        $suscripcion->forceFill(['trial_ends_at' => now()->subDay()])->save();

        // Sin recalcular al leer, seguiría diciendo «prueba» y regalaría el producto.
        $this->assertSame(Subscription::SUSPENDIDA, $this->servicio()->para($this->negocio)->status);
    }

    public function test_hay_gracia_despues_de_vencer(): void
    {
        $servicio = $this->servicio();
        $suscripcion = $servicio->para($this->negocio);

        // Venció ayer: el público paga cuando puede, cortarle a medianoche es perderlo.
        $suscripcion->forceFill(['ends_at' => now()->subDay(), 'status' => Subscription::ACTIVA])->save();
        $this->assertSame(Subscription::GRACIA, $servicio->para($this->negocio)->status);
        $this->assertTrue($servicio->para($this->negocio)->da_acceso);

        // Pasada la gracia sí se suspende.
        $suscripcion->forceFill(['ends_at' => now()->subDays(Subscription::DIAS_GRACIA + 1)])->save();
        $this->assertSame(Subscription::SUSPENDIDA, $servicio->para($this->negocio)->status);
    }

    public function test_confirmar_un_pago_activa_la_suscripcion(): void
    {
        $servicio = $this->servicio();
        $servicio->para($this->negocio);

        $pago = $servicio->reportar($this->negocio, $this->plan(), ['reference' => '001122']);

        // Reportar NO da acceso: a 3-5 $/mes es más barato cotejar que perseguir el fraude.
        $this->assertSame(Payment::REPORTADO, $pago->status);
        $this->assertSame(Subscription::PRUEBA, $servicio->para($this->negocio)->status);

        $suscripcion = $servicio->confirmar($pago, $this->usuario->id);

        $this->assertSame(Subscription::ACTIVA, $suscripcion->status);
        $this->assertSame(30, (int) now()->startOfDay()->diffInDays($suscripcion->ends_at->startOfDay()));
    }

    public function test_pagar_antes_de_vencer_no_pierde_los_dias_que_quedaban(): void
    {
        $servicio = $this->servicio();
        $suscripcion = $servicio->para($this->negocio);

        // Le quedan 10 días y paga hoy, por puntual.
        $suscripcion->forceFill(['status' => Subscription::ACTIVA, 'ends_at' => now()->addDays(10)])->save();

        $pago = $servicio->reportar($this->negocio, $this->plan(), ['reference' => '445566']);
        $renovada = $servicio->confirmar($pago, $this->usuario->id);

        // Se extiende desde el vencimiento, no desde hoy: ser puntual no puede costarle días.
        $this->assertSame(40, (int) now()->startOfDay()->diffInDays($renovada->ends_at->startOfDay()));
    }

    public function test_pagar_ya_vencido_cuenta_desde_hoy(): void
    {
        $servicio = $this->servicio();
        $suscripcion = $servicio->para($this->negocio);

        $suscripcion->forceFill(['status' => Subscription::ACTIVA, 'ends_at' => now()->subDays(20)])->save();

        $pago = $servicio->reportar($this->negocio, $this->plan(), ['reference' => '778899']);
        $renovada = $servicio->confirmar($pago, $this->usuario->id);

        // Regalarle los 20 días que estuvo suspendido sería cobrarle por días que no usó.
        $this->assertSame(30, (int) now()->startOfDay()->diffInDays($renovada->ends_at->startOfDay()));
    }

    public function test_el_monto_queda_congelado_en_las_dos_monedas(): void
    {
        $pago = $this->servicio()->reportar($this->negocio, $this->plan(), ['reference' => '112233']);

        // 4 $ a 40 Bs/$ son 160 Bs.
        $this->assertSame(4.0, (float) $pago->amount_usd);
        $this->assertSame(160.0, (float) $pago->amount_bs);
        $this->assertSame(40.0, (float) $pago->exchange_rate);

        // La tasa se dispara al día siguiente: el pago tiene que seguir diciendo lo mismo,
        // o el cliente diría —con razón— que pagó lo que le pidieron.
        ExchangeRate::create(['price' => 90, 'source' => ExchangeRate::BCV]);

        $this->assertSame(160.0, (float) $pago->fresh()->amount_bs);
    }

    public function test_una_referencia_no_se_puede_reportar_dos_veces(): void
    {
        $servicio = $this->servicio();
        $servicio->reportar($this->negocio, $this->plan(), ['reference' => '999000']);

        // Es lo que impide estirar la suscripción con el mismo comprobante.
        $this->expectException(RuntimeException::class);

        $servicio->reportar($this->negocio, $this->plan(), ['reference' => '999000']);
    }

    public function test_sin_tasa_cargada_no_se_puede_reportar_un_pago(): void
    {
        ExchangeRate::query()->delete();

        // Sin tasa el monto en bolívares saldría en cero y el cliente no sabría cuánto pagar.
        $this->expectException(RuntimeException::class);

        $this->servicio()->reportar($this->negocio, $this->plan(), ['reference' => '303030']);
    }

    public function test_un_pago_confirmado_no_se_confirma_dos_veces(): void
    {
        $servicio = $this->servicio();
        $pago = $servicio->reportar($this->negocio, $this->plan(), ['reference' => '404040']);
        $servicio->confirmar($pago, $this->usuario->id);

        // Confirmarlo de nuevo regalaría otro mes.
        $this->expectException(RuntimeException::class);

        $servicio->confirmar($pago->fresh(), $this->usuario->id);
    }

    public function test_suspendido_no_ve_sus_precios_pero_si_puede_pagar(): void
    {
        $suscripcion = $this->servicio()->para($this->negocio);
        $suscripcion->forceFill(['trial_ends_at' => now()->subMonth()])->save();

        // El valor vivo está protegido…
        $this->actingAs($this->usuario)
            ->get('/panel')
            ->assertRedirect(route('suscripcion'));

        // …pero la pantalla de pago NO, o el cliente no tendría cómo renovar.
        $this->actingAs($this->usuario)->get('/suscripcion')->assertOk();
    }

    public function test_un_suspendido_conserva_su_catalogo(): void
    {
        Product::create([
            'tenant_id' => $this->negocio->id,
            'name' => 'Harina',
            'cost' => 1,
            'cost_currency' => 'USD',
            'margin' => 30,
        ]);

        $suscripcion = $this->servicio()->para($this->negocio);
        $suscripcion->forceFill(['trial_ends_at' => now()->subMonth()])->save();
        $this->servicio()->para($this->negocio);

        // Suspender no es borrar: sus costos y márgenes los cargó él y son suyos. Perderlos
        // sería garantizar que no vuelva.
        $this->assertSame(1, Product::delNegocio($this->negocio->id)->count());
    }

    public function test_el_negocio_solo_ve_sus_propios_pagos(): void
    {
        $this->servicio()->reportar($this->negocio, $this->plan(), ['reference' => '505050']);

        $otro = Tenant::create(['name' => 'Otra', 'slug' => 'otra']);
        $this->servicio()->reportar($otro, $this->plan(), ['reference' => '606060']);

        $this->actingAs($this->usuario);

        $this->assertSame(1, Payment::count());
        $this->assertSame(2, Payment::sinNegocio()->count());
    }
}
