<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModuloSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar antes de sembrar
        DB::table('rol_modulo')->delete();
        DB::table('modulos')->delete();
        DB::table('modulo_padre')->delete();

        // Roles
        $admin   = DB::table('roles')->where('nombre', 'admin')->value('id');
        $rrhh    = DB::table('roles')->where('nombre', 'rrhh')->value('id');
        $empleado = DB::table('roles')->where('nombre', 'empleado')->value('id');

        // ── Módulos Padre ─────────────────────────────────
        $idBoletas  = Str::uuid()->toString();
        $idConfig   = Str::uuid()->toString();
        $idEspacio  = Str::uuid()->toString();

        DB::table('modulo_padre')->insert([
            ['id' => $idBoletas, 'nombre' => 'Boletas y Finanzas', 'icono' => 'receipt',      'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $idConfig,  'nombre' => 'Configuración',      'icono' => 'settings',     'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $idEspacio, 'nombre' => 'Mi Espacio',         'icono' => 'person',       'orden' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── Módulos hijos ─────────────────────────────────
        $modulos = [
            // Boletas y Finanzas — admin y rrhh
            ['padre' => $idBoletas, 'nombre' => 'Empleados',  'ruta' => '/empleados',  'icono' => 'people',        'orden' => 1, 'roles' => [$admin, $rrhh]],
            ['padre' => $idBoletas, 'nombre' => 'Planillas',  'ruta' => '/planillas',  'icono' => 'table_chart',   'orden' => 2, 'roles' => [$admin, $rrhh]],
            ['padre' => $idBoletas, 'nombre' => 'Boletas',    'ruta' => '/boletas',    'icono' => 'description',   'orden' => 3, 'roles' => [$admin, $rrhh]],
            ['padre' => $idBoletas, 'nombre' => 'Descuentos', 'ruta' => '/descuentos', 'icono' => 'remove_circle', 'orden' => 4, 'roles' => [$admin, $rrhh]],
            ['padre' => $idBoletas, 'nombre' => 'Documentos', 'ruta' => '/documentos', 'icono' => 'folder',        'orden' => 5, 'roles' => [$admin, $rrhh]],

            // Configuración — solo admin
            ['padre' => $idConfig, 'nombre' => 'Áreas',          'ruta' => '/areas',          'icono' => 'domain',      'orden' => 1, 'roles' => [$admin]],
            ['padre' => $idConfig, 'nombre' => 'Cargos',         'ruta' => '/cargos',         'icono' => 'badge',       'orden' => 2, 'roles' => [$admin]],
            ['padre' => $idConfig, 'nombre' => 'Roles',          'ruta' => '/roles',          'icono' => 'admin_panel_settings', 'orden' => 3, 'roles' => [$admin]],
            ['padre' => $idConfig, 'nombre' => 'Periodos',       'ruta' => '/periodos',       'icono' => 'date_range',  'orden' => 4, 'roles' => [$admin]],
            ['padre' => $idConfig, 'nombre' => 'Sedes',          'ruta' => '/sedes',          'icono' => 'location_on', 'orden' => 5, 'roles' => [$admin]],
            ['padre' => $idConfig, 'nombre' => 'Módulos',        'ruta' => '/modulos',        'icono' => 'view_module', 'orden' => 6, 'roles' => [$admin]],
            ['padre' => $idConfig, 'nombre' => 'Módulos Padre',  'ruta' => '/modulos-padre',  'icono' => 'folder_open', 'orden' => 7, 'roles' => [$admin]],

            // Mi Espacio — solo empleado
            ['padre' => $idEspacio, 'nombre' => 'Mis Boletas',    'ruta' => '/mis-boletas',    'icono' => 'receipt_long', 'orden' => 1, 'roles' => [$empleado]],
            ['padre' => $idEspacio, 'nombre' => 'Mis Documentos', 'ruta' => '/mis-documentos', 'icono' => 'folder_shared', 'orden' => 2, 'roles' => [$empleado]],
        ];

        foreach ($modulos as $m) {
            $moduloId = Str::uuid()->toString();
            DB::table('modulos')->insert([
                'id'              => $moduloId,
                'modulo_padre_id' => $m['padre'],
                'nombre'          => $m['nombre'],
                'ruta'            => $m['ruta'],
                'icono'           => $m['icono'],
                'orden'           => $m['orden'],
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            foreach ($m['roles'] as $rolId) {
                DB::table('rol_modulo')->insert([
                    'rol_id'    => $rolId,
                    'modulo_id' => $moduloId,
                ]);
            }
        }
    }
}