<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CorsMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(CorsMiddleware::class);
        $middleware->alias([
            'rol'         => \App\Http\Middleware\CheckRol::class,
            'sesion'      => \App\Http\Middleware\RenovarSesionActiva::class,
            // Traba la cuenta que todavía usa la contraseña que le dieron.
            'clave_nueva' => \App\Http\Middleware\ExigirCambioPassword::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();