<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El historial de la planilla, escrito por la BASE y no por la aplicación.
 *
 * La tabla `auditoria` la escribe Laravel (trait Auditable) y dice QUIÉN
 * hizo el cambio. Pero solo ve lo que pasa por los modelos: un UPDATE a mano
 * en la consola de MySQL, un script de migración o un arreglo de emergencia
 * no dejan nada. Un trigger sí: se dispara pase lo que pase por encima.
 *
 * Son dos registros que se complementan, no uno que sobra:
 *   auditoria          → quién, desde dónde, en palabras (la aplicación)
 *   planilla_historial → qué cifra había antes y cuál después (la base)
 *
 * Solo guarda cuando cambia el neto o el estado: es lo que se reclama
 * ("¿por qué mi boleta dice otra cifra?"). Y solo se escribe, nunca se toca.
 *
 * Además, en MySQL deja el procedimiento almacenado `sp_resumen_planilla`,
 * que devuelve el resumen de un mes (cuántas boletas, cuánto suma, mínimo,
 * máximo y promedio) sin traer las filas a PHP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planilla_historial', function (Blueprint $table) {
            $table->id();
            $table->string('planilla_id', 36);
            $table->decimal('total_anterior', 10, 2)->nullable();
            $table->decimal('total_nuevo', 10, 2)->nullable();
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo')->nullable();
            $table->timestamp('cambiado_en')->useCurrent();

            // Sin foreign key a propósito: si se borra la planilla, el rastro
            // de lo que valía tiene que sobrevivir.
            $table->index(['planilla_id', 'cambiado_en'], 'planilla_historial_idx');
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_planilla_historial');
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER trg_planilla_historial
                AFTER UPDATE ON planilla
                FOR EACH ROW
                BEGIN
                    IF NOT (OLD.total <=> NEW.total)
                       OR NOT (OLD.estado_registro <=> NEW.estado_registro) THEN
                        INSERT INTO planilla_historial
                            (planilla_id, total_anterior, total_nuevo, estado_anterior, estado_nuevo, cambiado_en)
                        VALUES
                            (NEW.id, OLD.total, NEW.total, OLD.estado_registro, NEW.estado_registro, NOW());
                    END IF;
                END
            SQL);

            DB::unprepared('DROP PROCEDURE IF EXISTS sp_resumen_planilla');
            DB::unprepared(<<<'SQL'
                CREATE PROCEDURE sp_resumen_planilla(IN p_anio INT, IN p_mes INT)
                BEGIN
                    SELECT COUNT(*)              AS boletas,
                           COALESCE(SUM(total), 0) AS total_neto,
                           COALESCE(MIN(total), 0) AS minimo,
                           COALESCE(MAX(total), 0) AS maximo,
                           COALESCE(ROUND(AVG(total), 2), 0) AS promedio
                    FROM planilla
                    WHERE anio = p_anio
                      AND mes = p_mes
                      AND estado_registro = 'activo';
                END
            SQL);
        } elseif ($driver === 'sqlite') {
            // Las pruebas corren sobre SQLite: mismo trigger, su sintaxis.
            DB::unprepared('DROP TRIGGER IF EXISTS trg_planilla_historial');
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER trg_planilla_historial
                AFTER UPDATE ON planilla
                FOR EACH ROW
                WHEN OLD.total IS NOT NEW.total OR OLD.estado_registro IS NOT NEW.estado_registro
                BEGIN
                    INSERT INTO planilla_historial
                        (planilla_id, total_anterior, total_nuevo, estado_anterior, estado_nuevo, cambiado_en)
                    VALUES
                        (NEW.id, OLD.total, NEW.total, OLD.estado_registro, NEW.estado_registro, CURRENT_TIMESTAMP);
                END
            SQL);
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'sqlite'], true)) {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_planilla_historial');
        }
        if ($driver === 'mysql') {
            DB::unprepared('DROP PROCEDURE IF EXISTS sp_resumen_planilla');
        }

        Schema::dropIfExists('planilla_historial');
    }
};
