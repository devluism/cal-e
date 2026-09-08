import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, Loader2 } from 'lucide-react';
import PanelMarca from '@/Components/Marca/PanelMarca';
import { IsotipoNorte } from '@/Components/Marca/LogoNorte';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

/** Logo de Google, en línea: una petición menos y no depende de un CDN externo. */
function LogoGoogle(props) {
    return (
        <svg viewBox="0 0 24 24" aria-hidden className="size-4" {...props}>
            <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.4a5.5 5.5 0 0 1-2.4 3.6v3h3.9c2.3-2.1 3.6-5.2 3.6-8.8Z" />
            <path fill="#34A853" d="M12 24c3.2 0 6-1.1 8-2.9l-3.9-3c-1.1.7-2.5 1.2-4.1 1.2-3.1 0-5.8-2.1-6.7-5H1.3v3.1A12 12 0 0 0 12 24Z" />
            <path fill="#FBBC05" d="M5.3 14.3a7.2 7.2 0 0 1 0-4.6V6.6H1.3a12 12 0 0 0 0 10.8l4-3.1Z" />
            <path fill="#EA4335" d="M12 4.8c1.8 0 3.3.6 4.6 1.8l3.4-3.4A12 12 0 0 0 1.3 6.6l4 3.1c.9-2.9 3.6-4.9 6.7-4.9Z" />
        </svg>
    );
}

export default function Login({ status }) {
    const [verClave, setVerClave] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const enviar = (e) => {
        e.preventDefault();
        post(route('login'));
    };

    return (
        <div className="min-h-svh bg-background p-3 lg:p-4">
            <Head title="Entrar" />

            {/* Dos columnas en escritorio; en teléfono el panel de marca desaparece y queda
                solo el formulario — que es como lo va a ver la mayoría del público. */}
            <div className="grid min-h-[calc(100svh-1.5rem)] gap-4 lg:grid-cols-2">
                <PanelMarca />

                <main className="flex min-w-0 items-center justify-center px-4 py-10">
                    <div className="w-full max-w-sm">
                        {/* En teléfono el logo va acá arriba, ya que no hay panel de marca. */}
                        <div className="mb-10 flex items-center justify-center gap-2 lg:hidden">
                            <IsotipoNorte className="size-7 text-primary" />
                            <span className="text-xl font-semibold tracking-tight">Norte</span>
                        </div>

                        <header className="mb-8 text-center">
                            <h1 className="text-3xl font-semibold tracking-tight">Hola de nuevo</h1>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Entra para ver tus precios de hoy
                            </p>
                        </header>

                        {status && (
                            <p className="mb-6 rounded-lg bg-success/10 px-4 py-3 text-center text-sm text-success">
                                {status}
                            </p>
                        )}

                        <Button variant="outline" className="h-11 w-full gap-2" asChild>
                            <a href={route('oauth.redirect', 'google')}>
                                <LogoGoogle />
                                Continuar con Google
                            </a>
                        </Button>

                        <div className="my-6 flex items-center gap-3">
                            <span className="h-px flex-1 bg-border" />
                            <span className="text-xs text-muted-foreground">o con tu correo</span>
                            <span className="h-px flex-1 bg-border" />
                        </div>

                        <form onSubmit={enviar} className="flex flex-col gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Correo</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    autoComplete="email"
                                    inputMode="email"
                                    placeholder="tucorreo@ejemplo.com"
                                    className="h-11"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    aria-invalid={Boolean(errors.email)}
                                    autoFocus
                                />
                                {errors.email && (
                                    <p className="text-xs text-destructive">{errors.email}</p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-baseline justify-between gap-2">
                                    <Label htmlFor="password">Contraseña</Label>
                                    <Link
                                        href={route('password.request')}
                                        className="text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                    >
                                        ¿La olvidaste?
                                    </Link>
                                </div>

                                <div className="relative">
                                    <Input
                                        id="password"
                                        type={verClave ? 'text' : 'password'}
                                        autoComplete="current-password"
                                        placeholder="••••••••"
                                        className="h-11 pr-11"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        aria-invalid={Boolean(errors.password)}
                                    />

                                    {/* Ver la clave importa más en teléfono, donde el teclado
                                        se equivoca y reescribirla es media pantalla de trabajo. */}
                                    <button
                                        type="button"
                                        onClick={() => setVerClave((v) => !v)}
                                        aria-label={verClave ? 'Ocultar contraseña' : 'Ver contraseña'}
                                        className="absolute right-1 top-1/2 -translate-y-1/2 rounded-md p-2 text-muted-foreground transition-colors hover:text-foreground"
                                    >
                                        {verClave ? (
                                            <EyeOff className="size-4" />
                                        ) : (
                                            <Eye className="size-4" />
                                        )}
                                    </button>
                                </div>

                                {errors.password && (
                                    <p className="text-xs text-destructive">{errors.password}</p>
                                )}
                            </div>

                            <Button type="submit" className="mt-2 h-11 w-full" disabled={processing}>
                                {processing && <Loader2 className="animate-spin" />}
                                Entrar
                            </Button>
                        </form>

                        <p className="mt-8 text-center text-sm text-muted-foreground">
                            ¿Todavía no tienes cuenta?{' '}
                            <Link
                                href={route('register')}
                                className="font-medium text-foreground underline-offset-4 hover:underline"
                            >
                                Empieza gratis
                            </Link>
                        </p>
                    </div>
                </main>
            </div>
        </div>
    );
}
