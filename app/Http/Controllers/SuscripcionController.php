<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Services\SuscripcionService;
use Illuminate\Support\Facades\Response;
use RuntimeException;

/**
 * Suscripción y cobro.
 *
 * El flujo es: el cliente elige plan → ve a dónde pagar y cuánto es en bolívares con la
 * tasa de hoy → paga en su banco → reporta la referencia → se confirma y se le extiende.
 *
 * El paso de reportar existe porque a 3–5 $/mes no compensa perseguir el fraude: es más
 * barato cotejar. Y el diseño deja el cotejo enchufable —hoy a mano, mañana un webhook del
 * banco— sin tocar el control de acceso.
 */
class SuscripcionController extends Controller
{
    public function __construct(private SuscripcionService $suscripciones) {}

    public function index()
    {
        $negocio = request()->user()->tenant;

        return inertia('Suscripcion/Index', [
            'suscripcion' => $this->suscripciones->resumen($negocio),
            'planes' => Plan::activos()->get(),
            'historial' => Payment::latest('id')->limit(12)->get()->map(fn (Payment $p) => [
                'id' => $p->id,
                'fecha' => $p->created_at?->format('d/m/Y'),
                'monto_usd' => (float) $p->amount_usd,
                'monto_bs' => (float) $p->amount_bs,
                'referencia' => $p->reference,
                'estado' => $p->status,
            ]),
        ]);
    }

    /** Cuánto y a dónde pagar, con la tasa del día ya aplicada. */
    public function cobro(string $planId)
    {
        return Response::json($this->suscripciones->cobroDe(Plan::activos()->findOrFail($planId)));
    }

    public function reportar()
    {
        $datos = request()->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'payment_account_id' => ['nullable', 'integer', 'exists:payment_accounts,id'],
            'reference' => ['required', 'string', 'max:80'],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'reference.required' => 'Escribe el número de referencia del pago.',
            'plan_id.required' => 'Elige el plan que estás pagando.',
            'paid_on.before_or_equal' => 'La fecha del pago no puede ser futura.',
        ]);

        try {
            $this->suscripciones->reportar(
                request()->user()->tenant,
                Plan::findOrFail($datos['plan_id']),
                $datos,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['reference' => $e->getMessage()]);
        }

        return back()->with(
            'success',
            'Recibimos tu reporte. En cuanto verifiquemos el pago se activa tu plan — normalmente el mismo día.'
        );
    }
}
