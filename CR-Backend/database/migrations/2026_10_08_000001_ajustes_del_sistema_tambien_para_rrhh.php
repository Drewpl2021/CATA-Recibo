<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «Ajustes del sistema» también para RR.HH. en los sistemas ya instalados:
 * es quien arma las planillas y quien sabe la UIT y los % de cada año. En
 * una base vacía lo siembra ModuloSeeder, como al resto.
 *
 * Usuarios, Roles, Módulos y Auditoría siguen siendo solo del administrador.
 */
return new class extends Migration
{
    public function up(): void
    {
        $moduloId = DB::table('modulos')->where('ruta', '/ajustes')->value('id');
        $rrhhId = DB::table('roles')->where('nombre', 'rrhh')->value('id');

        if (! $moduloId || ! $rrhhId) {
            return;
        }

        if (! DB::table('rol_modulo')->where('rol_id', $rrhhId)->where('modulo_id', $moduloId)->exists()) {
            DB::table('rol_modulo')->insert(['rol_id' => $rrhhId, 'modulo_id' => $moduloId]);
        }
    }

    public function down(): void
    {
        $moduloId = DB::table('modulos')->where('ruta', '/ajustes')->value('id');
        $rrhhId = DB::table('roles')->where('nombre', 'rrhh')->value('id');

        if ($moduloId && $rrhhId) {
            DB::table('rol_modulo')->where('rol_id', $rrhhId)->where('modulo_id', $moduloId)->delete();
        }
    }
};
