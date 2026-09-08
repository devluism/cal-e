import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Plus, TriangleAlert } from 'lucide-react';
import { IsotipoNorte } from '@/Components/Marca/LogoNorte';
import ThemeToggle from '@/Components/ThemeToggle';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { formatearNumero, formatearUsd } from '@/Utils/Formatter';

/**
 * El panel: la lista de precios ya calculada.
 *
 * Es la pantalla del "aha" y por eso no tiene menú lateral: lo primero y casi lo único que
 * se ve es la tasa de hoy y los precios al día. La navegación llega cuando haya más de una
 * cosa que hacer.
 */
export default function Panel() {
    const { auth, tasa, catalogo } = usePage().props;
    const productos = catalogo?.productos ?? [];

    return (
        <div className="min-h-svh bg-background">
            <Head title="Mis precios" />

            <header className="sticky top-0 z-10 border-b bg-background/90 backdrop-blur">
                <div className="mx-auto flex h-16 max-w-3xl items-center gap-3 px-4">
                    <IsotipoNorte className="size-6 shrink-0 text-primary" />

                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium">{auth.negocio?.name}</p>
                        <p className="truncate text-xs text-muted-foreground">Mis precios de hoy</p>
                    </div>

                    <ThemeToggle />

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="text-xs text-muted-foreground hover:text-foreground"
                    >
                        Salir
                    </Link>
                </div>
            </header>

            <main className="mx-auto flex max-w-3xl flex-col gap-4 px-4 py-6">
                {/* La tasa primero: es lo que el usuario viene a mirar. */}
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

                            <Button disabled>
                                <Plus /> Agregar producto
                            </Button>
                            <p className="text-xs text-muted-foreground">
                                (El alta de productos llega en el siguiente paso.)
                            </p>
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
                                Copiar para WhatsApp <ArrowUpRight className="size-3.5" />
                            </Button>
                        </CardHeader>

                        <CardContent className="flex flex-col divide-y">
                            {productos.map((p) => (
                                <div key={p.id} className="flex items-center gap-3 py-3">
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate font-medium">{p.name}</span>
                                        {p.category && (
                                            <span className="text-xs text-muted-foreground">
                                                {p.category}
                                            </span>
                                        )}
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
                        </CardContent>
                    </Card>
                )}
            </main>
        </div>
    );
}
