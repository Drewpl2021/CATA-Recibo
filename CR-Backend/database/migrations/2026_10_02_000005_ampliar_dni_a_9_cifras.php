<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El DNI de siempre son 8 cifras, pero un extranjero contratado trae un
 * Carné de Extranjería de 9: es una persona real que igual hay que poder
 * registrar, y la columna se había dejado fija en 8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->string('dni', 9)->change();
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->string('dni', 8)->change();
        });
    }
};
