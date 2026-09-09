<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que le faltaba a `vacaciones` para que el flujo se sostenga.
 *
 *  - `observacion`: por qué RR.HH. rechazó. Sin esto al trabajador le
 *    aparecía "Rechazado" a secas y tenía que ir a preguntar en persona.
 *  - `aprobado_at`: cuándo se resolvió. `aprobado_por` decía quién, pero no
 *    cuándo, y en un reclamo esa fecha es justo la que se pide.
 *  - `aprobado_por` pasa de uuid (char 36) a texto: ahí va el nombre de quien
 *    resuelve, tomado del token, y un nombre largo no entra en 36.
 *  - Un índice por empleado y fecha: el saldo y el cruce de fechas preguntan
 *    siempre por ese par, y sin él cada solicitud recorría la tabla entera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('vacaciones', 'observacion')) {
                $table->text('observacion')->nullable()->after('motivo');
            }

            if (! Schema::hasColumn('vacaciones', 'aprobado_at')) {
                $table->timestamp('aprobado_at')->nullable()->after('aprobado_por');
            }
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->string('aprobado_por', 120)->nullable()->change();
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            // Ojo: al crear este índice, MySQL BORRA solo el que se había
            // fabricado para la clave foránea de empleado_id, porque este
            // empieza por esa misma columna y le sirve igual. A partir de aquí
            // la foránea se sostiene sobre este índice — que es justo lo que
            // complica deshacerlo, ver down().
            $table->index(['empleado_id', 'fecha_inicio'], 'vacaciones_empleado_fecha_idx');
            $table->index('estado', 'vacaciones_estado_idx');
        });
    }

    /**
     * Deshacer esto no es simétrico, y por eso reventaba.
     *
     * `migrate:refresh` fallaba con "Cannot drop index
     * 'vacaciones_empleado_fecha_idx': needed in a foreign key constraint":
     * como MySQL se comió el índice propio de la foránea al crear el
     * compuesto, quitarlo dejaría a la foránea sin nada donde apoyarse y el
     * motor no lo permite.
     *
     * La salida es soltar la foránea primero y volver a ponerla después: al
     * recrearla, MySQL se fabrica otra vez su propio índice y la tabla queda
     * como estaba antes de esta migración.
     */
    public function down(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropForeign(['empleado_id']);
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            // Con hasIndex para que esto siga funcionando aunque se quede a
            // medias: una migración que no se puede deshacer dos veces es una
            // migración que hay que arreglar a mano en la base.
            if (Schema::hasIndex('vacaciones', 'vacaciones_empleado_fecha_idx')) {
                $table->dropIndex('vacaciones_empleado_fecha_idx');
            }

            if (Schema::hasIndex('vacaciones', 'vacaciones_estado_idx')) {
                $table->dropIndex('vacaciones_estado_idx');
            }
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->foreign('empleado_id')->references('id')->on('empleados')->cascadeOnDelete();
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            // Vuelve a ser uuid, como estaba. Los nombres que guardamos ahí
            // caben de sobra en 36 caracteres.
            $table->uuid('aprobado_por')->nullable()->change();
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            foreach (['observacion', 'aprobado_at'] as $columna) {
                if (Schema::hasColumn('vacaciones', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
