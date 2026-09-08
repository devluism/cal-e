import dayjs from 'dayjs';
import objectSupport from 'dayjs/plugin/objectSupport';
import relativeTime from 'dayjs/plugin/relativeTime';
import updateLocale from 'dayjs/plugin/updateLocale';
import 'dayjs/locale/es';

dayjs.extend(objectSupport);
dayjs.extend(relativeTime);
dayjs.extend(updateLocale);
dayjs.locale('es');

const VOCALES = ['a', 'e', 'i', 'o', 'u'];

/* ---------------------------------------------------------------------------
   Dinero

   Todo lo monetario pasa por aquí. La versión anterior truncaba con
   `parseInt(valor * 100) / 100`, así que 1.999 se mostraba como 1.99 y el
   redondeo no cuadraba con lo que sumaba el backend. Aquí se redondea.
--------------------------------------------------------------------------- */

const numero = (valor, decimales = 2) =>
    new Intl.NumberFormat('es-VE', {
        maximumFractionDigits: decimales,
        minimumFractionDigits: decimales,
    }).format(Number(valor) || 0);

/** Precio en dólares: `$3,50`. */
export const formatearUsd = (valor) => `$${numero(valor)}`;

/** Cantidad en bolívares, sin sufijo (lo pone quien la muestra). */
export const formatearBs = (valor) => numero(valor);

export const aBolivares = (valorUsd, tasa) => (Number(valorUsd) || 0) * (Number(tasa) || 0);

/** Un total en ambas monedas, que es como se lee en el mostrador. */
export const formatearTotal = (valorUsd, tasa) => ({
    usd: formatearUsd(valorUsd),
    bs: `${formatearBs(aBolivares(valorUsd, tasa))} Bs`,
});

export const formatearNumero = (valor, decimales = 0) => numero(valor, decimales);

/** Cantidades de inventario: entera si lo es, con decimales solo cuando hacen falta. */
export const formatearCantidad = (valor) => {
    const n = Number(valor) || 0;

    return Number.isInteger(n) ? String(n) : numero(n, 3).replace(/,?0+$/, '');
};

export const formatearPorcentaje = (valor, conSufijo = true) =>
    conSufijo ? `${numero(valor)}%` : numero(valor);

/**
 * Calcula un precio de venta a partir del costo y el margen ya resuelto.
 *
 * Es una CALCULADORA para el formulario, no la forma de mostrar precios. Lo que se cobra
 * es siempre `products.price` (o el `price_override` de la variante): antes el catálogo
 * pintaba este cálculo mientras la caja cobraba la columna `price`, así que la etiqueta
 * del anaquel y el ticket podían no coincidir.
 *
 * El margen llega ya decidido por la cascada (producto → categoría → global), igual que
 * en `MargenService::para()` del backend. La versión anterior recibía dos márgenes y los
 * sumaba o restaba entre sí según el signo, una regla que no correspondía a ninguna
 * jerarquía explicable.
 */
export const calcularPrecio = (costo = 0, margen = 0) => {
    const base = Number(costo) || 0;
    const porcentaje = Number(margen) || 0;

    return porcentaje > 0 ? Math.round((base + (porcentaje / 100) * base) * 100) / 100 : base;
};

/* ---------------------------------------------------------------------------
   Texto
--------------------------------------------------------------------------- */

/** Pluraliza el nombre de una unidad, o usa su abreviatura si la tiene. */
export const formatearUnidad = (nombre, plural = false, abreviatura = null) => {
    if (abreviatura) return abreviatura;
    if (!nombre) return '';
    if (!plural) return nombre;

    return VOCALES.includes(nombre.slice(-1).toLowerCase()) ? `${nombre}s` : `${nombre}es`;
};

export const primerNombre = (nombre = '', mayusculas = false) => {
    const primero = String(nombre).split(' ')[0] ?? '';

    return mayusculas ? primero.toUpperCase() : primero;
};

/* ---------------------------------------------------------------------------
   Tiempos del Mikrotik

   El router devuelve duraciones como "1w2d3h4m5s"; esto las traduce a algo
   legible para la pantalla de tickets.
--------------------------------------------------------------------------- */

const partirTiempoMk = (texto) => {
    const partes = { days: 0, hours: 0, minutes: 0, seconds: 0 };
    const unidades = {
        w: ['days', 7],
        d: ['days', 1],
        h: ['hours', 1],
        m: ['minutes', 1],
        s: ['seconds', 1],
    };

    String(texto).replace(/(\d+)([wdhms])/g, (_, cantidad, unidad) => {
        const [campo, factor] = unidades[unidad];
        partes[campo] += Number(cantidad) * factor;

        return '';
    });

    return partes;
};

/** Momento en que empezó una sesión, a partir de su tiempo transcurrido. */
export const momentoDesdeUptime = (uptime) => {
    if (!uptime) return '';

    const { days, hours, minutes, seconds } = partirTiempoMk(uptime);

    return dayjs()
        .subtract(days, 'd')
        .subtract(hours, 'h')
        .subtract(minutes, 'm')
        .subtract(seconds, 's')
        .format('YYYY-MM-DD HH:mm:ss');
};

/** Duración legible de una sesión: "2 días", "3 horas". */
export const duracionDesdeUptime = (uptime) => {
    if (!uptime) return '';

    const { days, hours, minutes, seconds } = partirTiempoMk(uptime);

    return dayjs()
        .subtract(days, 'd')
        .subtract(hours, 'h')
        .subtract(minutes, 'm')
        .subtract(seconds, 's')
        .fromNow(true);
};

/** Traduce el límite de tiempo de un perfil del router a palabras. */
export const formatearLimite = (tiempo) => {
    if (!tiempo || tiempo === '0' || tiempo === '00:00:00') return 'Sin límite';

    const nombres = { w: 'semana', d: 'día', h: 'hora', m: 'minuto' };

    return String(tiempo)
        .replace(/(\d+)([wdhm])/g, (_, cantidad, unidad) => {
            const nombre = nombres[unidad];

            return `${cantidad} ${nombre}${Number(cantidad) > 1 ? 's' : ''} `;
        })
        .trim();
};

export const fechaCorta = (valor) => (valor ? dayjs(valor).format('DD/MM/YYYY') : '');

export const fechaRelativa = (valor) => (valor ? dayjs(valor).fromNow() : '');
