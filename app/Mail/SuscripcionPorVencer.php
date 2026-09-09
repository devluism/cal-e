<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * El correo de «te quedan N días» (o «tu suscripción venció»).
 *
 * Es la mitad del pendiente «avisar del vencimiento»: hoy el negocio solo se entera de que se
 * quedó sin la parte viva del producto si vuelve a entrar a la app, y para el momento en que
 * lo nota ya pudo haber mandado una lista con precios congelados a un cliente.
 */
class SuscripcionPorVencer extends Mailable
{
    use Queueable, SerializesModels;

    /** @param '3'|'1'|'vencida' $hito El mismo hito que calcula `SuscripcionService::hitoDeAviso()`. */
    public function __construct(
        public Tenant $negocio,
        public Subscription $suscripcion,
        public string $hito,
    ) {}

    public function build(): self
    {
        return $this->subject($this->asunto())
            ->view('emails.suscripcion-por-vencer')
            ->with(['dias' => $this->dias()]);
    }

    private function asunto(): string
    {
        return $this->hito === 'vencida'
            ? "{$this->negocio->name}: tu suscripción a Norte venció"
            : "{$this->negocio->name}: tu suscripción a Norte vence en {$this->dias()} día(s)";
    }

    public function dias(): int
    {
        return max(0, $this->suscripcion->dias_restantes);
    }
}
