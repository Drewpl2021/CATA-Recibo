<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Rol;

/**
 * Los tres roles con los que funciona el sistema.
 *
 * El nombre va en minúscula A PROPÓSITO: no es una etiqueta, es la llave con
 * la que el backend decide quién puede hacer qué. Está escrita tal cual en
 * los `rol:admin` de routes/api.php y en comparaciones como
 * `$rol?->nombre !== 'rrhh'` repartidas por los controladores. Cambiar
 * "rrhh" por "RRHH" aquí no cambiaría el texto de la pantalla: dejaría a
 * Recursos Humanos sin permisos de un día para otro.
 *
 * Lo que el usuario lee ("Admin", "RRHH", "Empleado") sale del frontend, en
 * shared/constants/roles.constants.ts, que traduce esta llave a su etiqueta.
 * Es la separación de siempre: un valor para la máquina y otro para la
 * persona.
 *
 * La descripción sí es para leerse: sale en la pantalla de Roles, que antes
 * la mostraba vacía.
 */
class RolSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Rol::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $roles = [
            ['admin', 'Control total del sistema: configuración, usuarios, roles y permisos de los módulos.'],
            ['rrhh', 'Recursos Humanos: lleva el personal, los contratos, las planillas, las boletas y las vacaciones.'],
            ['empleado', 'El trabajador: entra a ver y firmar sus propias boletas, sus documentos y sus vacaciones.'],
        ];

        foreach ($roles as [$nombre, $descripcion]) {
            Rol::create([
                'nombre'      => $nombre,
                'descripcion' => $descripcion,
            ]);
        }

        $this->command?->info('   ' . count($roles) . ' roles del sistema');
    }
}
