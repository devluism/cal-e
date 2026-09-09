<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La prop `tasa` que viaja en cada página, compartida por `HandleInertiaRequests`.
 *
 * Regresión: cuando la tabla `exchange_rates` está vacía —una instalación recién migrada,
 * antes de que corra el primer `tasa:sync`— el frontend armaba el aviso de «Esta tasa se
 * actualizó [nada]. Si el BCV...» con un hueco en la frase, porque `actualizada` salía null
 * y el código solo distinguía «es de hoy» de «no es de hoy», no «nunca hubo ninguna».
 */
class TasaCompartidaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_ninguna_tasa_cargada_lo_dice_explicito(): void
    {
        $usuario = User::factory()->create();

        // Sin esto el aviso de la interfaz queda armado con un hueco: «se actualizó .».
        $this->actingAs($usuario)
            ->get(route('panel'))
            ->assertInertia(fn ($page) => $page
                ->where('tasa.nunca_cargada', true)
                ->where('tasa.es_de_hoy', false)
                // json_decode() devuelve `int(0)` para "0" aunque PHP lo haya calculado
                // como float; se compara el valor y no el tipo exacto que trae el JSON.
                ->where('tasa.valor', fn ($v) => (float) $v === 0.0));
    }

    public function test_con_la_tasa_de_hoy_cargada_no_avisa_de_nada(): void
    {
        $usuario = User::factory()->create();
        ExchangeRate::create(['price' => 100, 'source' => ExchangeRate::BCV]);

        $this->actingAs($usuario)
            ->get(route('panel'))
            ->assertInertia(fn ($page) => $page
                ->where('tasa.nunca_cargada', false)
                ->where('tasa.es_de_hoy', true));
    }

    public function test_con_una_tasa_vieja_avisa_que_no_es_de_hoy(): void
    {
        $usuario = User::factory()->create();

        // `created_at` no está en el `$fillable`: Eloquent lo gestiona solo y `create()` lo
        // habría sobrescrito con "ahora" de todas formas. `forceFill` es lo que permite
        // simular una tasa vieja sin esperar un día real en la prueba.
        $tasa = new ExchangeRate(['price' => 100, 'source' => ExchangeRate::BCV]);
        $tasa->forceFill(['created_at' => now()->subDay()])->save();

        // Distinto del caso "nunca cargada": acá sí hay una fecha que mostrar.
        $this->actingAs($usuario)
            ->get(route('panel'))
            ->assertInertia(fn ($page) => $page
                ->where('tasa.nunca_cargada', false)
                ->where('tasa.es_de_hoy', false)
                ->whereNot('tasa.actualizada', null));
    }
}
