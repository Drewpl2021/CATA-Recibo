<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cómo se llama el concepto EN LA BOLETA, cuando no es igual que en el catálogo.
 *
 * El nombre del catálogo tiene que ser único —es con lo que el motor lo
 * busca— y la boleta a veces necesita otro. El caso concreto son las dos
 * bolsas genéricas: en la boleta las dos se leen "Otros Conceptos", una bajo
 * Ingresos y otra bajo Descuentos, pero en el catálogo no pueden llamarse
 * igual las dos.
 *
 * Va nullable: la inmensa mayoría de los conceptos se imprimen con su
 * nombre y no necesitan nada. Solo se llena cuando de verdad difieren.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_concepts', function (Blueprint $table) {
            $table->string('etiqueta_boleta', 150)->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('payment_concepts', function (Blueprint $table) {
            $table->dropColumn('etiqueta_boleta');
        });
    }
};
