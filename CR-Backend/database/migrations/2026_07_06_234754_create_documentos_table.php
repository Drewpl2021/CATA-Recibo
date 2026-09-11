<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('empleado_id');
            $table->uuid('contrato_id')->nullable();
            $table->string('tipo', 50);
            $table->string('archivo', 255);
            $table->string('firmado_por', 100)->nullable();
            $table->string('codigo_firma', 100)->nullable();
            $table->timestamp('fecha_firma')->nullable();
            $table->enum('estado_firma', ['pendiente', 'visto', 'firmado'])->default('pendiente');
            $table->uuid('planilla_id')->nullable();
            $table->timestamp('fecha_visto')->nullable();
            // El rastro del documento: cuándo se avisó y a qué correo,
            // cuándo se descargó y cuántas veces. Es lo que le permite a
            // RR.HH. contestar con una fecha cuando alguien dice "a mí nunca
            // me avisaron".
            $table->timestamp('fecha_aviso')->nullable();
            $table->string('aviso_correo', 150)->nullable();
            $table->timestamp('fecha_descarga')->nullable();
            $table->unsignedInteger('descargas')->default(0);
            // Lado "Firma Empleador" — mismo patrón que el lado del empleado, pero
            // firmado por el RRHH/admin que representa a la institución. empleador_id
            // guarda QUIÉN de RRHH firmó, para poder estampar su firma_imagen/huella_imagen
            // (vía identidades_firma) igual que se hace con el lado del trabajador.
            $table->uuid('empleador_id')->nullable();
            $table->string('firmado_por_empleador', 100)->nullable();
            $table->string('codigo_firma_empleador', 100)->nullable();
            $table->timestamp('fecha_firma_empleador')->nullable();
            $table->enum('estado_firma_empleador', ['pendiente', 'firmado'])->default('pendiente');
            $table->string('estado_registro')->default('activo');
            $table->timestamps();

            // Los documentos de una persona, los de una planilla, y el
            // listado que ordena por fecha y filtra por estado de firma.
            $table->index(['empleado_id', 'tipo'], 'documentos_empleado_tipo_idx');
            $table->index(['planilla_id', 'tipo'], 'documentos_planilla_tipo_idx');
            $table->index('created_at', 'documentos_creado_idx');
            // El listado de Documentos siempre filtra los activos y ordena
            // por fecha. Con las dos columnas en el mismo índice se sirve de
            // él directamente; con el de solo la fecha, la página 2 000 leía
            // 20 000 entradas y descartaba las de baja una por una.
            $table->index(['estado_registro', 'created_at'], 'documentos_registro_creado_idx');
            $table->index(['tipo', 'estado_firma'], 'documentos_tipo_firma_idx');

            $table->foreign('empleado_id')->references('id')->on('empleados')->cascadeOnDelete();
            $table->foreign('contrato_id')->references('id')->on('contratos')->nullOnDelete();
            $table->foreign('planilla_id')->references('id')->on('planilla')->nullOnDelete();
            $table->foreign('empleador_id')->references('id')->on('empleados')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
