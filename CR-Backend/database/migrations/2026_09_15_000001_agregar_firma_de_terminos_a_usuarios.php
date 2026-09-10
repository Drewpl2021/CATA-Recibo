<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La firma de los términos de uso, que antes se hacía en papel.
 *
 * El colegio repartía una hoja de Excel impresa y cada trabajador la firmaba
 * para dejar constancia de que aceptaba recibir sus boletas por medios
 * digitales. Esa hoja se perdía, se firmaba dos veces o no se firmaba nunca,
 * y RR.HH. no tenía forma de saber quién faltaba.
 *
 * Ahora la firma vive en la cuenta de cada uno. Se guarda lo mismo que
 * probaba la hoja: QUIÉN aceptó, CUÁNDO y QUÉ versión —porque los términos
 * cambian, y aceptar los de 2026 no es aceptar los de 2028—, más la IP, que
 * es lo que hace las veces de rúbrica en una aceptación electrónica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            // El "FIRMADO SÍ / NO" de la hoja de antes.
            $tabla->boolean('terminos_firmados')->default(false)->after('debe_cambiar_password');
            $tabla->timestamp('terminos_firmados_en')->nullable()->after('terminos_firmados');
            $tabla->string('terminos_version', 20)->nullable()->after('terminos_firmados_en');
            $tabla->string('terminos_ip', 45)->nullable()->after('terminos_version');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->dropColumn(['terminos_firmados', 'terminos_firmados_en', 'terminos_version', 'terminos_ip']);
        });
    }
};
