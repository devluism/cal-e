<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRate;
use App\Models\Product;
use App\Services\PrecioService;
use App\Services\SuscripcionService;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

/**
 * El catálogo del negocio: lo que vende, a qué costo y con qué margen.
 *
 * ## Por qué listar está abierto y editar no
 *
 * Ver el catálogo **no** exige suscripción vigente: esos costos y márgenes los cargó el
 * usuario y son suyos, y quitárselos de la vista al vencer sería la forma más segura de que
 * no vuelva. Lo que se paga es la parte viva —el precio recalculado con la tasa de hoy— y
 * eso sí está detrás de `suscrito`, igual que el panel.
 *
 * Por eso `list()` omite los precios calculados cuando la suscripción no da acceso: se ve la
 * lista, no el resultado. Es la promesa que hace `RequiereSuscripcion`, cumplida.
 */
class ProductoController extends Controller
{
    public function __construct(
        private PrecioService $precios,
        private SuscripcionService $suscripciones,
    ) {}

    public function index()
    {
        return inertia('Productos/Index');
    }

    public function list()
    {
        $negocio = request()->user()->tenant;
        $conAcceso = $this->suscripciones->para($negocio)->da_acceso;

        $productos = Product::orderBy('position')->orderBy('name')->get();
        $tasa = $this->precios->tasaDe($negocio);
        $redondeo = (float) $negocio->rounding;

        return Response::json([
            'tasa' => $tasa,
            'con_acceso' => $conAcceso,
            'productos' => $productos->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category,
                'cost' => (float) $p->cost,
                'cost_currency' => $p->cost_currency,
                'cost_rate' => $p->cost_rate !== null ? (float) $p->cost_rate : null,
                'margin' => (float) $p->margin,
                'active' => $p->active,

                // El precio solo viaja si la suscripción da acceso. Calcularlo y esconderlo
                // en el navegador no sería esconderlo: basta abrir las herramientas del
                // navegador para verlo.
                ...($conAcceso ? $this->precios->calcular($p, $tasa, $redondeo) : []),
            ]),

            // Las categorías que ya usó, para sugerirlas en vez de hacerle escribirlas
            // completas cada vez. Es texto libre: obligarlo a crear categorías antes de
            // cargar su primer producto sería una pantalla más antes del «aha».
            'categorias' => $productos->pluck('category')->filter()->unique()->sort()->values(),
        ]);
    }

    public function store()
    {
        $datos = $this->validar();

        $producto = Product::create([
            ...$datos,
            'position' => (int) Product::max('position') + 1,
            'active' => true,
        ]);

        return back()->with('success', "«{$producto->name}» quedó en tu lista.");
    }

    public function update(string $id)
    {
        $producto = Product::findOrFail($id);

        $producto->update($this->validar());

        return back()->with('success', 'Producto actualizado.');
    }

    public function destroy(string $id)
    {
        $producto = Product::findOrFail($id);
        $nombre = $producto->name;

        $producto->delete();

        return back()->with('success', "«{$nombre}» se quitó de tu lista.");
    }

    /**
     * Reglas del alta y la edición.
     *
     * `cost_rate` es obligatoria **solo** cuando el costo viene en bolívares: sin ella esos
     * bolívares no significan nada y el precio saldría en cero. Ver el porqué completo en la
     * migración de `products`.
     */
    private function validar(): array
    {
        $datos = request()->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:60'],
            'cost' => ['required', 'numeric', 'min:0'],
            'cost_currency' => ['required', Rule::in([Product::USD, Product::VES])],
            'cost_rate' => ['nullable', 'numeric', 'gt:0', Rule::requiredIf(
                fn () => request('cost_currency') === Product::VES
            )],
            'margin' => ['required', 'numeric', 'min:0', 'max:1000'],
        ], [
            'name.required' => 'Ponle un nombre al producto.',
            'cost.required' => 'Escribe cuánto te costó.',
            'cost.numeric' => 'El costo tiene que ser un número.',
            'cost_rate.required' => 'Necesitamos la tasa a la que compraste, para anclar el costo.',
            'cost_rate.gt' => 'La tasa tiene que ser mayor que cero.',
            'margin.required' => 'Escribe cuánto quieres ganarle.',
            'margin.max' => 'Ese margen es demasiado alto. ¿Seguro no son bolívares en vez de por ciento?',
        ]);

        // Un costo en dólares no necesita tasa de referencia: ya está anclado. Guardarla
        // sería dejar un dato que el cálculo ignora y que confundiría a quien lea la fila.
        if ($datos['cost_currency'] === Product::USD) {
            $datos['cost_rate'] = null;
        }

        return $datos;
    }

    /** La tasa del día, para que el formulario prellene el anclaje del costo en bolívares. */
    public function tasaDelDia()
    {
        return Response::json([
            'tasa' => round((float) (ExchangeRate::vigente()?->price ?? 0), 4),
        ]);
    }
}
