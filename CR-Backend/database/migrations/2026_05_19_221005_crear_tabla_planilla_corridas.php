<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La corrida: la "planilla" con nombre que agrupa a un puñado de gente.
 *
 * Hasta ahora "planilla" era una fila por trabajador y por mes, y la pantalla
 * las enseñaba todas seguidas. Con 150 personas eso son 150 filas iguales sin
 * ninguna estructura, y RR.HH. no pregunta "¿cuánto cobra la fila 87?": pregunta
 * "¿cómo va la planilla de los docentes de este mes?".
 *
 * Una corrida es justo eso: "Planilla TIC — Septiembre 2026", con su nombre, su
 * mes y las filas de los trabajadores que le tocan.
 *
 * Por qué una tabla nueva y no reusar `periodos`: el periodo aquí se está
 * usando como año escolar ("Año Escolar 2026", de septiembre a diciembre), que
 * es otra cosa —dura meses y contiene varias corridas—. Meter las dos en la
 * misma tabla obligaría a distinguirlas por las fechas, y la pantalla de
 * Periodos acabaría mezclando el año escolar con las corridas de cada mes.
 *
 * La corrida NO guarda plata: los montos siguen en cada planilla, y lo que se
 * enseña arriba (cuánta gente, cuánto suma) se cuenta al vuelo. Un total
 * copiado en dos sitios es un total que algún día no coincide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planilla_corridas', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Cómo la llama RR.HH.: "Planilla TIC", "Docentes de secundaria".
            $table->string('nombre', 100);

            $table->integer('mes');
            $table->integer('anio');

            // De qué año escolar cuelga. Opcional: una corrida suelta —un pago
            // extraordinario, una liquidación— no tiene por qué colgar de uno.
            $table->uuid('periodo_id')->nullable();

            /*
             * Abierta se puede tocar; cerrada es la que ya se pagó y se deja
             * como está. No se borra nada al cerrarla: es solo el candado que
             * evita que alguien reabra el mes pasado y mueva un número.
             */
            $table->enum('estado', ['abierta', 'cerrada'])->default('abierta');

            $table->string('observaciones', 255)->nullable();

            $table->timestamps();

            $table->foreign('periodo_id')->references('id')->on('periodos')->nullOnDelete();

            // Dos corridas con el mismo nombre en el mismo mes serían dos cosas
            // indistinguibles en la pantalla.
            $table->unique(['nombre', 'mes', 'anio'], 'corridas_nombre_mes_idx');

            // La pantalla entra siempre por el mes, de lo más nuevo a lo viejo.
            $table->index(['anio', 'mes'], 'corridas_periodo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planilla_corridas');
    }
};
