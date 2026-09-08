<?php

/*
 * Origen de la tasa oficial del día.
 *
 * El BCV no publica una API JSON propia: lo que hay son espejos públicos que la
 * republican. Por eso se configuran dos — si el principal no responde o devuelve algo que
 * no se puede leer, se intenta con el siguiente antes de darse por vencido.
 *
 * Va en `config/` y no en `env()` en runtime por la misma razón que `config/mikrotik.php`:
 * con `config:cache` en producción, `env()` devuelve null y el módulo deja de funcionar
 * sin decir por qué.
 */
return [
    /*
     * Cada proveedor declara de dónde sacar el número dentro del JSON de respuesta.
     * `path` es una ruta en notación de puntos para `data_get()`.
     */
    'providers' => [
        [
            'name' => 'DolarApi',
            'url' => env('EXCHANGE_RATE_URL', 'https://ve.dolarapi.com/v1/dolares/oficial'),
            'path' => 'promedio',
        ],
        [
            'name' => 'PyDolarVenezuela',
            'url' => env('EXCHANGE_RATE_FALLBACK_URL', 'https://pydolarve.org/api/v1/dollar?page=bcv'),
            'path' => 'monitors.usd.price',
        ],
    ],

    // Segundos de espera por proveedor. Corto: si no responde, se pasa al siguiente.
    'timeout' => (int) env('EXCHANGE_RATE_TIMEOUT', 10),

    /*
     * Tope de cordura. Si el proveedor devuelve un número fuera de este rango, se descarta
     * en vez de escribirlo: una tasa absurda mal leída del JSON reventaría todos los
     * precios en bolívares de la caja.
     */
    'min' => (float) env('EXCHANGE_RATE_MIN', 1),
    'max' => (float) env('EXCHANGE_RATE_MAX', 100000),
];
