<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De dónde salió la remuneración de cada liquidación de CTS: de la planilla
 * del mes de cierre (abril, octubre o el del cese) o de la ficha, si ese mes
 * no pasó por el sistema. Igual que `gratificacion_fuente`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cts_liquidaciones', function (Blueprint $table) {
            $table->string('remuneracion_fuente', 20)->nullable()->after('bonificacion_cargo');
        });
    }

    public function down(): void
    {
        Schema::table('cts_liquidaciones', function (Blueprint $table) {
            $table->dropColumn('remuneracion_fuente');
        });
    }
};
