<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pone "Ajustes del sistema" en el menú de las bases ya instaladas. Solo
 * Admin, igual que Usuarios y Roles. En una instalación nueva lo siembra
 * ModuloSeeder. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('roles')->count() === 0) {
            echo "   base vacía: el menú lo siembra ModuloSeeder\n";
            return;
        }

        if (DB::table('modulos')->where('ruta', '/ajustes')->exists()) {
            echo "   Ajustes del sistema ya estaba en el menú\n";
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
            'nombre'          => 'Ajustes del sistema',
            'ruta'            => '/ajustes',
            'icono'           => 'settings',
            'orden'           => 12,
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
        $id = DB::table('modulos')->where('ruta', '/ajustes')->value('id');

        if ($id) {
            DB::table('rol_modulo')->where('modulo_id', $id)->delete();
            DB::table('modulos')->where('id', $id)->delete();
        }
    }
};
