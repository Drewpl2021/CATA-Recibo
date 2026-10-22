<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para las tablas que crecen cada mes.
 *
 * Salen de medir, no de adivinar: se registraron las consultas que hace el
 * sistema al recorrer todas sus pantallas y se pasó cada una por EXPLAIN.
 * Aquí van las que recorrían la tabla entera o la ordenaban aparte
 * («filesort») en tablas que no paran de crecer: la auditoría (cada acción),
 * la planilla y la 5ta anterior al sistema.
 *
 * Probado con 300 000 registros de auditoría: «lo que hizo este usuario»
 * bajó de 37 ms a 0,1 ms. El listado general NO necesitaba nada: en InnoDB
 * el índice de created_at ya lleva el id por dentro y sirve para
 * «order by created_at desc, id desc». Tampoco los documentos: el índice
 * (empleado_id, tipo) ya responde en 0,2 ms. Ni los contratos de una persona
 * o el listado de empleados: son pocas filas y ordenarlas aparte no cuesta.
 *
 * MySQL quita SOLO el índice de la llave foránea (auditoria.user_id,
 * planilla.corrida_id) cuando otro índice empieza por esa columna: el nuevo
 * pasa a sostener la llave. Por eso down() lo devuelve antes de borrar.
 *
 * Los catálogos (sedes, áreas, cargos, roles, conceptos...) también salían
 * con recorrido completo, pero tienen decenas de filas: leerlos enteros
 * cuesta menos que pasar por un índice, así que no se tocan.
 */
return new class extends Migration
{
    /** tabla => [nombre => columnas] */
    private const NUEVOS = [
        // Auditoría filtrada por usuario: con solo el índice de la llave
        // foránea, MySQL juntaba todo lo de esa persona y lo ordenaba aparte.
        'auditoria' => [
            'auditoria_usuario_fecha_idx' => ['user_id', 'created_at'],
        ],
        // «Sin agrupar» (corrida_id vacío) y las personas de una planilla del
        // mes, en orden de año y mes.
        'planilla' => [
            'planilla_corrida_periodo_idx' => ['corrida_id', 'anio', 'mes'],
        ],
        // Renta de 5ta suma lo de antes del sistema por año; el índice único
        // empieza por empleado_id y no servía para «where anio = ?».
        'renta_quinta_previa' => [
            'renta_quinta_previa_anio_idx' => ['anio', 'updated_at'],
        ],
    ];

    public function up(): void
    {
        foreach (self::NUEVOS as $tabla => $indices) {
            foreach ($indices as $nombre => $columnas) {
                if (! Schema::hasIndex($tabla, $nombre)) {
                    Schema::table($tabla, fn (Blueprint $t) => $t->index($columnas, $nombre));
                }
            }
        }
    }

    /** Índices de llave foránea que MySQL deja de necesitar con los nuevos. */
    private const DE_LLAVE = [
        'auditoria' => ['auditoria_user_id_foreign', 'user_id'],
        'planilla' => ['planilla_corrida_id_foreign', 'corrida_id'],
    ];

    public function down(): void
    {
        foreach (self::DE_LLAVE as $tabla => [$nombre, $columna]) {
            if (! Schema::hasIndex($tabla, $nombre)) {
                Schema::table($tabla, fn (Blueprint $t) => $t->index($columna, $nombre));
            }
        }

        foreach (self::NUEVOS as $tabla => $indices) {
            foreach (array_keys($indices) as $nombre) {
                if (Schema::hasIndex($tabla, $nombre)) {
                    Schema::table($tabla, fn (Blueprint $t) => $t->dropIndex($nombre));
                }
            }
        }
    }
};
