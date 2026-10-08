<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La firma digital del colegio en las boletas (ReFirma). Ver
 * App\Support\FirmaDigitalDeBoletas.
 *
 *   firma_colegio      null: la boleta no va por este camino (las de antes, o
 *                      con el ajuste apagado). 'pendiente': emitida, falta
 *                      firmarla. 'parcial': tiene firmas, pero no todas las
 *                      que se piden. 'completa': ya se le entregó al trabajador.
 *   firmas_colegio     quién firmó y cuándo, leído del certificado de cada firma.
 *   archivo_sin_firma  el PDF tal como lo emitió el sistema: contra él se
 *                      comprueba que lo que se sube firmado sea esa misma boleta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->string('firma_colegio', 12)->nullable()->after('estado_firma_empleador');
            $table->json('firmas_colegio')->nullable()->after('firma_colegio');
            $table->string('archivo_sin_firma', 255)->nullable()->after('archivo');
            $table->timestamp('firma_colegio_completa_en')->nullable()->after('firmas_colegio');
            $table->string('firma_colegio_subida_por', 100)->nullable()->after('firma_colegio_completa_en');
            $table->index(['firma_colegio'], 'documentos_firma_colegio_idx');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropIndex('documentos_firma_colegio_idx');
            $table->dropColumn(['firma_colegio', 'firmas_colegio', 'archivo_sin_firma', 'firma_colegio_completa_en', 'firma_colegio_subida_por']);
        });
    }
};
