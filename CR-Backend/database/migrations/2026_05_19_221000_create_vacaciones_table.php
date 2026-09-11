<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacaciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('empleado_id');
            $table->uuid('periodo_id')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->integer('dias_solicitados');
            $table->text('motivo')->nullable();
            // Lo que RR.HH. escribe al responder la solicitud.
            $table->text('observacion')->nullable();
            $table->string('estado', 20)->default('pendiente');
            // El nombre de quien respondió, no su id: se enseña tal cual
            // en la solicitud y no hace falta ir a buscarlo.
            $table->string('aprobado_por', 120)->nullable();
            $table->timestamp('aprobado_at')->nullable();
            $table->string('estado_registro')->default('activo');
            $table->timestamps();

            // Las de una persona por fecha, y el filtro por estado.
            $table->index(['empleado_id', 'fecha_inicio'], 'vacaciones_empleado_fecha_idx');
            $table->index('estado', 'vacaciones_estado_idx');
            $table->index('fecha_inicio', 'vacaciones_inicio_idx');

            $table->foreign('empleado_id')->references('id')->on('empleados')->cascadeOnDelete();
            $table->foreign('periodo_id')->references('id')->on('periodos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacaciones');
    }
};
