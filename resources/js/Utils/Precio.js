/**
 * Espejo en JavaScript de `App\Services\PrecioService`.
 *
 * **El servidor es la autoridad.** Esto existe solo para que el formulario muestre el precio
 * mientras el usuario teclea: pedirle al servidor en cada pulsación sería lento con la señal
 * que tiene este público, y ver el número aparecer *mientras* escribe es justamente el
 * momento en que entiende para qué sirve la herramienta. Lo que se guarda y lo que se
 * exporta lo calcula siempre `PrecioService`.
 *
 * Si se toca la fórmula de un lado, hay que tocarla del otro. Es el mismo trato que en IGA
 * con `calcularPrecio()` de `Formatter.js`, y la razón por la que ambas versiones llevan
 * este aviso: dos fórmulas que discrepan producen dos precios distintos para el mismo
 * producto, y lo único que este producto tiene es la confianza del usuario.
 */

/** Ancla el costo a dólares. Un costo en Bs sin su tasa no significa nada: devuelve 0. */
export function costoEnDolares({ costo, moneda, tasaCosto }) {
    const monto = Number(costo) || 0;

    if (moneda !== 'VES') {
        return monto;
    }

    const tasa = Number(tasaCosto) || 0;

    return tasa > 0 ? monto / tasa : 0;
}

/**
 * Redondea hacia ARRIBA al múltiplo del negocio.
 *
 * Hacia abajo se estaría comiendo el margen que este producto existe para proteger.
 */
export function redondear(monto, redondeo) {
    const paso = Number(redondeo) || 0;

    if (paso <= 0) {
        return Math.round(monto * 100) / 100;
    }

    return Math.round(Math.ceil(monto / paso) * paso * 100) / 100;
}

/**
 * El precio de venta con la tasa de hoy.
 *
 * @returns {{costoUsd: number, precioUsd: number, precioBs: number}}
 */
export function calcularPrecio({ costo, moneda, tasaCosto, margen, tasa, redondeo = 0 }) {
    const costoUsd = costoEnDolares({ costo, moneda, tasaCosto });
    const precioUsd = Math.round(costoUsd * (1 + (Number(margen) || 0) / 100) * 100) / 100;

    return {
        costoUsd: Math.round(costoUsd * 100) / 100,
        precioUsd,
        precioBs: redondear(precioUsd * (Number(tasa) || 0), redondeo),
    };
}
