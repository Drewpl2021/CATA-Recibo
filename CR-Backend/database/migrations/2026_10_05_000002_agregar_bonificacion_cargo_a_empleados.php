<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La Bonificación por Cargo (la "Bonificación por Función" del PLAME) como
 * dato de la ficha, igual que el sueldo.
 *
 * Es un monto fijo por persona: en el PLAME de marzo y en el de septiembre es
 * el mismo (Huacasi 850, Apaza Sosa 700...). Hasta ahora había que agregarla a
 * mano en la planilla de cada mes, y si se olvidaba, la persona cobraba menos
 * y su ONP/AFP, EsSalud, Diezmo y Renta de 5ta salían mal. Ahora cada planilla
 * nueva la trae sola desde aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->decimal('bonificacion_cargo', 10, 2)->default(0)->after('sueldo_base');
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn('bonificacion_cargo');
        });
    }
};
