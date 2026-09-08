import { useMemo, useState } from 'react';

/**
 * Ordenamiento client-side para las tablas de shadcn (que, a diferencia de las de Ant Design,
 * no traen `sorter` incorporado). Se mantiene chico a propósito: para tres tablas de catálogo
 * no vale la pena sumar TanStack Table como dependencia.
 */
export function useSortable(data = [], defaultKey = null) {
    const [sort, setSort] = useState({ key: defaultKey, dir: 'asc' });

    const sorted = useMemo(() => {
        if (!sort.key) {
            return data;
        }

        return [...data].sort((a, b) => {
            const valorA = a?.[sort.key];
            const valorB = b?.[sort.key];

            if (valorA == null) return 1;
            if (valorB == null) return -1;

            const resultado =
                typeof valorA === 'number' && typeof valorB === 'number'
                    ? valorA - valorB
                    : String(valorA).localeCompare(String(valorB), 'es', { numeric: true });

            return sort.dir === 'asc' ? resultado : -resultado;
        });
    }, [data, sort]);

    const toggleSort = (key) =>
        setSort((prev) =>
            prev.key === key ? { key, dir: prev.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: 'asc' },
        );

    return { sorted, sort, toggleSort };
}
