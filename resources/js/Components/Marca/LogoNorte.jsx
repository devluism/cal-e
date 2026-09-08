import { cn } from '@/lib/utils';

/**
 * Isotipo de Norte: la aguja de la brújula.
 *
 * La forma es una aguja asimétrica, no un rombo: la mitad norte es más larga y afilada, la
 * sur más corta y ancha. Esa asimetría es lo que la hace leerse como aguja y no como
 * diamante, y además da la sensación de que está señalando.
 *
 * La punta norte va en ámbar (el color de marca) y la sur en un tono apagado — el mismo
 * código visual de cualquier brújula real, que es lo que hace que se entienda sin
 * explicación.
 */
export function IsotipoNorte({ className, ...props }) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            role="img"
            aria-label="Norte"
            className={cn('size-6', className)}
            {...props}
        >
            {/* Mitad sur: apagada, más corta y ancha. */}
            <path d="M12 22.5 L16.6 13 L7.4 13 Z" className="fill-current opacity-35" />
            {/* Mitad norte: la punta que señala. */}
            <path d="M12 1.5 L16.6 13 L7.4 13 Z" className="fill-current" />
        </svg>
    );
}

/**
 * Logotipo completo: isotipo + palabra.
 *
 * `tono` decide de qué color sale la aguja. En superficies oscuras (login, sidebar) va en
 * ámbar; sobre fondo claro puede heredar el color del texto para no chillar.
 */
export default function LogoNorte({ className, tono = 'marca', mostrarTexto = true, ...props }) {
    return (
        <span className={cn('inline-flex items-center gap-2', className)} {...props}>
            <IsotipoNorte
                className={cn('size-7 shrink-0', tono === 'marca' ? 'text-primary' : 'text-current')}
            />

            {mostrarTexto && (
                <span className="text-xl font-semibold tracking-tight">
                    Norte
                </span>
            )}
        </span>
    );
}
