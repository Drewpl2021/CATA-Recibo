<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qué tipos de contrato generan CTS.
 *
 * Igual que `permite_vacaciones`: una bandera del catálogo y no un nombre
 * escrito en el código. Las prácticas (modalidades formativas) no generan
 * CTS; todos los demás sí. Si el colegio tiene un tipo de jornada menor a
 * cuatro horas diarias —que tampoco genera CTS—, RR.HH. lo desmarca desde
 * Tipos de Contrato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_contrato', function (Blueprint $table) {
            $table->boolean('genera_cts')->default(true)->after('permite_vacaciones');
        });

        DB::table('tipos_contrato')->where('nombre', 'like', 'Pr_ctica%')->update(['genera_cts' => false]);
    }

    public function down(): void
    {
        Schema::table('tipos_contrato', function (Blueprint $table) {
            $table->dropColumn('genera_cts');
        });
    }
};
