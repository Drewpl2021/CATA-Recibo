<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El Diezmo se aplica solo al 10% del sueldo a TODO el personal (ver
 * PaymentConceptSeeder, aplica_a_todos=true): es la norma del colegio, y
 * quien no diga lo contrario queda incluido. Esta columna es la excepción
 * puntual, igual que `tiene_hijos` lo es para la Asignación Familiar: por
 * defecto true, así que a quien ya existe o a quien se da de alta sin
 * contestar nada se le sigue aplicando el 10%, y solo deja de aplicársele
 * a quien RR.HH. marque explícitamente que no lo autoriza.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empleados', 'aplica_diezmo')) {
            Schema::table('empleados', function (Blueprint $table) {
                $table->boolean('aplica_diezmo')->default(true)->after('tiene_hijos');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empleados', 'aplica_diezmo')) {
            Schema::table('empleados', function (Blueprint $table) {
                $table->dropColumn('aplica_diezmo');
            });
        }
    }
};
