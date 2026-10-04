<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La denominación oficial del año ("Año de la recuperación y consolidación
 * de la economía peruana"), que va arriba en la boleta. Cambia cada año por
 * decreto, así que se edita junto a los montos de ley, en Ajustes.
 *
 * Se siembran 2024 y 2025, que son seguros. 2026 se deja vacío a propósito:
 * mejor que lo escriba RR.HH. tal como sale en el decreto que inventarle una
 * palabra. Vacío, la boleta simplemente no lleva esa línea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('valores_legales', function (Blueprint $table) {
            $table->string('nombre_anio', 255)->nullable()->after('anio');
        });

        $nombres = [
            2024 => 'Año del Bicentenario, de la consolidación de nuestra Independencia, y de la conmemoración de las heroicas batallas de Junín y Ayacucho',
            2025 => 'Año de la recuperación y consolidación de la economía peruana',
        ];

        foreach ($nombres as $anio => $nombre) {
            DB::table('valores_legales')->where('anio', $anio)->update(['nombre_anio' => $nombre]);
        }
    }

    public function down(): void
    {
        Schema::table('valores_legales', function (Blueprint $table) {
            $table->dropColumn('nombre_anio');
        });
    }
};
