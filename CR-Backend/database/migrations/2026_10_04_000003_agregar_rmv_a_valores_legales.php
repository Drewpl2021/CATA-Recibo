<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La remuneración mínima vital (RMV) de cada año.
 *
 * Hace falta para EsSalud: el aporte nunca se calcula sobre menos que la
 * RMV. El PLAME real del colegio lo aplica (SONCO RAMOS, sueldo 582.80:
 * EsSalud 101.70 = 9% de 1 130, no 52.45), y el sistema no lo hacía.
 *
 * 1 025 en 2024; 1 130 desde enero de 2025.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('valores_legales', function (Blueprint $table) {
            $table->decimal('rmv', 10, 2)->default(1130)->after('uit');
        });

        DB::table('valores_legales')->where('anio', 2024)->update(['rmv' => 1025]);
        DB::table('valores_legales')->where('anio', '>=', 2025)->update(['rmv' => 1130]);
    }

    public function down(): void
    {
        Schema::table('valores_legales', function (Blueprint $table) {
            $table->dropColumn('rmv');
        });
    }
};
