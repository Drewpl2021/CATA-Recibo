<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desde cuándo está afiliado a su sistema de pensión (AFP u ONP).
 *
 * Opcional: no entra en ningún cálculo, es un dato de su ficha que RR.HH.
 * lleva y que se pide en algunos trámites. Se llena desde la ficha o desde
 * el Excel de empleados, y puede quedar vacío.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->date('fecha_afiliacion')->nullable()->after('cuspp');
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn('fecha_afiliacion');
        });
    }
};
