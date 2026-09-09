<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Los tokens caducados (Sanctum los da por 24 h) se quedaban para siempre en
 * personal_access_tokens: la tabla solo crecía. Se purgan a diario los que
 * llevan más de 48 h sin usarse — el doble de su vida útil, para no borrar
 * ninguno que todavía sirva.
 *
 * Requiere que el scheduler esté corriendo:
 *   * * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
 */
Schedule::command('sanctum:prune-expired --hours=48')->daily();
