<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pone «Portal Web» en el menú de las bases ya instaladas: el panel donde se
 * edita el contenido de cata.edu.pe.
 *
 * Va en su propio grupo y al FINAL del menú (orden = el último + 1), para no
 * mover ninguno de los grupos que ya existen. Solo Admin: la puerta del
 * panel (Gate 'editar-portal') hoy la abre solo él, y mostrarle el módulo a
 * quien no puede usarlo sería ofrecerle una pantalla que le responde 403.
 *
 * En una instalación nueva lo siembra ModuloSeeder. Idempotente.
 */
return new class extends Migration
{
    private const RUTA = '/portal-web';
    private const GRUPO = 'Portal Web';

    public function up(): void
    {
        // Con migrate:fresh esto corre antes de los seeders, sin roles: el
        // menú lo arma ModuloSeeder, que ya lo incluye.
        if (DB::table('roles')->count() === 0) {
            echo "   base vacía: el menú lo siembra ModuloSeeder\n";
            return;
        }

        if (DB::table('modulos')->where('ruta', self::RUTA)->exists()) {
            echo "   Portal Web ya estaba en el menú\n";
            return;
        }

        $ahora = now();
        $padreId = DB::table('modulo_padre')->where('nombre', self::GRUPO)->value('id');

        if (! $padreId) {
            $padreId = (string) Str::uuid7();
            DB::table('modulo_padre')->insert([
                'id'         => $padreId,
                'nombre'     => self::GRUPO,
                'icono'      => 'layers',
                'orden'      => (int) DB::table('modulo_padre')->max('orden') + 1,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $moduloId = (string) Str::uuid7();
        DB::table('modulos')->insert([
            'id'              => $moduloId,
            'modulo_padre_id' => $padreId,
            'nombre'          => 'Contenido del portal',
            'ruta'            => self::RUTA,
            'icono'           => 'layers',
            'orden'           => 1,
            'created_at'      => $ahora,
            'updated_at'      => $ahora,
        ]);

        $admin = DB::table('roles')->where('nombre', 'admin')->value('id');
        if ($admin) {
            DB::table('rol_modulo')->insert(['rol_id' => $admin, 'modulo_id' => $moduloId]);
        }

        echo "   Portal Web agregado al menú del Administrador\n";
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('ruta', self::RUTA)->value('id');

        if ($id) {
            DB::table('rol_modulo')->where('modulo_id', $id)->delete();
            DB::table('modulos')->where('id', $id)->delete();
        }

        // El grupo solo se va si quedó vacío.
        $padreId = DB::table('modulo_padre')->where('nombre', self::GRUPO)->value('id');

        if ($padreId && ! DB::table('modulos')->where('modulo_padre_id', $padreId)->exists()) {
            DB::table('modulo_padre')->where('id', $padreId)->delete();
        }
    }
};
