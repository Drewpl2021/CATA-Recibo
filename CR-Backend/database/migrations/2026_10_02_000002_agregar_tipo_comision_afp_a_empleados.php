<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La comisión de AFP tiene dos esquemas, y el PLAME real del colegio los
 * distingue por trabajador (columna "TIPO COMISIÓN" de la hoja PLANILLA):
 *
 * - "Flujo": la normal desde 2013, % sobre la remuneración, la que ya
 *   calculaba el sistema para todos.
 * - "Mixta": régimen de antes del 2013. La AFP cobra su comisión directo
 *   del fondo acumulado, así que la planilla del colegio NO le descuenta
 *   nada de comisión —solo Fondo 10% + Prima de seguro 1.37%.
 *
 * En marzo 2026, 57 de 70 afiliados a AFP del colegio (81%) están en
 * Mixta: sin esta columna el sistema les estaba cobrando una comisión
 * que no les corresponde, todos los meses.
 *
 * Por defecto "flujo" —es el único esquema que existe para quien se
 * afilió de 2013 en adelante—, así que a quien no se le dice nada no
 * cambia nada. Solo se marca "Mixta" a quien el PLAME real ya tiene así.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empleados', 'tipo_comision_afp')) {
            Schema::table('empleados', function (Blueprint $table) {
                $table->enum('tipo_comision_afp', ['flujo', 'mixta'])->default('flujo')->after('afp');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empleados', 'tipo_comision_afp')) {
            Schema::table('empleados', function (Blueprint $table) {
                $table->dropColumn('tipo_comision_afp');
            });
        }
    }
};
