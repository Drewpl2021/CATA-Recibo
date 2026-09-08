<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para las tablas que crecen mes a mes.
 *
 * Hasta ahora los únicos índices eran los que MySQL crea solo por las
 * claves foráneas. El problema: las consultas que más se repiten no
 * caen sobre esas columnas, así que el motor recorría la tabla entera
 * cada vez. Con 127 empleados no se nota; con tres años de planillas
 * (127 × 12 × 3 ≈ 4.500 filas de planilla y ~40.000 de detalle) sí.
 *
 * Cada índice de acá abajo responde a una consulta que YA existe en el
 * código — no se agregan "por si acaso", porque cada índice se paga en
 * cada INSERT y UPDATE.
 *
 * Nota sobre el orden de las columnas: MySQL solo puede usar un índice
 * compuesto desde la izquierda. Por eso (empleado_id, anio, mes) sirve
 * también para buscar solo por empleado, pero no para buscar solo por
 * mes — para eso va el segundo índice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilla', function (Blueprint $table) {
            // empleado_id nunca tuvo clave foránea, así que tampoco tenía
            // índice: hoy "la planilla de tal empleado en tal mes" es un
            // recorrido completo. Es la consulta más repetida del sistema
            // (boletas, mis boletas, aplicar concepto a un grupo, generar).
            $table->index(['empleado_id', 'anio', 'mes'], 'planilla_empleado_periodo_idx');

            // "Todas las planillas de setiembre 2026": es como entra la
            // pantalla de Planillas y como se calcula la masa salarial.
            $table->index(['anio', 'mes'], 'planilla_periodo_idx');
        });

        Schema::table('payroll_detalles', function (Blueprint $table) {
            // updateOrCreate(planilla_id + payment_concept_id) se ejecuta una
            // vez por empleado y por concepto cada vez que se genera una
            // planilla o se aplica un concepto a un grupo. Las FK dejaron un
            // índice por cada columna suelta; este las cubre juntas.
            $table->index(['planilla_id', 'payment_concept_id'], 'payroll_detalles_planilla_concepto_idx');
        });

        Schema::table('payment_concepts', function (Blueprint $table) {
            // El filtro por tipo del catálogo.
            $table->index('tipo', 'payment_concepts_tipo_idx');

            // Al generar planillas se piden todos los conceptos automáticos.
            $table->index('aplica_a_todos', 'payment_concepts_aplica_a_todos_idx');
        });

        Schema::table('documentos', function (Blueprint $table) {
            // "¿este empleado ya tiene la boleta de esta planilla?" y el
            // listado de documentos de una persona por tipo. Crece igual de
            // rápido que planilla: una boleta por trabajador y por mes.
            $table->index(['empleado_id', 'tipo'], 'documentos_empleado_tipo_idx');
            $table->index(['planilla_id', 'tipo'], 'documentos_planilla_tipo_idx');
        });
    }

    /**
     * Deshacer esto tiene una trampa que costó un `migrate:refresh` fallido.
     *
     * Cuando un índice compuesto EMPIEZA por una columna que tiene clave
     * foránea, MySQL borra en silencio el índice que se había fabricado para
     * esa foránea, porque el nuevo le sirve igual. A partir de ahí la foránea
     * se apoya en el compuesto, y al intentar quitarlo el motor responde
     * "Cannot drop index ...: needed in a foreign key constraint".
     *
     * Pasa en tres de los índices de arriba:
     *   payroll_detalles_planilla_concepto_idx  (empieza por planilla_id)
     *   documentos_empleado_tipo_idx            (empieza por empleado_id)
     *   documentos_planilla_tipo_idx            (empieza por planilla_id)
     *
     * En esos hay que soltar la foránea, quitar el índice y volver a ponerla
     * con la misma regla de borrado que tenía. Los de `planilla` y
     * `payment_concepts` no tienen el problema: planilla.empleado_id nunca
     * tuvo foránea, y periodo_id conserva su propio índice.
     */
    public function down(): void
    {
        Schema::table('planilla', function (Blueprint $table) {
            $this->quitarIndice($table, 'planilla', 'planilla_empleado_periodo_idx');
            $this->quitarIndice($table, 'planilla', 'planilla_periodo_idx');
        });

        Schema::table('payment_concepts', function (Blueprint $table) {
            $this->quitarIndice($table, 'payment_concepts', 'payment_concepts_tipo_idx');
            $this->quitarIndice($table, 'payment_concepts', 'payment_concepts_aplica_a_todos_idx');
        });

        // payroll_detalles.planilla_id — sin acción al borrar, como la creó
        // su migración original.
        Schema::table('payroll_detalles', fn (Blueprint $table) => $table->dropForeign(['planilla_id']));
        Schema::table('payroll_detalles', function (Blueprint $table) {
            $this->quitarIndice($table, 'payroll_detalles', 'payroll_detalles_planilla_concepto_idx');
        });
        Schema::table('payroll_detalles', function (Blueprint $table) {
            $table->foreign('planilla_id')->references('id')->on('planilla');
        });

        // documentos: empleado_id cae en cascada, planilla_id queda en null.
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropForeign(['empleado_id']);
            $table->dropForeign(['planilla_id']);
        });
        Schema::table('documentos', function (Blueprint $table) {
            $this->quitarIndice($table, 'documentos', 'documentos_empleado_tipo_idx');
            $this->quitarIndice($table, 'documentos', 'documentos_planilla_tipo_idx');
        });
        Schema::table('documentos', function (Blueprint $table) {
            $table->foreign('empleado_id')->references('id')->on('empleados')->cascadeOnDelete();
            $table->foreign('planilla_id')->references('id')->on('planilla')->nullOnDelete();
        });
    }

    /** Quita un índice solo si está: así el down() se puede repetir. */
    private function quitarIndice(Blueprint $table, string $tabla, string $indice): void
    {
        if (Schema::hasIndex($tabla, $indice)) {
            $table->dropIndex($indice);
        }
    }
};
