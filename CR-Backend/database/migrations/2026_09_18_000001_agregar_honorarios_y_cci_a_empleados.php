<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El CCI, y el recibo por honorarios como forma de pago.
 *
 * Dos cosas que van juntas porque las pide el mismo bloque de la ficha:
 *
 * 1. `cci` — Código de Cuenta Interbancario, 20 dígitos. Es OPCIONAL a
 *    propósito: el número de cuenta normal basta para abonar dentro del
 *    mismo banco, y el CCI solo hace falta para transferir desde otro. Al
 *    colegio no le consta el de todos, y exigirlo habría trabado altas que
 *    hoy se hacen sin problema.
 *
 * 2. `forma_pago` gana el valor 'honorarios'. Va por ALTER y no por el
 *    desplegable porque la columna es un ENUM: el motor rechaza cualquier
 *    valor que no esté en su lista, así que agregarlo solo en la pantalla
 *    habría dado un error de base al guardar.
 *
 * Es aditiva e idempotente: sobre una base ya instalada no toca ningún dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empleados', 'cci')) {
            Schema::table('empleados', function (Blueprint $table) {
                $table->string('cci', 20)->nullable()->after('numero_cuenta');
            });
        }

        // El ENUM solo existe en MySQL. En SQLite —el motor de las pruebas—
        // la columna ya es texto y acepta el valor nuevo sin tocar nada.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE empleados MODIFY forma_pago ENUM('banco','efectivo','otro','honorarios') NULL"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // Primero se saca a quien esté en honorarios. Si se dejara, el
            // MODIFY lo guardaría como cadena vacía sin avisar de nada, y la
            // ficha quedaría sin forma de pago en lugar de volver atrás.
            DB::table('empleados')->where('forma_pago', 'honorarios')->update(['forma_pago' => 'otro']);

            DB::statement(
                "ALTER TABLE empleados MODIFY forma_pago ENUM('banco','efectivo','otro') NULL"
            );
        }

        if (Schema::hasColumn('empleados', 'cci')) {
            Schema::table('empleados', function (Blueprint $table) {
                $table->dropColumn('cci');
            });
        }
    }
};
