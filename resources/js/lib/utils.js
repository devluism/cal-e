import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Combina clases condicionales (clsx) y resuelve conflictos de Tailwind (tailwind-merge).
 * Es el helper estándar de shadcn/ui: todos los componentes de Components/ui lo usan.
 */
export function cn(...inputs) {
    return twMerge(clsx(inputs));
}
