<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El rastro de la boleta: aviso, descarga, revisión y firma.
 *
 * De los cuatro hitos por los que pasa una boleta, la tabla ya guardaba dos:
 *
 *      Aviso enviado   ->  FALTABA
 *      Descargado      ->  FALTABA
 *      Revisado        ->  fecha_visto      (ya estaba)
 *      Firmado         ->  fecha_firma      (ya estaba)
 *
 * Para qué sirve tenerlos: cuando un trabajador dice "a mí nunca me llegó mi
 * boleta", RR.HH. necesita poder responder con una fecha y un correo, no con
 * una opinión. Y al revés: si de verdad no se le avisó, esto lo enseña.
 *
 * `aviso_correo` guarda a QUÉ correo se le avisó en ese momento, congelado.
 * Leerlo de la cuenta al momento de mostrarlo sería mentir: si el trabajador
 * cambió de correo en marzo, la boleta de enero diría que se le avisó a un
 * correo que en enero todavía no existía.
 *
 * `descargas` cuenta las veces; `fecha_descarga` guarda la PRIMERA, que es la
 * que responde "¿desde cuándo la tiene?".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->timestamp('fecha_aviso')->nullable()->after('fecha_visto');
            $table->string('aviso_correo', 150)->nullable()->after('fecha_aviso');
            $table->timestamp('fecha_descarga')->nullable()->after('aviso_correo');
            $table->unsignedInteger('descargas')->default(0)->after('fecha_descarga');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn(['fecha_aviso', 'aviso_correo', 'fecha_descarga', 'descargas']);
        });
    }
};
