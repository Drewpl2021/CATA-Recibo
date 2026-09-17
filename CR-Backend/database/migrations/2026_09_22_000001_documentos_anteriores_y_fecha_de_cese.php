<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que hace falta para traer los archivos de antes del sistema.
 *
 * RR.HH. tiene años de boletas y contratos en PDF, sacados del Excel de la
 * planilla. Se suben en lote al expediente de cada trabajador, y de ahí salen
 * tres columnas:
 *
 *   periodo_mes / periodo_anio   de qué mes es una boleta que no tiene
 *                                planilla en el sistema (la planilla de marzo
 *                                de 2024 nunca pasó por aquí)
 *   huella                       el SHA-256 del archivo: subir la misma
 *                                carpeta dos veces no duplica nada
 *
 * Y a la ficha, la fecha de cese: para guardar las boletas de alguien que ya
 * se fue, primero tiene que existir su ficha, dada de baja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->unsignedTinyInteger('periodo_mes')->nullable()->after('planilla_id');
            $table->unsignedSmallInteger('periodo_anio')->nullable()->after('periodo_mes');
            $table->char('huella', 64)->nullable()->after('archivo');

            $table->index(['empleado_id', 'huella'], 'documentos_empleado_huella_idx');
        });

        Schema::table('empleados', function (Blueprint $table) {
            $table->date('fecha_cese')->nullable()->after('fecha_ingreso');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropIndex('documentos_empleado_huella_idx');
            $table->dropColumn(['periodo_mes', 'periodo_anio', 'huella']);
        });

        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn('fecha_cese');
        });
    }
};
