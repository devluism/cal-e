import { Link, usePage } from '@inertiajs/react';
import { Plus, TriangleAlert } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { IsotipoNorte } from '@/Components/Marca/LogoNorte';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { formatearNumero, formatearUsd } from '@/Utils/Formatter';

/**
 * El panel: la lista de precios ya calculada.
 *
 * Es la pantalla del «aha», así que lo primero que se ve es la tasa de hoy y los precios al
 * día — no un menú desde el cual llegar a ellos. Cada clic entre abrir la app y ver el
 * número es una razón para no volver.
 */
export default function Panel() {
    const { tasa, catalogo } = usePage().props;
    const productos = catalogo?.productos ?? [];

    // Agrupado por categoría: es como se lee y como se va a exportar a WhatsApp.
    const porCategoria = productos.reduce((grupos, p) => {
        const clave = p.category || 'Sin categoría';
        (grupos[clave] ??= []).push(p);

        return grupos;
    }, {});

    return (
        <AppLayout title="Mis precios de hoy">
            <Card className="min-w-0 border-primary/25 bg-primary/5">
                <CardHeader className="pb-2">
                    <CardDescription className="flex items-center gap-2">
                        Tasa de hoy
                        <Badge variant="outline" className="uppercase">
                            {tasa?.fuente === 'propia' ? 'la tuya' : 'BCV'}
                        </Badge>
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <p className="font-mono text-4xl font-semibold tabular-nums">
                        {formatearNumero(tasa?.valor ?? 0, 2)}
                        <span className="ml-2 text-base font-normal text-muted-foreground">
                            Bs/$
                        </span>
                    </p>

                    {tasa && !tasa.es_de_hoy && (
                        <p className="mt-2 flex items-start gap-2 text-xs text-warning">
                            <TriangleAlert className="mt-0.5 size-3.5 shrink-0" />
                            Esta tasa se actualizó {tasa.actualizada}. Si el BCV ya publicó la de
                            hoy, revísala antes de mandar tu lista.
                        </p>
                    )}
                </CardContent>
            </Card>

            {productos.length === 0 ? (
                <Card className="min-w-0">
                    <CardContent className="flex flex-col items-center gap-4 py-14 text-center">
                        <span className="flex size-14 items-center justify-center rounded-full bg-primary/10">
                            <IsotipoNorte className="size-7 text-primary" />
                        </span>

                        <div>
                            <p className="font-medium">Carga tu primer producto</p>
                            <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                                Pones el costo y el margen una sola vez. De ahí en adelante Norte
                                recalcula el precio con la tasa de cada día.
                            </p>
                        </div>

                        <Button asChild>
                            <Link href={route('productos.index')}>
                                <Plus /> Agregar producto
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <Card className="min-w-0">
                    <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 space-y-0">
                        <div>
                            <CardTitle className="text-base">Tu lista al día</CardTitle>
                            <CardDescription>
                                {productos.length} producto(s), con la tasa de hoy aplicada.
                            </CardDescription>
                        </div>

                        <Button variant="outline" size="sm" disabled>
                            Copiar para WhatsApp
                        </Button>
                    </CardHeader>

                    <CardContent className="flex flex-col gap-4">
                        {Object.entries(porCategoria).map(([categoria, items]) => (
                            <div key={categoria} className="flex flex-col">
                                <p className="mb-1 px-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    {categoria}
                                </p>

                                <div className="flex flex-col divide-y">
                                    {items.map((p) => (
                                        <div key={p.id} className="flex items-center gap-3 py-3">
                                            <span className="min-w-0 flex-1 truncate font-medium">
                                                {p.name}
                                            </span>

                                            <span className="shrink-0 text-right">
                                                <span className="block font-mono font-semibold tabular-nums">
                                                    {formatearNumero(p.precio_bs, 2)} Bs
                                                </span>
                                                <span className="block font-mono text-xs text-muted-foreground tabular-nums">
                                                    {formatearUsd(p.precio_usd)}
                                                </span>
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}
        </AppLayout>
    );
}
