<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pone "Tipos de Contrato" en el menú de las bases que ya estaban instaladas.
 *
 * En una instalación nueva lo siembra ModuloSeeder; esto es para las que ya
 * tienen su menú armado y no se vuelven a sembrar. Mismo grupo y mismos
 * roles que Áreas, Cargos y Sedes: RR.HH. administra el catálogo.
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

        if (DB::table('modulos')->where('ruta', '/tipos-contrato')->exists()) {
            echo "   Tipos de Contrato ya estaba en el menú\n";
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
            'nombre'          => 'Tipos de Contrato',
            'ruta'            => '/tipos-contrato',
            'icono'           => 'clipboard_check',
            'orden'           => 3,
            'created_at'      => $ahora,
            'updated_at'      => $ahora,
        ]);

        $roles = DB::table('roles')->whereIn('nombre', ['admin', 'rrhh'])->pluck('id');
        foreach ($roles as $rolId) {
            DB::table('rol_modulo')->insert(['rol_id' => $rolId, 'modulo_id' => $moduloId]);
        }
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('ruta', '/tipos-contrato')->value('id');

        if ($id) {
            DB::table('rol_modulo')->where('modulo_id', $id)->delete();
            DB::table('modulos')->where('id', $id)->delete();
        }
    }
};
