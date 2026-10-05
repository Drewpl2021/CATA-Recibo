<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que cada trabajador cobró y se le retuvo de Renta de 5ta en meses que
 * NO pasaron por el sistema.
 *
 * El colegio empieza a usarlo a fin de 2026, y la 5ta de marzo a diciembre
 * necesita lo cobrado y retenido en enero y febrero (así lo hace el Excel de
 * RR.HH.). Esos meses no tienen planilla en el sistema, y armarle planillas
 * de mentira solo para eso las haría aparecer en reportes y boletas. Se
 * guardan aparte: solo las lee el cálculo de la 5ta, y solo cuando ese mes no
 * tiene planilla de verdad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renta_quinta_previa', function (Blueprint $table) {
            $table->id();
            $table->uuid('empleado_id');
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->decimal('remuneracion', 10, 2)->default(0);
            $table->decimal('retencion', 10, 2)->default(0);
            // De dónde salió el dato, para poder rastrearlo ("Calculo 5ta.xlsx").
            $table->string('origen', 255)->nullable();
            $table->timestamps();

            $table->unique(['empleado_id', 'anio', 'mes']);
            $table->foreign('empleado_id')->references('id')->on('empleados')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renta_quinta_previa');
    }
};
