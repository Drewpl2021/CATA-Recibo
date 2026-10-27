<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «Ajustes del sistema» para RR.HH., en toda instalación.
 *
 * La migración del 8 de octubre ya se lo daba, pero en una base donde el
 * permiso se quitó después (desmarcado en Roles) o donde el rol se llama
 * «RRHH» con mayúsculas, RR.HH. se quedaba sin la pantalla. Esta lo vuelve
 * a asegurar: busca el rol sin importar mayúsculas y, si ya lo tiene, no
 * hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $moduloId = DB::table('modulos')->where('ruta', '/ajustes')->value('id');
        $rrhhIds = DB::table('roles')->whereRaw('LOWER(nombre) IN (?, ?)', ['rrhh', 'rr.hh.'])->pluck('id');

        if (! $moduloId) {
            return;
        }

        foreach ($rrhhIds as $rolId) {
            if (! DB::table('rol_modulo')->where('rol_id', $rolId)->where('modulo_id', $moduloId)->exists()) {
                DB::table('rol_modulo')->insert(['rol_id' => $rolId, 'modulo_id' => $moduloId]);
            }
        }
    }

    public function down(): void
    {
        // No se quita: lo daba ya la migración del 8 de octubre.
    }
};
