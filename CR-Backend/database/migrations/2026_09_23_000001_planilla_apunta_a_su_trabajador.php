<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La planilla apunta a su trabajador, y ahora la base lo vigila.
 *
 * `planilla.empleado_id` tenía la columna y su índice, y el código usaba la
 * relación, pero no había llave foránea. Se pasó por alto al crear la tabla:
 * en esa misma migración sí se declararon las de `periodo_id` y `corrida_id`,
 * y las tablas hermanas creadas el mismo día —`vacaciones` y `users`— sí
 * apuntan a `empleados` con su regla.
 *
 * Mientras se entre por la aplicación no pasa nada: dar de baja a alguien lo
 * deja inactivo, nunca lo borra. El agujero se abre cuando alguien toca la
 * base por fuera —una consulta a mano, un script de limpieza, un respaldo
 * restaurado a medias—: las planillas de esa persona quedan apuntando a una
 * ficha que ya no existe, y eso no se nota hasta que un reporte sale con
 * filas sin nombre.
 *
 * Va con RESTRICT y no con cascade a propósito: una planilla es el registro
 * de un pago, con valor contable y legal. Antes que borrar el historial de
 * alguien, la base tiene que negarse a borrar al trabajador.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Primero mirar si hay planillas huérfanas. Con una sola, MySQL
         * rechaza la llave con un error 1452 que no dice cuál es el problema
         * ni cómo arreglarlo; mejor explicarlo aquí.
         */
        $huerfanas = DB::table('planilla as p')
            ->leftJoin('empleados as e', 'e.id', '=', 'p.empleado_id')
            ->whereNull('e.id')
            ->count();

        if ($huerfanas > 0) {
            throw new RuntimeException(
                "No se puede poner la llave: hay {$huerfanas} planilla(s) que apuntan a un "
                . 'trabajador que ya no existe. Míralas con:' . PHP_EOL
                . '  SELECT p.id, p.mes, p.anio, p.empleado_id FROM planilla p' . PHP_EOL
                . '  LEFT JOIN empleados e ON e.id = p.empleado_id WHERE e.id IS NULL;' . PHP_EOL
                . 'Hay que decidir qué hacer con ellas (recuperar al trabajador o borrar esas '
                . 'planillas) antes de volver a correr la migración.'
            );
        }

        Schema::table('planilla', function (Blueprint $table) {
            $table->foreign('empleado_id')
                ->references('id')->on('empleados')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('planilla', function (Blueprint $table) {
            $table->dropForeign(['empleado_id']);
        });
    }
};
