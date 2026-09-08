import { useEffect, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { Check, ClipboardCopy, Clock, Loader2, ShieldCheck, TriangleAlert } from 'lucide-react';
import { toast } from 'sonner';
import { IsotipoNorte } from '@/Components/Marca/LogoNorte';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Separator } from '@/Components/ui/separator';
import { Textarea } from '@/Components/ui/textarea';
import { formatearNumero, formatearUsd } from '@/Utils/Formatter';

const ESTADO = {
    prueba: { texto: 'Prueba gratis', clase: 'text-primary' },
    activa: { texto: 'Activa', clase: 'text-success' },
    gracia: { texto: 'Por vencer', clase: 'text-warning' },
    suspendida: { texto: 'Vencida', clase: 'text-destructive' },
    cancelada: { texto: 'Cancelada', clase: 'text-muted-foreground' },
};

/** Un dato del Pago Móvil con botón de copiar: nadie transcribe una cédula a mano sin error. */
function DatoCopiable({ etiqueta, valor }) {
    if (!valor) return null;

    const copiar = async () => {
        try {
            await navigator.clipboard.writeText(String(valor));
            toast.success(`${etiqueta} copiado`);
        } catch {
            toast.error('No se pudo copiar. Anótalo a mano.');
        }
    };

    return (
        <button
            type="button"
            onClick={copiar}
            className="flex w-full items-center justify-between gap-3 rounded-lg border px-3 py-2 text-left transition-colors hover:bg-accent"
        >
            <span className="min-w-0">
                <span className="block text-xs text-muted-foreground">{etiqueta}</span>
                <span className="block truncate font-mono text-sm">{valor}</span>
            </span>
            <ClipboardCopy className="size-4 shrink-0 text-muted-foreground" />
        </button>
    );
}

export default function Suscripcion() {
    const { auth, suscripcion, planes, historial, flash } = usePage().props;

    const [planElegido, setPlanElegido] = useState(planes?.[0]?.id ?? null);
    const [cobro, setCobro] = useState(null);
    const [cargandoCobro, setCargandoCobro] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        plan_id: planes?.[0]?.id ?? '',
        payment_account_id: '',
        reference: '',
        paid_on: '',
        notes: '',
    });

    // Al cambiar de plan se recalcula el monto en bolívares con la tasa de hoy.
    useEffect(() => {
        if (!planElegido) return;

        setCargandoCobro(true);
        axios
            .get(route('suscripcion.cobro', planElegido))
            .then(({ data: c }) => {
                setCobro(c);
                setData((prev) => ({
                    ...prev,
                    plan_id: planElegido,
                    payment_account_id: c.cuentas?.[0]?.id ?? '',
                }));
            })
            .catch(() => toast.error('No se pudo calcular el monto'))
            .finally(() => setCargandoCobro(false));
    }, [planElegido]);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
            reset('reference', 'notes');
        }
    }, [flash?.success]);

    const enviar = (e) => {
        e.preventDefault();
        post(route('suscripcion.reportar'), { preserveScroll: true });
    };

    const estado = ESTADO[suscripcion?.estado] ?? ESTADO.prueba;
    const pendiente = suscripcion?.pago_pendiente;

    return (
        <div className="min-h-svh bg-background">
            <Head title="Suscripción" />

            <header className="sticky top-0 z-10 border-b bg-background/90 backdrop-blur">
                <div className="mx-auto flex h-16 max-w-3xl items-center gap-3 px-4">
                    <IsotipoNorte className="size-6 shrink-0 text-primary" />
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium">{auth.negocio?.name}</p>
                        <p className="text-xs text-muted-foreground">Suscripción</p>
                    </div>
                    {suscripcion?.da_acceso && (
                        <Link href={route('panel')} className="text-xs text-muted-foreground hover:text-foreground">
                            Volver
                        </Link>
                    )}
                </div>
            </header>

            <main className="mx-auto flex max-w-3xl flex-col gap-4 px-4 py-6">
                <Card className="min-w-0">
                    <CardHeader className="pb-3">
                        <CardDescription className="flex items-center gap-2">
                            Tu plan
                            <Badge variant="outline" className={estado.clase}>
                                {estado.texto}
                            </Badge>
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="flex flex-col gap-3">
                        <p className="text-2xl font-semibold">
                            {suscripcion?.plan?.name ?? 'Prueba gratis'}
                        </p>

                        {suscripcion?.vence_el && (
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Clock className="size-4" />
                                {suscripcion.dias_restantes >= 0
                                    ? `Vence el ${suscripcion.vence_el} · quedan ${suscripcion.dias_restantes} día(s)`
                                    : `Venció el ${suscripcion.vence_el}`}
                            </p>
                        )}

                        {!suscripcion?.da_acceso && (
                            <p className="flex items-start gap-2 rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
                                <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                Tus precios dejaron de actualizarse. Tu catálogo está intacto: en cuanto
                                renueves, vuelve a calcularse con la tasa del día.
                            </p>
                        )}

                        {pendiente && (
                            <p className="flex items-start gap-2 rounded-lg bg-warning/10 p-3 text-sm text-warning">
                                <Clock className="mt-0.5 size-4 shrink-0" />
                                Ya reportaste un pago (ref. {pendiente.reference}). Lo estamos
                                verificando — normalmente el mismo día.
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Planes */}
                <div className="grid gap-4 sm:grid-cols-2">
                    {(planes ?? []).map((plan) => {
                        const elegido = plan.id === planElegido;

                        return (
                            <button
                                key={plan.id}
                                type="button"
                                onClick={() => setPlanElegido(plan.id)}
                                className={`min-w-0 rounded-xl border p-4 text-left transition-colors ${
                                    elegido ? 'border-primary bg-primary/5' : 'hover:bg-accent'
                                }`}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <span className="font-medium">{plan.name}</span>
                                    {elegido && <Check className="size-4 shrink-0 text-primary" />}
                                </div>

                                <p className="mt-1 font-mono text-2xl font-semibold tabular-nums">
                                    {formatearUsd(plan.price_usd)}
                                    <span className="ml-1 text-sm font-normal text-muted-foreground">
                                        / {plan.days >= 365 ? 'año' : 'mes'}
                                    </span>
                                </p>

                                <p className="mt-1 text-xs text-muted-foreground">{plan.description}</p>
                            </button>
                        );
                    })}
                </div>

                {/* Cómo pagar */}
                <Card className="min-w-0">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <ShieldCheck className="size-4 text-primary" />
                            Cómo pagar
                        </CardTitle>
                        <CardDescription>
                            Haz el Pago Móvil desde tu banco y reporta la referencia acá. Verificamos y
                            se activa — normalmente el mismo día.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="flex flex-col gap-4">
                        {cargandoCobro ? (
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Loader2 className="size-4 animate-spin" /> Calculando el monto…
                            </p>
                        ) : cobro ? (
                            <>
                                <div className="rounded-lg border bg-muted/40 p-4 text-center">
                                    <p className="text-sm text-muted-foreground">Monto a pagar</p>
                                    <p className="font-mono text-3xl font-semibold tabular-nums">
                                        {formatearNumero(cobro.monto_bs, 2)}
                                        <span className="ml-1 text-base font-normal">Bs</span>
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {formatearUsd(cobro.monto_usd)} a {formatearNumero(cobro.tasa, 2)} Bs/$
                                    </p>
                                </div>

                                {(cobro.cuentas ?? []).map((cuenta) => (
                                    <div key={cuenta.id} className="flex flex-col gap-2">
                                        <p className="text-sm font-medium">{cuenta.label}</p>
                                        <DatoCopiable etiqueta="Banco" valor={cuenta.bank} />
                                        <DatoCopiable etiqueta="Cédula / RIF" valor={cuenta.id_number} />
                                        <DatoCopiable etiqueta="Teléfono" valor={cuenta.phone} />
                                    </div>
                                ))}

                                <Separator />

                                <form onSubmit={enviar} className="flex flex-col gap-4">
                                    <p className="text-sm font-medium">Ya pagué: reportar</p>

                                    <div className="grid gap-2">
                                        <Label htmlFor="reference">Número de referencia</Label>
                                        <Input
                                            id="reference"
                                            inputMode="numeric"
                                            placeholder="Los últimos dígitos que te dio el banco"
                                            className="h-11"
                                            value={data.reference}
                                            onChange={(e) => setData('reference', e.target.value)}
                                            aria-invalid={Boolean(errors.reference)}
                                        />
                                        {errors.reference && (
                                            <p className="text-xs text-destructive">{errors.reference}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="paid_on">Fecha del pago</Label>
                                        <Input
                                            id="paid_on"
                                            type="date"
                                            className="h-11"
                                            value={data.paid_on}
                                            onChange={(e) => setData('paid_on', e.target.value)}
                                            aria-invalid={Boolean(errors.paid_on)}
                                        />
                                        {errors.paid_on && (
                                            <p className="text-xs text-destructive">{errors.paid_on}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="notes">Nota (opcional)</Label>
                                        <Textarea
                                            id="notes"
                                            rows={2}
                                            placeholder="Pagué desde otra cuenta, a nombre de…"
                                            value={data.notes}
                                            onChange={(e) => setData('notes', e.target.value)}
                                        />
                                    </div>

                                    <Button type="submit" className="h-11" disabled={processing}>
                                        {processing && <Loader2 className="animate-spin" />}
                                        Reportar mi pago
                                    </Button>
                                </form>
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">Elige un plan para ver el monto.</p>
                        )}
                    </CardContent>
                </Card>

                {(historial ?? []).length > 0 && (
                    <Card className="min-w-0">
                        <CardHeader>
                            <CardTitle className="text-base">Tus pagos</CardTitle>
                        </CardHeader>

                        <CardContent className="flex flex-col divide-y">
                            {historial.map((p) => (
                                <div key={p.id} className="flex items-center gap-3 py-3">
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm">{p.fecha}</span>
                                        <span className="block truncate font-mono text-xs text-muted-foreground">
                                            ref. {p.referencia ?? '—'}
                                        </span>
                                    </span>

                                    <span className="shrink-0 text-right">
                                        <span className="block font-mono text-sm tabular-nums">
                                            {formatearNumero(p.monto_bs, 2)} Bs
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className={
                                                p.estado === 'confirmado'
                                                    ? 'text-success'
                                                    : p.estado === 'rechazado'
                                                      ? 'text-destructive'
                                                      : 'text-warning'
                                            }
                                        >
                                            {p.estado}
                                        </Badge>
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
