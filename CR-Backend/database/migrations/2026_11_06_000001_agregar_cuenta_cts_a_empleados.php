<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La cuenta donde se le deposita la CTS a cada trabajador.
 *
 * Es otra cuenta, distinta de la del sueldo: la abre el trabajador en la
 * entidad que él elige (D.S. 001-97-TR, art. 23). Sin estos campos el
 * depósito y la hoja de liquidación tomaban la cuenta del sueldo, que no es
 * donde va la CTS.
 *
 * Nacen vacíos: no hay de dónde sacarlos, y copiar la del sueldo sería
 * inventar el dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->string('cts_entidad_financiera', 100)->nullable()->after('cci');
            $table->string('cts_numero_cuenta', 30)->nullable()->after('cts_entidad_financiera');
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn(['cts_entidad_financiera', 'cts_numero_cuenta']);
        });
    }
};
