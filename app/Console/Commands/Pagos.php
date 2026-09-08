<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\SuscripcionService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Cotejar y confirmar pagos reportados, desde la consola.
 *
 * Es la otra mitad del cobro: sin confirmar, un reporte no activa nada. Va por consola y no
 * por una pantalla de administración porque con las primeras decenas de clientes es más
 * rápido —se abre el estado de cuenta del banco al lado y se confirma— y porque una consola
 * de administración es un producto en sí mismo que no toca construir todavía.
 *
 * Cuando haya volumen, la pantalla llama a `SuscripcionService::confirmar()`, igual que este
 * comando. Y cuando el banco exponga notificaciones, las llama un webhook. El control de
 * acceso no se entera de cuál de los tres fue.
 */
class Pagos extends Command
{
    protected $signature = 'pagos
        {accion=pendientes : pendientes | confirmar | rechazar}
        {id? : El id del pago}
        {--motivo= : Motivo del rechazo}';

    protected $description = 'Lista, confirma o rechaza los pagos de suscripción reportados';

    public function handle(SuscripcionService $suscripciones): int
    {
        return match ($this->argument('accion')) {
            'pendientes' => $this->listar(),
            'confirmar' => $this->confirmar($suscripciones),
            'rechazar' => $this->rechazar($suscripciones),
            default => $this->fallo('Acción desconocida. Usa: pendientes | confirmar | rechazar'),
        };
    }

    private function listar(): int
    {
        // `sinNegocio()` a propósito: desde consola no hay sesión y hay que ver todos.
        $pendientes = Payment::sinNegocio()->with('tenant:id,name')->where('status', Payment::REPORTADO)
            ->orderBy('id')->get();

        if ($pendientes->isEmpty()) {
            $this->info('No hay pagos por confirmar.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'negocio', 'referencia', 'pagó', 'monto Bs', 'monto $'],
            $pendientes->map(fn (Payment $p) => [
                $p->id,
                $p->tenant?->name ?? '—',
                $p->reference ?? '—',
                $p->paid_on?->format('d/m/Y') ?? '—',
                number_format((float) $p->amount_bs, 2, ',', '.'),
                number_format((float) $p->amount_usd, 2),
            ])->all()
        );

        $this->line('Confirmar con: php artisan pagos confirmar <id>');

        return self::SUCCESS;
    }

    private function confirmar(SuscripcionService $suscripciones): int
    {
        $pago = $this->pago();

        if (! $pago) {
            return self::FAILURE;
        }

        try {
            $suscripcion = $suscripciones->confirmar($pago);
        } catch (RuntimeException $e) {
            return $this->fallo($e->getMessage());
        }

        $this->info("Pago #{$pago->id} confirmado. La suscripción vence el ".$suscripcion->ends_at->format('d/m/Y').'.');

        return self::SUCCESS;
    }

    private function rechazar(SuscripcionService $suscripciones): int
    {
        $pago = $this->pago();

        if (! $pago) {
            return self::FAILURE;
        }

        $suscripciones->rechazar($pago, $this->option('motivo'));
        $this->info("Pago #{$pago->id} rechazado.");

        return self::SUCCESS;
    }

    private function pago(): ?Payment
    {
        $id = $this->argument('id');

        if (! $id) {
            $this->fallo('Falta el id del pago. Míralo con: php artisan pagos pendientes');

            return null;
        }

        $pago = Payment::sinNegocio()->find($id);

        if (! $pago) {
            $this->fallo("No existe el pago #{$id}.");
        }

        return $pago;
    }

    private function fallo(string $mensaje): int
    {
        $this->error($mensaje);

        return self::FAILURE;
    }
}
