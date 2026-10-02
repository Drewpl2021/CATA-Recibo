<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A quien no aporta a una AFP (ONP, o nadie) este dato no le aplica, y
 * AltaDeEmpleado::limpiarAfp() ya lo pone en null a propósito. La columna
 * se había creado sin `nullable()`, así que cualquier alta u actualización
 * de alguien sin AFP rompía con "Column 'tipo_comision_afp' cannot be null".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->enum('tipo_comision_afp', ['flujo', 'mixta'])->nullable()->default('flujo')->change();
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->enum('tipo_comision_afp', ['flujo', 'mixta'])->default('flujo')->change();
        });
    }
};
