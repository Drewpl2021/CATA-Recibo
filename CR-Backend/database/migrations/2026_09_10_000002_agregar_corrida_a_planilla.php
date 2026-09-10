<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada fila de planilla dice a qué corrida pertenece.
 *
 * Va NULLABLE a propósito y no se rellena nada de lo que ya existe: las
 * planillas de hoy se quedan como están y la pantalla las junta en un grupo
 * "Sin agrupar", desde donde se pueden mover a una corrida cuando RR.HH.
 * quiera. Una migración que reparte datos viejos por su cuenta es una
 * migración que hay que deshacer a mano si el reparto no gustó.
 *
 * `nullOnDelete` y no `cascadeOnDelete`: borrar una corrida no puede llevarse
 * por delante las planillas de la gente. Al borrarla, sus filas vuelven a
 * "Sin agrupar" — que es exactamente lo que significa quedarse sin corrida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilla', function (Blueprint $table) {
            $table->uuid('corrida_id')->nullable()->after('periodo_id');

            $table->foreign('corrida_id')
                ->references('id')->on('planilla_corridas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('planilla', function (Blueprint $table) {
            // El índice de la foránea se va con ella; hay que soltarla antes.
            $table->dropForeign(['corrida_id']);
            $table->dropColumn('corrida_id');
        });
    }
};
