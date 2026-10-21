<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desde qué navegador y equipo se hizo cada cosa («Chrome en Windows»).
 *
 * La IP sola dice poco dentro del colegio: todos salen por la misma. El
 * navegador ayuda a contestar «¿esto lo hizo desde su celular o desde la
 * computadora de RR.HH.?». Los registros de antes quedan sin él.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditoria', function (Blueprint $table) {
            $table->string('navegador', 255)->nullable()->after('ip');
        });
    }

    public function down(): void
    {
        Schema::table('auditoria', function (Blueprint $table) {
            $table->dropColumn('navegador');
        });
    }
};
