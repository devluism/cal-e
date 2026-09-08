import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import PanelMarca from '@/Components/Marca/PanelMarca';
import { IsotipoNorte } from '@/Components/Marca/LogoNorte';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

/**
 * Alta de cuenta.
 *
 * Cuatro campos y nada más. Cada uno de más es una razón para cerrar la pestaña: el
 * teléfono, el rubro y la tasa que usa se preguntan después, dentro del producto, cuando el
 * usuario ya vio para qué sirve. Acá solo lo imprescindible para que exista su negocio.
 */
export default function Registro() {
    const { data, setData, post, processing, errors } = useForm({
        negocio: '',
        name: '',
        email: '',
        password: '',
    });

    const enviar = (e) => {
        e.preventDefault();
        post(route('register'));
    };

    const campos = [
        {
            campo: 'negocio',
            etiqueta: '¿Cómo se llama tu negocio?',
            tipo: 'text',
            placeholder: 'Bodega La Esquina',
            ayuda: 'Es el nombre que va a ver tu cliente en la lista de precios.',
        },
        { campo: 'name', etiqueta: 'Tu nombre', tipo: 'text', placeholder: 'María Rodríguez' },
        { campo: 'email', etiqueta: 'Correo', tipo: 'email', placeholder: 'tucorreo@ejemplo.com' },
        {
            campo: 'password',
            etiqueta: 'Contraseña',
            tipo: 'password',
            placeholder: '••••••••',
            ayuda: 'Mínimo 8 caracteres.',
        },
    ];

    return (
        <div className="min-h-svh bg-background p-3 lg:p-4">
            <Head title="Crear cuenta" />

            <div className="grid min-h-[calc(100svh-1.5rem)] gap-4 lg:grid-cols-2">
                <PanelMarca />

                <main className="flex min-w-0 items-center justify-center px-4 py-10">
                    <div className="w-full max-w-sm">
                        <div className="mb-10 flex items-center justify-center gap-2 lg:hidden">
                            <IsotipoNorte className="size-7 text-primary" />
                            <span className="text-xl font-semibold tracking-tight">Norte</span>
                        </div>

                        <header className="mb-8 text-center">
                            <h1 className="text-3xl font-semibold tracking-tight">Empieza gratis</h1>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Carga tus productos una vez. Los precios se ajustan solos.
                            </p>
                        </header>

                        <form onSubmit={enviar} className="flex flex-col gap-4">
                            {campos.map(({ campo, etiqueta, tipo, placeholder, ayuda }) => (
                                <div key={campo} className="grid gap-2">
                                    <Label htmlFor={campo}>{etiqueta}</Label>
                                    <Input
                                        id={campo}
                                        type={tipo}
                                        placeholder={placeholder}
                                        className="h-11"
                                        autoComplete={tipo === 'password' ? 'new-password' : 'off'}
                                        value={data[campo]}
                                        onChange={(e) => setData(campo, e.target.value)}
                                        aria-invalid={Boolean(errors[campo])}
                                    />
                                    {errors[campo] ? (
                                        <p className="text-xs text-destructive">{errors[campo]}</p>
                                    ) : (
                                        ayuda && (
                                            <p className="text-xs text-muted-foreground">{ayuda}</p>
                                        )
                                    )}
                                </div>
                            ))}

                            <Button type="submit" className="mt-2 h-11 w-full" disabled={processing}>
                                {processing && <Loader2 className="animate-spin" />}
                                Crear mi cuenta
                            </Button>
                        </form>

                        <p className="mt-8 text-center text-sm text-muted-foreground">
                            ¿Ya tienes cuenta?{' '}
                            <Link
                                href={route('login')}
                                className="font-medium text-foreground underline-offset-4 hover:underline"
                            >
                                Entrar
                            </Link>
                        </p>
                    </div>
                </main>
            </div>
        </div>
    );
}
