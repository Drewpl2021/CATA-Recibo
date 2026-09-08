<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pone vacaciones en el menú, que era lo que faltaba para que existiera.
 *
 * El API de vacaciones estaba desde el principio, pero sin módulo no había
 * pantalla ni forma de llegar: el sistema tenía el control de vacaciones
 * escondido donde nadie lo veía.
 *
 * Son dos, porque son dos oficios distintos:
 *   - "Vacaciones" (Boletas y Finanzas, admin y RR.HH.): las solicitudes de
 *     todo el personal, para aprobar o rechazar.
 *   - "Mis Vacaciones" (Mi Espacio, todos): los días que le quedan a uno y
 *     sus propias solicitudes.
 *
 * Es idempotente: si la base se sembró de cero con el ModuloSeeder ya
 * actualizado, no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Con `migrate:refresh` o `migrate:fresh` esto corre ANTES de los
        // seeders, sobre una base sin roles: no hay a quién darle el módulo, y
        // insertarlo igual dejaba una fila colgada sin dueño. Cuando la base se
        // siembra de cero, el menú lo arma el ModuloSeeder, que ya lo incluye.
        if (DB::table('roles')->count() === 0) {
            echo "   base vacía: el menú lo siembra ModuloSeeder\n";
            return;
        }

        $nuevos = [
            [
                'padre'  => 'Boletas y Finanzas',
                'nombre' => 'Vacaciones',
                'ruta'   => '/vacaciones',
                'orden'  => 6,
                'roles'  => ['admin', 'rrhh'],
            ],
            [
                'padre'  => 'Mi Espacio',
                'nombre' => 'Mis Vacaciones',
                'ruta'   => '/mis-vacaciones',
                'orden'  => 3,
                'roles'  => ['admin', 'rrhh', 'empleado'],
            ],
        ];

        foreach ($nuevos as $m) {
            if (DB::table('modulos')->where('ruta', $m['ruta'])->exists()) {
                echo "   {$m['nombre']} ya estaba en el menú\n";
                continue;
            }

            $padreId = DB::table('modulo_padre')->where('nombre', $m['padre'])->value('id');

            if (! $padreId) {
                echo "   no encontré el grupo \"{$m['padre']}\": {$m['nombre']} se queda fuera del menú\n";
                continue;
            }

            $moduloId = (string) Str::uuid();

            DB::table('modulos')->insert([
                'id'              => $moduloId,
                'modulo_padre_id' => $padreId,
                'nombre'          => $m['nombre'],
                'ruta'            => $m['ruta'],
                'icono'           => 'beach',
                'orden'           => $m['orden'],
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $roles = DB::table('roles')->whereIn('nombre', $m['roles'])->pluck('id');

            // rol_modulo es una pivote pelada: solo rol_id + modulo_id.
            foreach ($roles as $rolId) {
                DB::table('rol_modulo')->insert([
                    'rol_id'    => $rolId,
                    'modulo_id' => $moduloId,
                ]);
            }

            echo "   {$m['nombre']} agregado al menú para {$roles->count()} rol(es)\n";
        }
    }

    public function down(): void
    {
        foreach (['/vacaciones', '/mis-vacaciones'] as $ruta) {
            $moduloId = DB::table('modulos')->where('ruta', $ruta)->value('id');

            if ($moduloId) {
                DB::table('rol_modulo')->where('modulo_id', $moduloId)->delete();
                DB::table('modulos')->where('id', $moduloId)->delete();
            }
        }
    }
};
