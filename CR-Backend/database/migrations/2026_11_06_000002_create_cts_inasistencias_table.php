<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las faltas injustificadas de cada trabajador en un semestre de CTS.
 *
 * Para la CTS solo cuentan los días de trabajo efectivo (D.S. 001-97-TR):
 * cada falta injustificada le quita un día al tiempo computable,
 * es decir 1/360 de la remuneración. La hoja de RR.HH. lo hacía a mano
 * («CTS Con descuento», columna «Días no laborados»).
 *
 * No sale del concepto «Descuento Autorizado - Tardanzas y Faltas»: ese
 * concepto está en soles y junta tardanzas con faltas, y las tardanzas no
 * le quitan días a nadie. Por eso RR.HH. anota los días aquí, y el concepto
 * solo se le muestra como pista de a quién revisar.
 *
 * `faltas` queda también en la liquidación: lo que se depositó tiene que
 * decir con cuántas faltas se calculó, aunque después se corrija el registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cts_inasistencias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('empleado_id');
            $table->unsignedSmallInteger('anio');
            $table->string('periodo', 10);
            $table->unsignedSmallInteger('dias');
            // Por qué: «faltas de junio y setiembre según el control de asistencia».
            $table->string('observacion', 255)->nullable();
            $table->uuid('registrado_por')->nullable();
            $table->timestamps();

            $table->unique(['empleado_id', 'anio', 'periodo'], 'cts_inasistencias_semestre_unico');
            $table->foreign('empleado_id')->references('id')->on('empleados')->cascadeOnDelete();
        });

        Schema::table('cts_liquidaciones', function (Blueprint $table) {
            $table->unsignedSmallInteger('faltas')->default(0)->after('dias');
        });
    }

    public function down(): void
    {
        Schema::table('cts_liquidaciones', function (Blueprint $table) {
            $table->dropColumn('faltas');
        });
        Schema::dropIfExists('cts_inasistencias');
    }
};
