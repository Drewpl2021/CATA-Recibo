<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Le da su sitio en el menú al Panel de Control.
 *
 * Esa pantalla existía desde el principio y es a donde cae Administración y
 * RR.HH. al entrar, pero no tenía módulo: no salía en la barra lateral, y la
 * única forma de volver a ella era pulsar el logo del colegio. Quien no lo
 * supiera, no la encontraba.
 *
 * Va en su propio grupo y de primero, porque es la pantalla de inicio.
 *
 * Solo lo ven admin y RR.HH.: sus cifras son de toda la nómina (masa
 * salarial, contratos por vencer), nada que deba ver un docente. Al empleado
 * el sistema lo manda a Mis Boletas, y eso no cambia.
 *
 * Es idempotente: si el módulo ya está (base sembrada de cero con el
 * ModuloSeeder ya actualizado), no hace nada.
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

        $yaExiste = DB::table('modulos')->where('ruta', '/dashboard')->exists();

        if ($yaExiste) {
            echo "   el Panel de Control ya estaba en el menú\n";
            return;
        }

        $ahora = now();

        $padreId = DB::table('modulo_padre')->where('nombre', 'Dashboard')->value('id');

        if (! $padreId) {
            $padreId = (string) Str::uuid();
            DB::table('modulo_padre')->insert([
                'id'         => $padreId,
                'nombre'     => 'Dashboard',
                'icono'      => 'dashboard',
                // orden 0 para que quede arriba sin tener que renumerar los
                // grupos que ya existen.
                'orden'      => 0,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $moduloId = (string) Str::uuid();
        DB::table('modulos')->insert([
            'id'              => $moduloId,
            'modulo_padre_id' => $padreId,
            'nombre'          => 'Panel de Control',
            'ruta'            => '/dashboard',
            'icono'           => 'dashboard',
            'orden'           => 1,
            'created_at'      => $ahora,
            'updated_at'      => $ahora,
        ]);

        $roles = DB::table('roles')->whereIn('nombre', ['admin', 'rrhh'])->pluck('id');

        // rol_modulo es una pivote pelada: solo rol_id + modulo_id, sin id
        // propio ni timestamps.
        foreach ($roles as $rolId) {
            DB::table('rol_modulo')->insert([
                'rol_id'    => $rolId,
                'modulo_id' => $moduloId,
            ]);
        }

        echo "   Panel de Control agregado al menú para {$roles->count()} rol(es)\n";
    }

    public function down(): void
    {
        $moduloId = DB::table('modulos')->where('ruta', '/dashboard')->value('id');

        if ($moduloId) {
            DB::table('rol_modulo')->where('modulo_id', $moduloId)->delete();
            DB::table('modulos')->where('id', $moduloId)->delete();
        }

        // El grupo solo se va si queda vacío: si alguien le colgó otra cosa,
        // se queda donde está.
        $padreId = DB::table('modulo_padre')->where('nombre', 'Dashboard')->value('id');

        if ($padreId && ! DB::table('modulos')->where('modulo_padre_id', $padreId)->exists()) {
            DB::table('modulo_padre')->where('id', $padreId)->delete();
        }
    }
};
