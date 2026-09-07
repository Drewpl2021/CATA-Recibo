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
        $idInicio   = Str::uuid()->toString();
        $idBoletas  = Str::uuid()->toString();
        $idConfig   = Str::uuid()->toString();
        $idEspacio  = Str::uuid()->toString();

        DB::table('modulo_padre')->insert([
            // Va primero: es la pantalla en la que se cae al entrar.
            ['id' => $idInicio,  'nombre' => 'Dashboard',          'icono' => 'dashboard',    'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $idBoletas, 'nombre' => 'Boletas y Finanzas', 'icono' => 'receipt',      'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $idConfig,  'nombre' => 'Configuración',      'icono' => 'settings',     'orden' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $idEspacio, 'nombre' => 'Mi Espacio',         'icono' => 'person',       'orden' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── Módulos hijos ─────────────────────────────────
        $modulos = [
            // Dashboard — el resumen del mes. Solo admin y RRHH: sus cifras son
            // de toda la nómina (masa salarial, contratos por vencer), nada que
            // deba ver un docente. Al empleado se le manda a Mis Boletas.
            ['padre' => $idInicio, 'nombre' => 'Panel de Control', 'ruta' => '/dashboard', 'icono' => 'dashboard', 'orden' => 1, 'roles' => [$admin, $rrhh]],

            // Boletas y Finanzas — admin y rrhh
            ['padre' => $idBoletas, 'nombre' => 'Empleados',  'ruta' => '/empleados',  'icono' => 'people',        'orden' => 1, 'roles' => [$admin, $rrhh]],
            ['padre' => $idBoletas, 'nombre' => 'Planillas',  'ruta' => '/planillas',  'icono' => 'table_chart',   'orden' => 2, 'roles' => [$admin, $rrhh]],
            ['padre' => $idBoletas, 'nombre' => 'Boletas',    'ruta' => '/boletas',    'icono' => 'description',   'orden' => 3, 'roles' => [$admin, $rrhh]],
            ['padre' => $idBoletas, 'nombre' => 'Documentos', 'ruta' => '/documentos', 'icono' => 'folder',        'orden' => 4, 'roles' => [$admin, $rrhh]],
            ['padre' => $idBoletas, 'nombre' => 'Contratos',  'ruta' => '/contratos',  'icono' => 'description',   'orden' => 5, 'roles' => [$admin, $rrhh]],

            // Configuración — RRHH tiene los catálogos; Roles/Módulos siguen
            // siendo exclusivos de Admin (igual que el middleware rol: en routes/api.php).
            ['padre' => $idConfig, 'nombre' => 'Áreas',          'ruta' => '/areas',          'icono' => 'domain',      'orden' => 1, 'roles' => [$admin, $rrhh]],
            ['padre' => $idConfig, 'nombre' => 'Cargos',         'ruta' => '/cargos',         'icono' => 'badge',       'orden' => 2, 'roles' => [$admin, $rrhh]],
            ['padre' => $idConfig, 'nombre' => 'Sedes',          'ruta' => '/sedes',          'icono' => 'location_on', 'orden' => 3, 'roles' => [$admin, $rrhh]],
            ['padre' => $idConfig, 'nombre' => 'Periodos',       'ruta' => '/periodos',       'icono' => 'date_range',  'orden' => 4, 'roles' => [$admin, $rrhh]],
            ['padre' => $idConfig, 'nombre' => 'Conceptos de Pago', 'ruta' => '/conceptos-pago', 'icono' => 'money',       'orden' => 5, 'roles' => [$admin, $rrhh]],
            ['padre' => $idConfig, 'nombre' => 'Usuarios',       'ruta' => '/usuarios',       'icono' => 'people',      'orden' => 6, 'roles' => [$admin]],
            ['padre' => $idConfig, 'nombre' => 'Roles',          'ruta' => '/roles',          'icono' => 'shield',      'orden' => 7, 'roles' => [$admin]],
            ['padre' => $idConfig, 'nombre' => 'Módulos',        'ruta' => '/modulos',        'icono' => 'view_module', 'orden' => 8, 'roles' => [$admin]],
            ['padre' => $idConfig, 'nombre' => 'Módulos Padre',  'ruta' => '/modulos-padre',  'icono' => 'folder_open', 'orden' => 9, 'roles' => [$admin]],

            // Mi Espacio — admin, rrhh y empleado
            ['padre' => $idEspacio, 'nombre' => 'Mis Boletas',    'ruta' => '/mis-boletas',    'icono' => 'receipt_long',  'orden' => 1, 'roles' => [$admin, $rrhh, $empleado]],
            ['padre' => $idEspacio, 'nombre' => 'Mis Documentos', 'ruta' => '/mis-documentos', 'icono' => 'folder_shared', 'orden' => 2, 'roles' => [$admin, $rrhh, $empleado]],
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