<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * El módulo «Gratificaciones» en el menú (Boletas y Finanzas), para RR.HH. y
 * Administración, en los sistemas ya instalados. En una base vacía lo siembra
 * ModuloSeeder, como al resto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('roles')->count() === 0) {
            echo "   base vacía: el menú lo siembra ModuloSeeder\n";
            return;
        }

        if (DB::table('modulos')->where('ruta', '/gratificaciones')->exists()) {
            return;
        }

        $padreId = DB::table('modulo_padre')->where('nombre', 'Boletas y Finanzas')->value('id')
            ?? DB::table('modulos')->where('ruta', '/cts')->value('modulo_padre_id');
        if (! $padreId) {
            echo "   no se encontró «Boletas y Finanzas»: agrega el módulo desde Módulos\n";
            return;
        }

        $ahora = now();
        $moduloId = (string) Str::uuid();
        DB::table('modulos')->insert([
            'id'              => $moduloId,
            'modulo_padre_id' => $padreId,
            'nombre'          => 'Gratificaciones',
            'ruta'            => '/gratificaciones',
            'icono'           => 'regalo',
            'orden'           => 9,
            'created_at'      => $ahora,
            'updated_at'      => $ahora,
        ]);

        foreach (DB::table('roles')->whereIn('nombre', ['admin', 'rrhh'])->pluck('id') as $rolId) {
            DB::table('rol_modulo')->insert(['rol_id' => $rolId, 'modulo_id' => $moduloId]);
        }
    }

    public function down(): void
    {
        $moduloId = DB::table('modulos')->where('ruta', '/gratificaciones')->value('id');

        if ($moduloId) {
            DB::table('rol_modulo')->where('modulo_id', $moduloId)->delete();
            DB::table('modulos')->where('id', $moduloId)->delete();
        }
    }
};
