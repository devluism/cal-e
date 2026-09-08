<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * La máquina de estados del acceso, y el registro de los pagos que la mueven.
 *
 * Está deliberadamente separada del riel de cobro. `avanzar()` no sabe si el dinero llegó
 * por Pago Móvil reportado a mano, por un webhook del banco o por Binance: solo sabe que un
 * pago se confirmó. Conectar una confirmación automática mañana es llamar a
 * `confirmar()` desde otro sitio — el control de acceso no se entera.
 *
 * Ver la migración `create_billing_tables` para el porqué de la gracia y de que "suspendida"
 * no signifique quedarse afuera.
 */
class SuscripcionService
{
    public function __construct(private PrecioService $precios) {}

    /**
     * La suscripción del negocio, creándole la prueba si es su primera vez.
     *
     * Se crea al vuelo y no en el registro para que un negocio que ya existía —o uno creado
     * por una migración de arreglo— también tenga la suya sin necesidad de un backfill.
     */
    public function para(Tenant $negocio): Subscription
    {
        $suscripcion = Subscription::firstOrCreate(
            ['tenant_id' => $negocio->id],
            [
                'status' => Subscription::PRUEBA,
                'trial_ends_at' => now()->addDays(Subscription::DIAS_PRUEBA),
            ]
        );

        return $this->avanzar($suscripcion);
    }

    /**
     * Pone el estado al día según la fecha. Es idempotente: se puede llamar en cada request.
     *
     * El estado no se guarda "congelado" al pagar y ya: el tiempo pasa solo, y una
     * suscripción que dice `activa` tres semanas después de vencer estaría regalando el
     * producto. Recalcularlo al leer es lo que hace que no haga falta un cron para que el
     * acceso sea correcto — el cron sirve para avisar, no para que la regla se cumpla.
     */
    public function avanzar(Subscription $suscripcion): Subscription
    {
        if (in_array($suscripcion->status, [Subscription::CANCELADA], true)) {
            return $suscripcion;
        }

        $estado = $this->estadoQueLeToca($suscripcion);

        if ($estado !== $suscripcion->status) {
            $suscripcion->update(['status' => $estado]);
        }

        return $suscripcion;
    }

    private function estadoQueLeToca(Subscription $suscripcion): string
    {
        $ahora = now();

        // Todavía de prueba y sin haber pagado nunca.
        if (! $suscripcion->ends_at) {
            return $suscripcion->trial_ends_at?->isFuture()
                ? Subscription::PRUEBA
                : Subscription::SUSPENDIDA;
        }

        if ($suscripcion->ends_at->isFuture()) {
            return Subscription::ACTIVA;
        }

        $finGracia = $suscripcion->ends_at->copy()->addDays(Subscription::DIAS_GRACIA);

        return $ahora->lte($finGracia) ? Subscription::GRACIA : Subscription::SUSPENDIDA;
    }

    /**
     * Lo que hay que mostrarle al usuario para que pague.
     *
     * El monto en bolívares se calcula con la tasa del día. Se le muestra ya convertido
     * porque es lo que va a teclear en su app del banco: obligarlo a multiplicar es pedirle
     * justo la cuenta de la que este producto lo libera.
     */
    public function cobroDe(Plan $plan): array
    {
        $tasa = round((float) (ExchangeRate::vigente()?->price ?? 0), 4);
        $montoUsd = round((float) $plan->price_usd, 2);

        return [
            'plan' => ['id' => $plan->id, 'name' => $plan->name, 'days' => $plan->days],
            'monto_usd' => $montoUsd,
            'monto_bs' => $tasa > 0 ? round($montoUsd * $tasa, 2) : 0.0,
            'tasa' => $tasa,
            'cuentas' => PaymentAccount::activas()->get(),
        ];
    }

    /**
     * El cliente reporta que pagó. **Todavía no le da acceso.**
     *
     * Queda como `reportado` hasta que alguien —o algo— lo coteje contra el banco. Darle
     * acceso con solo decir que pagó convierte la suscripción en honor system, y a 3–5 $/mes
     * el fraude no compensa el trabajo de perseguirlo: es más barato confirmar.
     *
     * El monto se congela en las dos monedas con la tasa del momento: si se recalculara al
     * leer, el pago de ayer aparecería hoy con otra cifra y el cliente diría, con razón, que
     * pagó lo que le pidieron.
     */
    public function reportar(Tenant $negocio, Plan $plan, array $datos): Payment
    {
        $cobro = $this->cobroDe($plan);

        if ($cobro['tasa'] <= 0) {
            throw new RuntimeException('No hay tasa del día cargada, así que no se puede calcular el monto en bolívares. Intenta más tarde.');
        }

        $referencia = trim((string) ($datos['reference'] ?? ''));

        // La referencia es lo que impide que el mismo comprobante estire dos suscripciones.
        if ($referencia !== '' && Payment::sinNegocio()->where('reference', $referencia)->exists()) {
            throw new RuntimeException('Esa referencia ya fue reportada. Si crees que es un error, escríbenos.');
        }

        $cuenta = ! empty($datos['payment_account_id'])
            ? PaymentAccount::find($datos['payment_account_id'])
            : null;

        return Payment::create([
            'tenant_id' => $negocio->id,
            'plan_id' => $plan->id,
            'payment_account_id' => $cuenta?->id,
            'method_snapshot' => $cuenta?->label,
            'amount_usd' => $cobro['monto_usd'],
            'amount_bs' => $cobro['monto_bs'],
            'exchange_rate' => $cobro['tasa'],
            'reference' => $referencia ?: null,
            'paid_on' => $datos['paid_on'] ?? today()->toDateString(),
            'status' => Payment::REPORTADO,
            'notes' => $datos['notes'] ?? null,
        ]);
    }

    /**
     * Se confirmó que el dinero llegó: se extiende la suscripción.
     *
     * **Se extiende desde el vencimiento, no desde hoy**, siempre que no haya vencido: quien
     * paga con tres días de anticipación no puede perder esos tres días por ser puntual.
     * Si ya venció, se cuenta desde hoy — regalarle el tiempo que estuvo suspendido sería
     * cobrarle por días que no usó.
     */
    public function confirmar(Payment $pago, ?int $confirmadoPor = null): Subscription
    {
        if ($pago->status === Payment::CONFIRMADO) {
            throw new RuntimeException('Ese pago ya estaba confirmado.');
        }

        return DB::transaction(function () use ($pago, $confirmadoPor) {
            $negocio = Tenant::findOrFail($pago->tenant_id);
            $plan = $pago->plan ?? Plan::activos()->first();

            $suscripcion = Subscription::firstOrCreate(
                ['tenant_id' => $negocio->id],
                ['status' => Subscription::PRUEBA, 'trial_ends_at' => now()],
            );

            $desde = $suscripcion->ends_at && $suscripcion->ends_at->isFuture()
                ? $suscripcion->ends_at->copy()
                : Carbon::now();

            $suscripcion->update([
                'plan_id' => $plan?->id,
                'status' => Subscription::ACTIVA,
                'ends_at' => $desde->addDays($plan?->days ?? 30),
                'cancelled_at' => null,
            ]);

            $pago->update([
                'status' => Payment::CONFIRMADO,
                'confirmed_by' => $confirmadoPor ?? Auth::id(),
                'confirmed_at' => now(),
            ]);

            return $suscripcion->fresh();
        });
    }

    public function rechazar(Payment $pago, ?string $motivo = null): Payment
    {
        $pago->update([
            'status' => Payment::RECHAZADO,
            'notes' => $motivo ?: $pago->notes,
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
        ]);

        return $pago->fresh();
    }

    /** Lo que necesita la pantalla de suscripción. */
    public function resumen(Tenant $negocio): array
    {
        $suscripcion = $this->para($negocio);

        return [
            'estado' => $suscripcion->status,
            'da_acceso' => $suscripcion->da_acceso,
            'vence_el' => $suscripcion->vence_el?->format('d/m/Y'),
            'dias_restantes' => $suscripcion->dias_restantes,
            'plan' => $suscripcion->plan?->only(['id', 'name', 'price_usd', 'days']),
            'pago_pendiente' => Payment::delNegocio($negocio->id)->pendientes()->latest('id')->first(),
        ];
    }
}
