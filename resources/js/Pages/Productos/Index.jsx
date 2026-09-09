import { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { LineChart, Lock, Package, Pencil, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import AppLayout from '@/Layouts/AppLayout';
import ConfirmDialog from '@/Components/ConfirmDialog';
import ProductoDialog from '@/Components/Productos/ProductoDialog';
import HistorialDialog from '@/Components/Productos/HistorialDialog';
import { IsotipoNorte } from '@/Components/Marca/LogoNorte';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';
import { formatearNumero, formatearUsd } from '@/Utils/Formatter';

export default function Productos() {
    const { flash, suscripcion } = usePage().props;

    const [catalogo, setCatalogo] = useState(null);
    const [cargando, setCargando] = useState(true);
    const [abierto, setAbierto] = useState(false);
    const [editando, setEditando] = useState(null);
    const [verHistorial, setVerHistorial] = useState(null);

    const cargar = async () => {
        try {
            const { data } = await axios.get(route('productos.list'));
            setCatalogo(data);
        } catch (error) {
            toast.error('No se pudo cargar tu catálogo');
        } finally {
            setCargando(false);
        }
    };

    useEffect(() => {
        cargar();
    }, []);

    // Guardar y borrar van por Inertia (recargan las props compartidas), así que la lista
    // se vuelve a pedir cuando llega el mensaje de éxito.
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
            cargar();
        }
    }, [flash?.success]);

    const abrirNuevo = () => {
        setEditando(null);
        setAbierto(true);
    };

    const abrirEdicion = (producto) => {
        setEditando(producto);
        setAbierto(true);
    };

    const eliminar = (producto) => {
        router.delete(route('productos.destroy', producto.id), { preserveScroll: true });
    };

    const productos = catalogo?.productos ?? [];
    const conAcceso = catalogo?.con_acceso ?? true;

    // Agrupado por categoría: es como se lee la lista y como se va a exportar a WhatsApp.
    const porCategoria = productos.reduce((grupos, p) => {
        const clave = p.category || 'Sin categoría';
        (grupos[clave] ??= []).push(p);

        return grupos;
    }, {});

    return (
        <AppLayout title="Productos">
            <Card className="min-w-0">
                <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 space-y-0">
                    <div className="min-w-0">
                        <CardTitle className="text-base">Tu catálogo</CardTitle>
                        <CardDescription>
                            {productos.length > 0
                                ? `${productos.length} producto(s). El precio se recalcula solo con la tasa de cada día.`
                                : 'Carga cada producto una vez. De ahí en adelante Norte se encarga.'}
                        </CardDescription>
                    </div>

                    {conAcceso && (
                        <Button onClick={abrirNuevo}>
                            <Plus /> Agregar
                        </Button>
                    )}
                </CardHeader>

                <CardContent className="flex flex-col gap-4">
                    {/* Suspendido: conserva su catálogo, pierde la parte viva. */}
                    {!conAcceso && (
                        <p className="flex items-start gap-2 rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
                            <Lock className="mt-0.5 size-4 shrink-0" />
                            <span>
                                Tu catálogo está intacto, pero los precios dejaron de calcularse.{' '}
                                <Link href={route('suscripcion')} className="font-medium underline">
                                    Renueva
                                </Link>{' '}
                                para volver a verlos al día.
                            </span>
                        </p>
                    )}

                    {cargando ? (
                        <div className="flex flex-col gap-2">
                            {[...Array(4)].map((_, i) => (
                                <Skeleton key={i} className="h-14 w-full" />
                            ))}
                        </div>
                    ) : productos.length === 0 ? (
                        <div className="flex flex-col items-center gap-4 py-12 text-center">
                            <span className="flex size-14 items-center justify-center rounded-full bg-primary/10">
                                <IsotipoNorte className="size-7 text-primary" />
                            </span>

                            <div>
                                <p className="font-medium">Tu lista está vacía</p>
                                <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                                    Carga tu primer producto: el costo, cuánto quieres ganarle, y
                                    listo. El precio se ajusta solo de ahí en adelante.
                                </p>
                            </div>

                            {conAcceso && (
                                <Button onClick={abrirNuevo}>
                                    <Plus /> Agregar mi primer producto
                                </Button>
                            )}
                        </div>
                    ) : (
                        Object.entries(porCategoria).map(([categoria, items]) => (
                            <div key={categoria} className="flex flex-col gap-1">
                                <p className="px-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    {categoria}
                                </p>

                                <div className="flex flex-col divide-y rounded-lg border">
                                    {/* Dos renglones y no uno: el nombre es lo que identifica
                                        el producto y en un teléfono, compartiendo línea con
                                        el precio y los botones, se truncaba a «Harina de
                                        m…». Ocupando su propia línea se lee entero. */}
                                    {items.map((p) => (
                                        <div key={p.id} className="flex flex-col gap-1 p-3">
                                            <div className="flex min-w-0 items-center gap-2">
                                                <span className="min-w-0 flex-1 truncate font-medium">
                                                    {p.name}
                                                </span>

                                                {conAcceso && (
                                                    <span className="flex shrink-0 gap-0.5">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                            aria-label={`Ver historial de ${p.name}`}
                                                            onClick={() => setVerHistorial(p)}
                                                        >
                                                            <LineChart className="size-3.5" />
                                                        </Button>

                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                            aria-label={`Editar ${p.name}`}
                                                            onClick={() => abrirEdicion(p)}
                                                        >
                                                            <Pencil className="size-3.5" />
                                                        </Button>

                                                        <ConfirmDialog
                                                            title={`¿Quitar «${p.name}»?`}
                                                            description="Se borra de tu lista. Puedes volver a cargarlo cuando quieras."
                                                            confirmLabel="Quitar"
                                                            onConfirm={() => eliminar(p)}
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8 text-destructive"
                                                                aria-label={`Quitar ${p.name}`}
                                                            >
                                                                <Trash2 className="size-3.5" />
                                                            </Button>
                                                        </ConfirmDialog>
                                                    </span>
                                                )}
                                            </div>

                                            <div className="flex min-w-0 items-baseline gap-3">
                                                <span className="min-w-0 flex-1 truncate text-xs text-muted-foreground">
                                                    Costo{' '}
                                                    {p.cost_currency === 'VES'
                                                        ? `${formatearNumero(p.cost, 2)} Bs`
                                                        : formatearUsd(p.cost)}
                                                    {p.cost_currency === 'VES' && p.cost_rate && (
                                                        <> a {formatearNumero(p.cost_rate, 2)}</>
                                                    )}
                                                    {' · '}
                                                    <span className="text-primary">
                                                        +{formatearNumero(p.margin, 0)}%
                                                    </span>
                                                </span>

                                                {conAcceso ? (
                                                    <span className="shrink-0 text-right">
                                                        <span className="font-mono text-sm font-semibold tabular-nums">
                                                            {formatearNumero(p.precio_bs, 2)} Bs
                                                        </span>
                                                        <span className="ml-2 font-mono text-xs text-muted-foreground tabular-nums">
                                                            {formatearUsd(p.precio_usd)}
                                                        </span>
                                                    </span>
                                                ) : (
                                                    <Badge variant="outline" className="shrink-0">
                                                        <Lock className="size-3" />
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>

            <ProductoDialog
                abierto={abierto}
                onOpenChange={setAbierto}
                producto={editando}
                tasa={catalogo?.tasa ?? 0}
                categorias={catalogo?.categorias ?? []}
            />

            <HistorialDialog
                abierto={Boolean(verHistorial)}
                onOpenChange={(open) => !open && setVerHistorial(null)}
                producto={verHistorial}
            />
        </AppLayout>
    );
}
