<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pone "Auditoría" en el menú de las bases que ya estaban instaladas.
 *
 * En una instalación nueva lo siembra ModuloSeeder; esto es para las que ya
 * tienen su menú armado y no se vuelven a sembrar. Solo para Admin: es
 * justamente donde se ve lo que hizo RR.HH.
 *
 * Idempotente: si ya está, no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('roles')->count() === 0) {
            echo "   base vacía: el menú lo siembra ModuloSeeder\n";
            return;
        }

        if (DB::table('modulos')->where('ruta', '/auditoria')->exists()) {
            echo "   Auditoría ya estaba en el menú\n";
            return;
        }

        $padreId = DB::table('modulo_padre')->where('nombre', 'Configuración')->value('id');
        if (! $padreId) {
            echo "   no hay grupo Configuración: se deja para ModuloSeeder\n";
            return;
        }

        $ahora    = now();
        $moduloId = (string) Str::uuid7();

        DB::table('modulos')->insert([
            'id'              => $moduloId,
            'modulo_padre_id' => $padreId,
            'nombre'          => 'Auditoría',
            'ruta'            => '/auditoria',
            'icono'           => 'clock',
            'orden'           => 10,
            'created_at'      => $ahora,
            'updated_at'      => $ahora,
        ]);

        $admin = DB::table('roles')->where('nombre', 'admin')->value('id');
        if ($admin) {
            DB::table('rol_modulo')->insert(['rol_id' => $admin, 'modulo_id' => $moduloId]);
        }
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('ruta', '/auditoria')->value('id');

        if ($id) {
            DB::table('rol_modulo')->where('modulo_id', $id)->delete();
            DB::table('modulos')->where('id', $id)->delete();
        }
    }
};
