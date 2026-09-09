<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los índices que faltaban para ORDENAR, no para filtrar.
 *
 * La tanda anterior de índices cubrió las búsquedas ("la planilla de tal
 * empleado en tal mes"). Pero al medir con volumen de verdad —150 empleados y
 * dos años de planillas— apareció que seis listados seguían recorriendo la
 * tabla entera y ordenando en memoria:
 *
 *   EXPLAIN … type=ALL  key=(ninguno)  Extra="Using filesort"
 *
 * Un `filesort` sobre 3.000 documentos no se nota; sobre los 30.000 que va a
 * tener el colegio en unos años, sí. Y es de las cosas que se degradan poco a
 * poco: nadie sabe decir cuándo empezó a ir lento.
 *
 * Cada índice de aquí abajo responde a un `orderBy` que YA está en el código
 * —no se agregan "por si acaso", cada índice se paga en cada INSERT—, y todos
 * se comprobaron con EXPLAIN antes y después.
 *
 * Ninguno empieza por una columna con clave foránea, así que no repiten el
 * problema de la migración de vacaciones: MySQL no se come ningún índice de
 * foránea al crearlos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            // La pantalla de Empleados: los activos, ordenados por apellido y
            // nombre (EmpleadoController::index). Es el listado más abierto
            // del sistema y era un recorrido completo con filesort.
            $table->index(['estado', 'apellido', 'nombre'], 'empleados_estado_nombre_idx');
        });

        Schema::table('documentos', function (Blueprint $table) {
            // Documentos y Mis Documentos ordenan por fecha de emisión.
            $table->index('created_at', 'documentos_creado_idx');

            // El Panel de Control cuenta las boletas por estado de firma.
            // Sin esto recorría los 3.154 documentos para dar tres números.
            $table->index(['tipo', 'estado_firma'], 'documentos_tipo_firma_idx');
        });

        Schema::table('contratos', function (Blueprint $table) {
            // La lista de contratos, del más nuevo al más viejo.
            $table->index('fecha_inicio', 'contratos_inicio_idx');
            // Y las cifras de la cabecera: cuántos vigentes y cuántos cerrados.
            $table->index('estado', 'contratos_estado_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            // La lista de Usuarios va ordenada por nombre.
            $table->index('name', 'users_nombre_idx');
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            // El listado de RR.HH. ordena por fecha sin filtrar por empleado,
            // así que el índice (empleado_id, fecha_inicio) no le sirve: solo
            // se puede usar un índice compuesto desde la izquierda.
            $table->index('fecha_inicio', 'vacaciones_inicio_idx');
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropIndex('empleados_estado_nombre_idx');
        });

        Schema::table('documentos', function (Blueprint $table) {
            $table->dropIndex('documentos_creado_idx');
            $table->dropIndex('documentos_tipo_firma_idx');
        });

        Schema::table('contratos', function (Blueprint $table) {
            $table->dropIndex('contratos_inicio_idx');
            $table->dropIndex('contratos_estado_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_nombre_idx');
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropIndex('vacaciones_inicio_idx');
        });
    }
};
