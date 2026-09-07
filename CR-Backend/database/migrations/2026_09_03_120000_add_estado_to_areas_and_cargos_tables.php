<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Áreas y Cargos no tenían estado, pero Sedes sí, así que en pantalla las
 * tres se comportaban distinto. Se agrega la misma columna con el mismo
 * valor por defecto para que los tres catálogos sean iguales: dar de baja
 * un área o un cargo sin borrarlo (y sin romper los empleados que lo usan).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['areas', 'cargos'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('estado')->default('activo')->after('descripcion');
            });
        }
    }

    public function down(): void
    {
        foreach (['areas', 'cargos'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('estado');
            });
        }
    }
};
