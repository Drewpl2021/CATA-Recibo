<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los montos de ley, uno por año: UIT, asignación familiar y porcentajes de
 * pensión y EsSalud.
 *
 * Estaban fijos en CalculaConceptosPlanilla, así que una planilla de 2025
 * armada hoy se calculaba con la UIT de 2026. Ahora cada planilla usa los
 * de su año, y el Administrador los cambia desde Ajustes del sistema.
 *
 * Se siembran 2024, 2025 y 2026. UIT: 5 150 / 5 350 / 5 500. Asignación
 * familiar = 10% de la RMV: 102.50 (RMV 1 025) en 2024 y 113.00 (RMV
 * 1 130, desde enero 2025) después. Los porcentajes de AFP son los que ya
 * usaba el sistema, comprobados contra el PLAME de marzo 2026; los de años
 * anteriores se copian de esos y hay que revisarlos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valores_legales', function (Blueprint $table) {
            $table->unsignedSmallInteger('anio')->primary();
            $table->decimal('uit', 10, 2);
            $table->decimal('asignacion_familiar', 10, 2);
            $table->decimal('onp', 5, 2);
            $table->decimal('essalud', 5, 2);
            $table->decimal('aporte_afp', 5, 2);
            $table->decimal('prima_seguro_afp', 5, 2);
            $table->decimal('comision_habitat', 5, 2);
            $table->decimal('comision_integra', 5, 2);
            $table->decimal('comision_prima', 5, 2);
            $table->decimal('comision_profuturo', 5, 2);
            $table->timestamps();
        });

        $porcentajes = [
            'onp' => 13.00, 'essalud' => 9.00, 'aporte_afp' => 10.00, 'prima_seguro_afp' => 1.37,
            'comision_habitat' => 1.47, 'comision_integra' => 1.55, 'comision_prima' => 1.60, 'comision_profuturo' => 1.69,
        ];

        foreach ([2024 => [5150, 102.50], 2025 => [5350, 113.00], 2026 => [5500, 113.00]] as $anio => [$uit, $asignacion]) {
            DB::table('valores_legales')->insert($porcentajes + [
                'anio'                => $anio,
                'uit'                 => $uit,
                'asignacion_familiar' => $asignacion,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('valores_legales');
    }
};
