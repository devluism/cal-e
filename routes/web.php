<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\SuscripcionController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas de Norte.
 *
 * Mismo criterio que en IGA: las rutas estáticas van antes de las que llevan `{id}`, que
 * si no se las tragan como si fueran un identificador.
 */

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'mostrarLogin'])->name('inicio');

    Route::get('/entrar', [AuthController::class, 'mostrarLogin'])->name('login');
    Route::post('/entrar', [AuthController::class, 'entrar']);

    Route::get('/crear-cuenta', [AuthController::class, 'mostrarRegistro'])->name('register');
    Route::post('/crear-cuenta', [AuthController::class, 'registrar']);

    // Entrada con Google: la ruta existe para que el botón no quede muerto, pero avisa.
    Route::get('/oauth/{proveedor}', [AuthController::class, 'oauth'])->name('oauth.redirect');

    // Recuperación de clave: pendiente, pero el enlace del login no puede apuntar a nada.
    Route::get('/recuperar', fn () => redirect()->route('login')->with(
        'status', 'La recuperación de contraseña todavía no está lista. Escríbenos y te ayudamos.'
    ))->name('password.request');
});

Route::middleware('auth')->group(function () {
    Route::post('/salir', [AuthController::class, 'salir'])->name('logout');

    /*
     * Suscripción. NO lleva `suscrito`: es justo la pantalla a la que hay que poder llegar
     * cuando la suscripción venció — protegerla dejaría al cliente sin forma de pagar.
     */
    Route::controller(SuscripcionController::class)->prefix('suscripcion')->group(function () {
        Route::get('/', 'index')->name('suscripcion');
        Route::get('/cobro/{planId}', 'cobro')->name('suscripcion.cobro');
        Route::post('/reportar', 'reportar')->name('suscripcion.reportar');
    });

    // El valor vivo: precio recalculado con la tasa de hoy. Esto es lo que se paga.
    Route::middleware('suscrito')->group(function () {
        Route::get('/panel', [PanelController::class, 'index'])->name('panel');
    });
});
