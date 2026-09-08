import { useCallback, useEffect, useState } from 'react';

const STORAGE_KEY = 'norte-theme';

/**
 * Modo claro/oscuro por clase `.dark` en <html>, persistido en localStorage.
 *
 * **Norte arranca en oscuro.** No es capricho: la marca se diseñó sobre azul medianoche —el
 * ámbar de la aguja es lo que se lee bien encima— y el público la usa en la calle, muchas
 * veces de noche cerrando el negocio. Quien prefiera claro lo cambia y su elección manda de
 * ahí en adelante.
 *
 * El tema real lo aplica un script en línea del blade ANTES de que React monte (ver
 * `app.blade.php`). Si se aplicara acá, en el primer render la página se vería con el tema
 * equivocado y parpadearía — y ese parpadeo, en la pantalla de entrada, es lo primero que
 * ve un usuario nuevo.
 */
export function leerTema() {
    if (typeof window === 'undefined') {
        return 'dark';
    }

    const guardado = window.localStorage.getItem(STORAGE_KEY);

    return guardado === 'light' || guardado === 'dark' ? guardado : 'dark';
}

function aplicar(tema) {
    document.documentElement.classList.toggle('dark', tema === 'dark');
    document.documentElement.style.colorScheme = tema;
}

export function useTheme() {
    const [theme, setThemeState] = useState(leerTema);

    useEffect(() => {
        aplicar(theme);
        window.localStorage.setItem(STORAGE_KEY, theme);
    }, [theme]);

    const setTheme = useCallback((next) => setThemeState(next), []);
    const toggleTheme = useCallback(
        () => setThemeState((prev) => (prev === 'dark' ? 'light' : 'dark')),
        [],
    );

    return { theme, setTheme, toggleTheme };
}
