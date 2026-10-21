<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La CTS de cada trabajador, semestre por semestre.
 *
 * La CTS (D.S. 001-97-TR) no se paga con la boleta: el colegio la deposita
 * en una cuenta del trabajador dos veces al año —hasta el 15 de mayo por
 * noviembre–abril y hasta el 15 de noviembre por mayo–octubre— y le entrega
 * una hoja de liquidación. Cada fila es ESA liquidación: con qué se calculó
 * (para que la hoja se pueda volver a imprimir igual años después) y si ya
 * se depositó.
 *
 * El desglose se guarda y no se recalcula al leerla: si después cambia el
 * sueldo de la ficha, la liquidación de un semestre pasado tiene que seguir
 * diciendo lo que se depositó de verdad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cts_liquidaciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('empleado_id');
            // El año y el mes del depósito: mayo cubre noviembre–abril, noviembre cubre mayo–octubre.
            $table->unsignedSmallInteger('anio');
            $table->enum('periodo', ['mayo', 'noviembre']);
            // El tramo que de verdad trabajó dentro del semestre.
            $table->date('desde');
            $table->date('hasta');

            // La remuneración computable, pieza por pieza.
            $table->decimal('sueldo', 10, 2)->default(0);
            $table->decimal('asignacion_familiar', 10, 2)->default(0);
            $table->decimal('bonificacion_cargo', 10, 2)->default(0);
            // La gratificación del semestre de la que sale el sexto, y de dónde se tomó.
            $table->decimal('gratificacion', 10, 2)->default(0);
            $table->string('gratificacion_fuente', 20)->nullable();
            $table->decimal('sexto_gratificacion', 10, 2)->default(0);
            $table->decimal('remuneracion_computable', 10, 2)->default(0);

            $table->unsignedTinyInteger('meses')->default(0);
            $table->unsignedTinyInteger('dias')->default(0);
            $table->decimal('monto', 10, 2)->default(0);

            // calculada → depositada. Una depositada ya no se vuelve a calcular.
            $table->string('estado', 20)->default('calculada');
            $table->date('fecha_deposito')->nullable();
            $table->string('entidad_financiera', 100)->nullable();
            $table->string('numero_cuenta', 50)->nullable();
            // La hoja de liquidación en el expediente (tipo «cts»).
            $table->uuid('documento_id')->nullable();
            $table->string('observacion', 255)->nullable();
            $table->timestamps();

            $table->unique(['empleado_id', 'anio', 'periodo']);
            $table->index(['anio', 'periodo', 'estado']);
            $table->foreign('empleado_id')->references('id')->on('empleados')->cascadeOnDelete();
            $table->foreign('documento_id')->references('id')->on('documentos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cts_liquidaciones');
    }
};
