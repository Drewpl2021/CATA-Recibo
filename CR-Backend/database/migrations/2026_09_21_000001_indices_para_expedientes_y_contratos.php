<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para las preguntas que hace "Documentos del personal".
 *
 * Medido con 313 trabajadores, 7 200 planillas y 7 200 boletas: la pantalla
 * tardaba 46 ms, y con el filtro "con boletas por firmar" 113 ms, casi todo
 * en dos consultas que preguntan, POR CADA trabajador, si tiene tal
 * documento o tal contrato.
 *
 * Los índices que ya había se quedaban a mitad de camino:
 *
 *   documentos(empleado_id, tipo)   servía para "sus hojas de vida", pero
 *                                   después había que mirar fila por fila
 *                                   el estado_registro y el estado_firma.
 *   contratos(estado) y (fecha_inicio) por separado: para "vence en 30 días"
 *                                   hay que cruzar las dos columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            // "¿Tiene hoja de vida vigente?" y "¿le quedan boletas por
            // firmar?": las dos se responden con este índice, sin tocar la
            // tabla. El estado_firma va al final porque es el único que se
            // compara con "distinto de".
            $table->index(
                ['empleado_id', 'tipo', 'estado_registro', 'estado_firma'],
                'documentos_empleado_tipo_estados_idx'
            );
        });

        Schema::table('contratos', function (Blueprint $table) {
            // "Contratos que vencen pronto", sobre todo el personal.
            $table->index(['estado', 'fecha_fin'], 'contratos_estado_fin_idx');
            // El contrato vigente de una persona, que es el que manda:
            // empleado + estado, y ya ordenado por fecha de inicio.
            $table->index(['empleado_id', 'estado', 'fecha_inicio'], 'contratos_empleado_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropIndex('documentos_empleado_tipo_estados_idx');
        });

        Schema::table('contratos', function (Blueprint $table) {
            $table->dropIndex('contratos_estado_fin_idx');
            $table->dropIndex('contratos_empleado_estado_idx');
        });
    }
};
