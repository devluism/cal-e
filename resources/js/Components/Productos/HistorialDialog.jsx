import { useEffect, useState } from 'react';
import axios from 'axios';
import { Line, LineChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import { toast } from 'sonner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/Components/ui/chart';
import { Skeleton } from '@/Components/ui/skeleton';
import { formatearNumero } from '@/Utils/Formatter';

const CONFIG = {
    precio_bs: { label: 'Precio (Bs)', color: 'var(--primary)' },
};

/**
 * La evolución del precio de un producto, día a día.
 *
 * Es la prueba de la promesa central: «tus precios se ajustan solos». Sin este gráfico esa
 * frase es texto en la pantalla de entrada; con él, el usuario ve la línea moverse sola con
 * cada foto que tomó `precios:snapshot` — no hay que creerle a la herramienta, se ve.
 *
 * Se pide por fetch al abrir el diálogo, no junto con el resto del catálogo: la mayoría de
 * las veces que se ve la lista de productos nadie va a abrir el historial de ninguno, y
 * pedir treinta días de cada producto por adelantado sería trabajo que casi nunca se usa.
 */
export default function HistorialDialog({ abierto, onOpenChange, producto }) {
    const [cargando, setCargando] = useState(true);
    const [historial, setHistorial] = useState([]);

    useEffect(() => {
        if (!abierto || !producto) return;

        setCargando(true);

        axios
            .get(route('productos.historial', producto.id))
            .then(({ data }) => setHistorial(data.historial))
            .catch(() => toast.error('No se pudo cargar el historial de este producto.'))
            .finally(() => setCargando(false));
    }, [abierto, producto?.id]);

    const datos = historial.map((punto) => ({
        ...punto,
        etiqueta: new Date(`${punto.fecha}T00:00:00`).toLocaleDateString('es-VE', {
            day: 'numeric',
            month: 'short',
        }),
    }));

    return (
        <Dialog open={abierto} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Historial de «{producto?.name}»</DialogTitle>
                    <DialogDescription>
                        El precio en bolívares de los últimos 30 días, con la tasa de cada día.
                    </DialogDescription>
                </DialogHeader>

                {cargando ? (
                    <Skeleton className="aspect-video w-full" />
                ) : datos.length < 2 ? (
                    <p className="py-8 text-center text-sm text-muted-foreground">
                        Todavía no hay suficiente historial. Vuelve mañana: la foto de hoy ya
                        quedó guardada.
                    </p>
                ) : (
                    <ChartContainer config={CONFIG}>
                        <LineChart data={datos} margin={{ left: 8, right: 8 }}>
                            <CartesianGrid vertical={false} />
                            <XAxis
                                dataKey="etiqueta"
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                minTickGap={24}
                            />
                            <YAxis
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                width={56}
                                tickFormatter={(valor) => formatearNumero(valor, 0)}
                            />
                            <ChartTooltip content={<ChartTooltipContent />} />
                            <Line
                                dataKey="precio_bs"
                                type="monotone"
                                stroke="var(--color-precio_bs)"
                                strokeWidth={2}
                                dot={false}
                            />
                        </LineChart>
                    </ChartContainer>
                )}
            </DialogContent>
        </Dialog>
    );
}
