import dayjs from 'dayjs';
import { formatearNumero, formatearUsd } from '@/Utils/Formatter';

/**
 * Arma el texto de la lista de precios para pegar (o enviar) por WhatsApp.
 *
 * Es la mitad de la promesa B del producto: «copio mi lista y la mando en 10 segundos». El
 * formato usa el markdown propio de WhatsApp (`*negrita*`, `_cursiva_`) para que se vea
 * arreglado sin que el usuario tenga que tocar nada — la lista se manda tal cual sale de
 * acá, sin pasar por un editor.
 *
 * El precio en bolívares va primero y el dólar entre paréntesis como referencia: es como se
 * lee en el mostrador y como ya se muestra en el panel — dos formatos para el mismo número
 * confundirían al cliente que recibe la lista.
 */
export function generarTextoWhatsApp({ negocio, productos }) {
    const porCategoria = productos.reduce((grupos, p) => {
        const clave = p.category || 'Otros';
        (grupos[clave] ??= []).push(p);

        return grupos;
    }, {});

    const fecha = dayjs().format('D [de] MMMM');

    const bloques = Object.entries(porCategoria).map(([categoria, items]) => {
        const lineas = items
            .map((p) => `• ${p.name} — ${formatearNumero(p.precio_bs, 2)} Bs (${formatearUsd(p.precio_usd)})`)
            .join('\n');

        // Con una sola categoría ("Otros", el caso común al empezar) el encabezado no
        // aporta nada y solo alarga el mensaje.
        return Object.keys(porCategoria).length > 1 ? `*${categoria.toUpperCase()}*\n${lineas}` : lineas;
    });

    return [
        `🧭 *${negocio?.name ?? 'Mis precios'}*`,
        `_Actualizado el ${fecha}_`,
        '',
        ...bloques,
        '',
        '_Precios sujetos a la tasa del día._',
    ].join('\n');
}

/**
 * Copia el texto al portapapeles.
 *
 * `navigator.clipboard` exige un contexto seguro (HTTPS) y puede no existir en un navegador
 * viejo o en un WebView restringido — nada raro con el público de Norte. Se usa
 * `document.execCommand` como respaldo en vez de simplemente fallar, para que copiar siga
 * funcionando en el celular de alguien con Android desactualizado.
 */
export async function copiarAlPortapapeles(texto) {
    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(texto);

            return true;
        } catch {
            // sigue al respaldo de abajo
        }
    }

    const area = document.createElement('textarea');
    area.value = texto;
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.focus();
    area.select();

    let copiado = false;

    try {
        copiado = document.execCommand('copy');
    } catch {
        copiado = false;
    }

    document.body.removeChild(area);

    return copiado;
}

/**
 * Abre WhatsApp con el texto ya cargado, listo para elegir a quién enviárselo.
 *
 * Sin número de destino a propósito: quien manda la lista es el negocio, y el destinatario
 * —un cliente puntual, un grupo, su propio estado— lo elige él en la app, no la herramienta.
 */
export function abrirWhatsApp(texto) {
    window.open(`https://wa.me/?text=${encodeURIComponent(texto)}`, '_blank', 'noopener');
}
