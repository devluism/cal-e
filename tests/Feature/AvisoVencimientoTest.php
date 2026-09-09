<?php

namespace Tests\Feature;

use App\Mail\SuscripcionPorVencer;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * El aviso de vencimiento: `suscripcion:avisar`.
 *
 * Antes de esto, un negocio solo se enteraba de que perdió acceso al precio calculado si
 * volvía a entrar a la app — y para entonces pudo haber mandado una lista con precios
 * congelados a un cliente. Lo que sostiene que el aviso valga algo es que llegue una sola vez
 * por hito: un correo diario diciendo lo mismo se aprende a ignorar en una semana.
 */
class AvisoVencimientoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $negocio;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = Tenant::create(['name' => 'Bodega', 'slug' => 'bodega']);
        $this->usuario = User::create([
            'tenant_id' => $this->negocio->id,
            'name' => 'María',
            'email' => 'maria@ejemplo.com',
            'password' => 'secreto123',
            'active' => true,
        ]);

        Mail::fake();
    }

    private function suscripcion(array $overrides = []): Subscription
    {
        return Subscription::create([
            'tenant_id' => $this->negocio->id,
            'status' => Subscription::PRUEBA,
            'trial_ends_at' => now()->addDays(Subscription::DIAS_PRUEBA),
            ...$overrides,
        ]);
    }

    public function test_avisa_tres_dias_antes_de_que_venza_la_prueba(): void
    {
        $this->suscripcion(['trial_ends_at' => now()->addDays(3)]);

        $this->artisan('suscripcion:avisar');

        Mail::assertSent(SuscripcionPorVencer::class, fn ($correo) => $correo->hasTo($this->usuario->email)
            && $correo->hito === '3');
    }

    public function test_avisa_un_dia_antes(): void
    {
        $this->suscripcion(['trial_ends_at' => now()->addDay()]);

        $this->artisan('suscripcion:avisar');

        Mail::assertSent(SuscripcionPorVencer::class, fn ($correo) => $correo->hito === '1');
    }

    public function test_no_avisa_si_faltan_dias_que_no_son_un_hito(): void
    {
        $this->suscripcion(['trial_ends_at' => now()->addDays(5)]);

        $this->artisan('suscripcion:avisar');

        Mail::assertNothingSent();
    }

    public function test_avisa_una_sola_vez_por_hito_aunque_el_comando_corra_dos_veces(): void
    {
        $this->suscripcion(['trial_ends_at' => now()->addDay()]);

        $this->artisan('suscripcion:avisar');
        $this->artisan('suscripcion:avisar');

        Mail::assertSent(SuscripcionPorVencer::class, 1);
    }

    public function test_avisa_al_entrar_en_gracia(): void
    {
        // Ya venció el plan pagado, pero todavía está dentro de los días de gracia.
        $this->suscripcion(['status' => Subscription::ACTIVA, 'ends_at' => now()->subDay()]);

        $this->artisan('suscripcion:avisar');

        Mail::assertSent(SuscripcionPorVencer::class, fn ($correo) => $correo->hito === 'vencida');
    }

    public function test_no_repite_el_aviso_de_vencida_mientras_siga_suspendida(): void
    {
        $this->suscripcion([
            'status' => Subscription::SUSPENDIDA,
            'ends_at' => now()->subMonth(),
            'reminder_sent_for' => 'vencida',
        ]);

        $this->artisan('suscripcion:avisar');

        Mail::assertNothingSent();
    }

    public function test_el_aviso_se_reactiva_solo_en_el_siguiente_ciclo_de_pago(): void
    {
        // Ya se avisó "vencida" en el ciclo anterior; ahora hay un pago nuevo y la
        // suscripción quedó activa con 3 días para vencer otra vez.
        $this->suscripcion([
            'status' => Subscription::ACTIVA,
            'ends_at' => now()->addDays(3),
            'reminder_sent_for' => 'vencida',
        ]);

        $this->artisan('suscripcion:avisar');

        // Sin limpiar el campo a mano: el hito de hoy ('3') ya no coincide con el guardado.
        Mail::assertSent(SuscripcionPorVencer::class, fn ($correo) => $correo->hito === '3');
    }

    public function test_no_avisa_si_el_negocio_no_tiene_usuarios_activos(): void
    {
        $this->usuario->update(['active' => false]);
        $this->suscripcion(['trial_ends_at' => now()->addDay()]);

        $this->artisan('suscripcion:avisar');

        Mail::assertNothingSent();

        // Y no marca el hito como avisado: si mañana el usuario se reactiva, tiene que poder
        // recibirlo.
        $this->assertNull(Subscription::sole()->reminder_sent_for);
    }
}
