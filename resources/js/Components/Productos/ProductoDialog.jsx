import { useEffect, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { formatearNumero, formatearUsd } from '@/Utils/Formatter';
import { calcularPrecio } from '@/Utils/Precio';
import { cn } from '@/lib/utils';

const VACIO = { name: '', category: '', cost: '', cost_currency: 'USD', cost_rate: '', margin: '30' };

/**
 * Alta y edición de un producto.
 *
 * La pieza central es la **vista previa en vivo**: el precio aparece mientras el usuario
 * teclea el costo y el margen. Es el momento en que entiende qué hace la herramienta, así
 * que no puede estar detrás de un botón «calcular» ni esperar al servidor.
 */
export default function ProductoDialog({ abierto, onOpenChange, producto, tasa, categorias = [] }) {
    const { auth } = usePage().props;
    const editando = Boolean(producto);

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm(VACIO);

    useEffect(() => {
        if (!abierto) return;

        clearErrors();

        setData(
            producto
                ? {
                      name: producto.name ?? '',
                      category: producto.category ?? '',
                      cost: String(producto.cost ?? ''),
                      cost_currency: producto.cost_currency ?? 'USD',
                      cost_rate: producto.cost_rate != null ? String(producto.cost_rate) : '',
                      margin: String(producto.margin ?? ''),
                  }
                : { ...VACIO },
        );
    }, [abierto, producto?.id]);

    /*
     * Al pasar el costo a bolívares se prellena con la tasa de hoy, que es el caso común:
     * «esto lo compré ahorita a como está el dólar». Quien esté cargando un costo viejo la
     * cambia. Dejarla vacía obligaría a todos a buscar un dato que la mayoría no necesita.
     */
    const cambiarMoneda = (moneda) => {
        setData((prev) => ({
            ...prev,
            cost_currency: moneda,
            cost_rate: moneda === 'VES' && !prev.cost_rate ? String(tasa || '') : prev.cost_rate,
        }));
    };

    const enBolivares = data.cost_currency === 'VES';

    const previa = calcularPrecio({
        costo: data.cost,
        moneda: data.cost_currency,
        tasaCosto: data.cost_rate,
        margen: data.margin,
        tasa,
        redondeo: auth.negocio?.rounding ?? 0,
    });

    const hayPrevia = Number(data.cost) > 0 && previa.precioBs > 0;

    const enviar = (e) => {
        e.preventDefault();

        const opciones = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                reset();
            },
            onError: () => toast.error('Revisa los datos marcados.'),
        };

        editando
            ? put(route('productos.update', producto.id), opciones)
            : post(route('productos.store'), opciones);
    };

    return (
        <Dialog open={abierto} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92svh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{editando ? 'Editar producto' : 'Nuevo producto'}</DialogTitle>
                    <DialogDescription>
                        Pones el costo y cuánto quieres ganarle. El precio se recalcula solo con la
                        tasa de cada día.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={enviar} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">¿Qué vendes?</Label>
                        <Input
                            id="name"
                            placeholder="Harina de maíz 1 kg"
                            className="h-11"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            aria-invalid={Boolean(errors.name)}
                            autoFocus
                        />
                        {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                    </div>

                    {/* Moneda del costo: dos botones y no un desplegable. Con dos opciones,
                        un select es un toque de más y esconde la que no está elegida. */}
                    <div className="grid gap-2">
                        <Label>¿Cuánto te costó?</Label>

                        <div className="flex gap-2">
                            {[
                                ['USD', 'En dólares'],
                                ['VES', 'En bolívares'],
                            ].map(([valor, etiqueta]) => (
                                <button
                                    key={valor}
                                    type="button"
                                    onClick={() => cambiarMoneda(valor)}
                                    className={cn(
                                        'flex-1 rounded-lg border px-3 py-2 text-sm transition-colors',
                                        data.cost_currency === valor
                                            ? 'border-primary bg-primary/10 font-medium'
                                            : 'text-muted-foreground hover:bg-accent',
                                    )}
                                >
                                    {etiqueta}
                                </button>
                            ))}
                        </div>

                        <div className="relative">
                            {/* `step="any"` en todos los campos numéricos, a propósito.
                                Con un `step` fijo el navegador rechaza valores que no caen
                                en la rejilla —y lo hace en silencio, con un mensaje suyo en
                                inglés que este público no va a leer—. Pasó con la tasa
                                prellenada (814,6908 contra step 0,01): el formulario no
                                enviaba y no se entendía por qué. Quien valida es el
                                servidor; el navegador solo elige el teclado. */}
                            <Input
                                id="cost"
                                type="number"
                                step="any"
                                min="0"
                                inputMode="decimal"
                                placeholder="0,00"
                                className="h-11 pr-12 font-mono tabular-nums"
                                value={data.cost}
                                onChange={(e) => setData('cost', e.target.value)}
                                aria-invalid={Boolean(errors.cost)}
                            />
                            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">
                                {enBolivares ? 'Bs' : '$'}
                            </span>
                        </div>
                        {errors.cost && <p className="text-xs text-destructive">{errors.cost}</p>}
                    </div>

                    {/* El anclaje. Solo aparece si hace falta, y explica para qué sirve —
                        sin la explicación parece un dato burocrático más. */}
                    {enBolivares && (
                        <div className="grid gap-2 rounded-lg border border-primary/25 bg-primary/5 p-3">
                            <Label htmlFor="cost_rate">¿A qué tasa lo compraste?</Label>
                            <Input
                                id="cost_rate"
                                type="number"
                                step="any"
                                min="0"
                                inputMode="decimal"
                                className="h-11 font-mono tabular-nums"
                                value={data.cost_rate}
                                onChange={(e) => setData('cost_rate', e.target.value)}
                                aria-invalid={Boolean(errors.cost_rate)}
                            />
                            {errors.cost_rate ? (
                                <p className="text-xs text-destructive">{errors.cost_rate}</p>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    Con esto tu costo queda anclado en{' '}
                                    <span className="font-mono">
                                        {formatearUsd(previa.costoUsd)}
                                    </span>{' '}
                                    y tu precio sube solo cuando suba el dólar. Sin esto irías
                                    perdiendo sin darte cuenta.
                                </p>
                            )}
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="margin">¿Cuánto quieres ganarle?</Label>
                        <div className="relative">
                            <Input
                                id="margin"
                                type="number"
                                step="any"
                                min="0"
                                inputMode="decimal"
                                placeholder="30"
                                className="h-11 pr-9 font-mono tabular-nums"
                                value={data.margin}
                                onChange={(e) => setData('margin', e.target.value)}
                                aria-invalid={Boolean(errors.margin)}
                            />
                            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">
                                %
                            </span>
                        </div>
                        {errors.margin && (
                            <p className="text-xs text-destructive">{errors.margin}</p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="category">Categoría (opcional)</Label>
                        <Input
                            id="category"
                            list="categorias-existentes"
                            placeholder="Víveres"
                            className="h-11"
                            value={data.category}
                            onChange={(e) => setData('category', e.target.value)}
                        />
                        {/* Sugiere las que ya usó, sin obligarlo a elegir de una lista. */}
                        <datalist id="categorias-existentes">
                            {categorias.map((c) => (
                                <option key={c} value={c} />
                            ))}
                        </datalist>
                        <p className="text-xs text-muted-foreground">
                            Sirve para agrupar la lista cuando la mandes por WhatsApp.
                        </p>
                    </div>

                    {/* La vista previa: el momento en que se entiende el producto. */}
                    <div
                        className={cn(
                            'rounded-lg border p-4 text-center transition-colors',
                            hayPrevia ? 'border-primary/30 bg-primary/5' : 'bg-muted/40',
                        )}
                    >
                        <p className="text-sm text-muted-foreground">Lo vendes hoy en</p>

                        {hayPrevia ? (
                            <>
                                <p className="font-mono text-3xl font-semibold tabular-nums">
                                    {formatearNumero(previa.precioBs, 2)}
                                    <span className="ml-1 text-base font-normal">Bs</span>
                                </p>
                                <p className="mt-1 font-mono text-xs text-muted-foreground tabular-nums">
                                    {formatearUsd(previa.precioUsd)} · te queda{' '}
                                    {formatearUsd(previa.precioUsd - previa.costoUsd)} de ganancia
                                </p>
                            </>
                        ) : (
                            <p className="mt-1 text-sm text-muted-foreground">
                                {tasa > 0
                                    ? 'Escribe el costo para ver tu precio.'
                                    : 'Falta la tasa del día para poder calcularlo.'}
                            </p>
                        )}
                    </div>

                    <DialogFooter className="gap-2 sm:gap-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Loader2 className="animate-spin" />}
                            {editando ? 'Guardar cambios' : 'Agregar a mi lista'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
