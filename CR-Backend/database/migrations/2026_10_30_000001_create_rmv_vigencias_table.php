<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los cambios del sueldo mínimo (RMV) durante el año. Los montos de ley se
 * guardan por año, pero la RMV sube cuando sale el decreto: el D.S.
 * 015-2026-TR la llevó de S/ 1,130 a S/ 1,230 desde el 1 de octubre de 2026.
 * Cambiar el monto del año habría movido enero a setiembre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rmv_vigencias', function (Blueprint $table) {
            $table->id();
            // Siempre el primer día del mes: la planilla es mensual.
            $table->date('vigente_desde')->unique();
            $table->decimal('monto', 10, 2);
            $table->timestamps();
        });

        DB::table('rmv_vigencias')->insert([
            'vigente_desde' => '2026-10-01', 'monto' => 1230, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('rmv_vigencias');
    }
};
