<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los nombres con que RR.HH. llama a cada concepto en SUS Excel.
 *
 * En la hoja del colegio la columna dice "Movilidad" y en el catálogo el
 * concepto se llama "Planilla de Movilidad". La primera vez que se importa,
 * RR.HH. confirma que son lo mismo; esa confirmación queda aquí, y desde la
 * siguiente importación la columna se reconoce sola.
 *
 * El alias se guarda ya normalizado (minúsculas, sin tildes ni signos): así
 * "MOVILIDAD", "Movilidad " y "movilidad" son la misma fila y no tres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concepto_alias', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Único: un mismo título no puede apuntar a dos conceptos. Si
            // RR.HH. lo confirma distinto otro mes, se actualiza esta fila.
            $table->string('alias', 150)->unique();

            $table->uuid('payment_concept_id');

            // El nombre de quien lo confirmó, no su id: se enseña tal cual y
            // no hace falta ir a buscarlo.
            $table->string('confirmado_por', 120)->nullable();

            $table->timestamps();

            // Si se borra el concepto, sus alias ya no apuntan a nada.
            $table->foreign('payment_concept_id')->references('id')->on('payment_concepts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concepto_alias');
    }
};
