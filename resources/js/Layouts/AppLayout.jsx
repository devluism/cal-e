import { Head, Link, usePage } from '@inertiajs/react';
import { Clock, LogOut, Package, TrendingUp } from 'lucide-react';
import { IsotipoNorte } from '@/Components/Marca/LogoNorte';
import ThemeToggle from '@/Components/ThemeToggle';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

/**
 * Marco de las pantallas con sesión.
 *
 * La navegación son tres enlaces y nada más. No hay menú lateral a propósito: el público usa
 * el teléfono con una mano y entre cliente y cliente, y un sidebar en esa pantalla es una
 * capa que hay que abrir para llegar a lo que se venía a hacer. Con tres destinos, la barra
 * de arriba alcanza y no esconde nada.
 */
const NAVEGACION = [
    { ruta: 'panel', etiqueta: 'Mis precios', icono: TrendingUp, activo: 'panel' },
    { ruta: 'productos.index', etiqueta: 'Productos', icono: Package, activo: 'productos.*' },
];

export default function AppLayout({ title, children }) {
    const { auth, suscripcion } = usePage().props;

    // Se avisa desde una semana antes: da tiempo a hacer el Pago Móvil sin apuro, que es
    // como paga este público. Avisar el día del vencimiento es avisar tarde.
    const porVencer =
        suscripcion &&
        suscripcion.da_acceso &&
        suscripcion.dias_restantes !== null &&
        suscripcion.dias_restantes <= 7;

    return (
        <div className="min-h-svh bg-background">
            {title && <Head title={title} />}

            <header className="sticky top-0 z-10 border-b bg-background/90 backdrop-blur">
                <div className="mx-auto flex h-16 max-w-3xl items-center gap-3 px-4">
                    <Link href={route('panel')} className="flex min-w-0 items-center gap-2">
                        <IsotipoNorte className="size-6 shrink-0 text-primary" />
                        <span className="min-w-0">
                            <span className="block truncate text-sm font-medium">
                                {auth.negocio?.name}
                            </span>
                            <span className="block truncate text-xs text-muted-foreground">
                                {title}
                            </span>
                        </span>
                    </Link>

                    <div className="ml-auto flex items-center gap-1">
                        <ThemeToggle />

                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            aria-label="Salir"
                            className="rounded-md p-2 text-muted-foreground transition-colors hover:text-foreground"
                        >
                            <LogOut className="size-4" />
                        </Link>
                    </div>
                </div>

                <nav className="mx-auto flex max-w-3xl gap-1 px-4 pb-2">
                    {NAVEGACION.map(({ ruta, etiqueta, icono: Icono, activo }) => (
                        <Link
                            key={ruta}
                            href={route(ruta)}
                            className={cn(
                                'flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm transition-colors',
                                route().current(activo)
                                    ? 'bg-accent font-medium text-accent-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Icono className="size-4" />
                            {etiqueta}
                        </Link>
                    ))}
                </nav>
            </header>

            {porVencer && (
                <div className="border-b border-warning/30 bg-warning/10">
                    <div className="mx-auto flex max-w-3xl flex-wrap items-center gap-3 px-4 py-2.5">
                        <p className="flex min-w-0 flex-1 items-center gap-2 text-sm text-warning">
                            <Clock className="size-4 shrink-0" />
                            <span className="min-w-0">
                                {suscripcion.dias_restantes <= 0
                                    ? 'Tu plan vence hoy.'
                                    : `Tu plan vence en ${suscripcion.dias_restantes} día(s).`}
                            </span>
                        </p>

                        <Button size="sm" variant="outline" asChild>
                            <Link href={route('suscripcion')}>Renovar</Link>
                        </Button>
                    </div>
                </div>
            )}

            <main className="mx-auto flex max-w-3xl flex-col gap-4 px-4 py-6">{children}</main>
        </div>
    );
}
