<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\PrecioService;

/**
 * El panel: la lista de precios ya calculada con la tasa de hoy.
 *
 * Es la pantalla del "aha": lo primero que el usuario ve al entrar tiene que ser su lista
 * al día, no un menú desde el cual llegar a ella. Cada clic entre abrir la app y ver el
 * número es una razón para no volver.
 */
class PanelController extends Controller
{
    public function __construct(private PrecioService $precios) {}

    public function index()
    {
        $negocio = request()->user()->tenant;

        $productos = Product::activos()->orderBy('position')->orderBy('name')->get();

        return inertia('Panel/Index', [
            'catalogo' => $this->precios->calcularCatalogo($negocio, $productos),
        ]);
    }
}
