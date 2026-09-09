<?php

namespace Database\Seeders;

use App\Models\PaymentAccount;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Planes y datos de cobro.
 *
 * No son datos de demostración: sin al menos un plan no se puede cobrar, y sin una cuenta
 * de cobro el usuario no sabe a dónde pagar. Se usa `updateOrCreate` sobre el código para
 * poder volver a correrlo sin pisar lo que el negocio haya ajustado.
 *
 * El precio sale del rango que definió el negocio (3–5 $/mes). El plan anual va con dos
 * meses de regalo: en un mercado donde la gente paga cuando puede, cobrar una vez al año
 * elimina once oportunidades de que se caiga la suscripción.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $planes = [
            ['mensual', 'Mensual', 'Todo Norte, mes a mes.', 4.00, 30, 0],
            ['anual', 'Anual', 'Dos meses de regalo si pagas el año completo.', 40.00, 365, 1],
        ];

        foreach ($planes as [$code, $name, $descripcion, $precio, $dias, $orden]) {
            Plan::updateOrCreate(['code' => $code], [
                'name' => $name,
                'description' => $descripcion,
                'price_usd' => $precio,
                'days' => $dias,
                // Sin tope de productos: limitar el catálogo del plan pago castigaría
                // justo al cliente que más usa la herramienta.
                'max_products' => null,
                'active' => true,
                'position' => $orden,
            ]);
        }

        // Datos de ejemplo: hay que reemplazarlos por los reales antes de cobrarle a nadie.
        PaymentAccount::updateOrCreate(['label' => 'Pago Móvil'], [
            'method' => 'pago_movil',
            'bank' => '0102 — Banco de Venezuela',
            'id_number' => 'V-25935410',
            'phone' => '0424-9064305',
            'holder' => 'Norte',
            'active' => true,
            'position' => 0,
        ]);
    }
}
