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
