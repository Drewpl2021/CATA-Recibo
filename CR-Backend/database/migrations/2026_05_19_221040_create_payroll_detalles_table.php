<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_detalles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('planilla_id');
            $table->uuid('payment_concept_id');
            $table->decimal('monto_calculado', 10, 2);
            // Con qué regla salió ese monto: sirve para explicarlo después
            // ("10% de su básico") y para recalcularlo si cambia el sueldo.
            $table->enum('calculo', ['fijo', 'porcentaje'])->nullable();
            $table->decimal('valor', 10, 2)->nullable();
            // Detalle puntual de ESTA aplicación del concepto (ej. concepto genérico
            // "Otros Conceptos" + descripcion "Subsidio de Maternidad"). La boleta
            // imprime "Nombre del concepto: descripcion" cuando viene informada.
            $table->string('descripcion', 255)->nullable();
            $table->string('estado', 45)->default('activo');
            $table->timestamps();

            /*
             * Las líneas de una planilla se leen siempre juntas, y el Panel
             * de Control las suma por concepto. Con el monto DENTRO del
             * índice esa suma no tiene que ir a buscar cada fila.
             *
             * Ojo con la historia: con ids aleatorios (UUID v4) este mismo
             * índice salía PEOR —a MySQL le parecía barato recorrerlo entero
             * y dejaba de entrar por las planillas del mes—. Con ids
             * ordenados (UUID v7, ver los modelos) el plan se mantiene y la
             * suma baja de 102 a 48 ms sobre un mes de 2 000 trabajadores.
             * Si algún día se vuelve a v4, revisar esto.
             */
            $table->index(['planilla_id', 'payment_concept_id', 'monto_calculado'], 'payroll_detalles_planilla_concepto_idx');

            $table->foreign('planilla_id')->references('id')->on('planilla');
            $table->foreign('payment_concept_id')->references('id')->on('payment_concepts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_detalles');
    }
};
