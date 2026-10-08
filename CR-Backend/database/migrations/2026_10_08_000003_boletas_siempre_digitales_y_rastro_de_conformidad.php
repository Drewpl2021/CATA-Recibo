<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Toda boleta nueva va con firma digital del colegio: el ajuste para
 * apagarlo se va. Y la conformidad del trabajador guarda su rastro: desde
 * qué dirección y equipo la dio, y el código (SHA-256) del PDF exacto al
 * que la dio.
 *
 * Las imágenes de firma y huella dejan de usarse; su tabla se queda como
 * está (no se borra lo que la gente ya registró).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->string('conformidad_ip', 45)->nullable()->after('codigo_firma');
            $table->string('conformidad_dispositivo', 255)->nullable()->after('conformidad_ip');
            $table->string('conformidad_sha256', 64)->nullable()->after('conformidad_dispositivo');
        });

        DB::table('configuraciones')->where('clave', 'boleta_firma_digital')->delete();
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn(['conformidad_ip', 'conformidad_dispositivo', 'conformidad_sha256']);
        });
    }
};
