<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequiereSuscripcion;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        /*
         * A dónde manda cada middleware. Sin esto Laravel usa sus rutas por defecto
         * (`/login`, `/dashboard`), que en Norte no existen: quien ya tenía sesión y volvía
         * a /entrar caía en un bucle de redirecciones.
         */
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('panel'));

        // `->middleware('suscrito')` en lo que se paga. Ver RequiereSuscripcion.
        $middleware->alias(['suscrito' => RequiereSuscripcion::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
