<?php

namespace App\Console\Commands;

use App\Mail\SuscripcionPorVencer;
use App\Models\Subscription;
use App\Services\SuscripcionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Manda el aviso de vencimiento a quien le toque, y solo una vez por hito.
 *
 * `Subscription` no lleva el scope de inquilino (ver el modelo), así que este comando puede
 * recorrer las de toda la plataforma sin tener que iterar negocio por negocio.
 */
class AvisarVencimientos extends Command
{
    protected $signature = 'suscripcion:avisar';

    protected $description = 'Avisa por correo a los negocios cuya suscripción está por vencer o venció';

    public function handle(SuscripcionService $suscripciones): int
    {
        $avisados = 0;

        Subscription::with('tenant.users')->each(function (Subscription $suscripcion) use ($suscripciones, &$avisados) {
            $suscripciones->avanzar($suscripcion);

            $hito = $suscripciones->hitoDeAviso($suscripcion);

            if ($hito === null) {
                return;
            }

            $negocio = $suscripcion->tenant;
            $destinatarios = $negocio->users->where('active', true)->pluck('email');

            // Sin nadie a quien mandárselo no hay aviso que registrar como enviado: si mañana
            // el negocio tiene un usuario activo, tiene que poder recibirlo igual.
            if ($destinatarios->isEmpty()) {
                return;
            }

            Mail::to($destinatarios->all())->send(new SuscripcionPorVencer($negocio, $suscripcion, $hito));

            $suscripcion->update(['reminder_sent_for' => $hito]);
            $avisados++;
        });

        $this->info("Avisados {$avisados} negocio(s).");

        return self::SUCCESS;
    }
}
