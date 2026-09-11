<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // La contraseña: 8 caracteres con al menos una letra y un número. Antes
        // solo se pedía el largo, y "12345678" o "aaaaaaaa" pasaban. Es la
        // regla que usan el cambio de contraseña y la recuperación; el
        // frontend la replica para avisar antes de guardar.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        $this->frenosDeLasPuertasAbiertas();
    }

    /**
     * Un contador para cada puerta pública, y no uno solo para todas.
     *
     * Sin esto, `throttle:5,1` a secas comparte contador entre TODAS las rutas
     * públicas: Laravel, cuando no hay sesión, arma la clave con el dominio y
     * la IP, sin mirar la URL. O sea que a quien erraba su contraseña unas
     * cuantas veces y después pedía el enlace para reponerla, el sistema le
     * respondía "Too Many Attempts" en una ruta que ni había tocado — y se
     * quedaba sin las dos salidas a la vez.
     *
     * Nombrando el limitador, cada uno lleva su propia cuenta por IP.
     */
    private function frenosDeLasPuertasAbiertas(): void
    {
        // Probar contraseñas a lo bruto contra /login.
        RateLimiter::for('ingreso', fn (Request $peticion) => Limit::perMinute(8)->by($peticion->ip()));

        // Crear cuentas en masa desde el autoregistro.
        RateLimiter::for('registro', fn (Request $peticion) => Limit::perMinute(5)->by($peticion->ip()));

        // Pedir enlaces de reposición en cadena (y llenarle el buzón a alguien).
        RateLimiter::for('recuperacion', fn (Request $peticion) => Limit::perMinute(5)->by($peticion->ip()));

        // Consultar DNIs. Cada uno que no esté en la base propia se le paga a
        // Decolecta, así que se cuenta por usuario: 30 por minuto es más de
        // lo que da de alta RR.HH. en una mañana, y corta un bucle.
        RateLimiter::for('consulta_dni', fn (Request $peticion) => Limit::perMinute(30)
            ->by($peticion->user()?->id ?: $peticion->ip()));
    }
}
