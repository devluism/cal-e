<?php

use Illuminate\Support\Facades\Schedule;

/*
 * La tasa oficial se publica en días hábiles a media tarde. Se consulta dos veces y no una,
 * para que un fallo puntual del proveedor no deje a toda la plataforma calculando con la
 * tasa de ayer. El servicio no duplica filas si el valor no cambió.
 *
 * En desarrollo: `php artisan schedule:work`.
 * En producción: una tarea del sistema que ejecute `php artisan schedule:run` cada minuto.
 */
Schedule::command('tasa:sync')->weekdays()->at('16:30')->withoutOverlapping();
Schedule::command('tasa:sync')->weekdays()->at('18:00')->withoutOverlapping();

/*
 * Una corrida temprano: el usuario abre la app en la mañana para mandar su lista, y para
 * entonces la tasa del día hábil anterior ya tiene que estar cargada.
 */
Schedule::command('tasa:sync')->dailyAt('07:00')->withoutOverlapping();
