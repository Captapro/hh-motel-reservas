<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detrás de Render (y cualquier proxy/balanceador), Laravel recibe
        // la conexión interna como HTTP plano -- sin confiar en el proxy,
        // genera todos los links (assets, formularios) con http:// aunque
        // el visitante haya entrado por https://, y el navegador bloquea
        // esos recursos como "Mixed Content".
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'mucama' => \App\Http\Middleware\EnsureMucamaSession::class,
        ]);

        // Global (no solo en rutas 'auth'): así una cuenta desactivada a
        // mitad de sesión pierde el acceso en el siguiente click, sin
        // depender de que cada grupo de rutas la incluya a mano.
        $middleware->web(append: [\App\Http\Middleware\EnsureUserIsActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
