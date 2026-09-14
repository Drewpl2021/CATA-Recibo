<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué cargos tienen sentido en cada área.
 *
 * Va en una tabla aparte y no como una columna `area_id` en cargos porque la
 * relación es de varios a varios de verdad: "Docente" existe en Inicial, en
 * Primaria y en Secundaria. Con una columna habría que partirlo en tres
 * ("Docente de Primaria"…), y entonces el cargo dejaría de ser el cargo para
 * ser cargo + nivel — que es justo lo que ya dice el área, y lo que se
 * imprime dos veces en la boleta.
 *
 * LA REGLA: un cargo SIN ninguna fila aquí vale en TODAS las áreas.
 *
 * Eso es lo que hace barata la función: no hay que llenar una matriz de 15
 * áreas × 34 cargos para que el filtro sirva. Se acotan los que conviene
 * (Contador, Bibliotecario, Vigilante…) y los transversales —Practicante,
 * Voluntario Misionero— se quedan sin marcar y siguen valiendo en cualquier
 * lado. Con la tabla vacía, el sistema se comporta igual que antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_cargo', function (Blueprint $table) {
            $table->uuid('area_id');
            $table->uuid('cargo_id');

            // La pareja no se repite, y de paso sirve para "los cargos de
            // esta área", que es como entra el formulario de alta.
            $table->primary(['area_id', 'cargo_id']);

            // Y al revés: "las áreas de este cargo", que es como entra su ficha.
            $table->index('cargo_id', 'area_cargo_cargo_idx');

            $table->foreign('area_id')->references('id')->on('areas')->cascadeOnDelete();
            $table->foreign('cargo_id')->references('id')->on('cargos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_cargo');
    }
};
