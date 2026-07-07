<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('empleado_id');
            $table->enum('tipo_contrato', ['indeterminado', 'plazo_fijo', 'suplencia', 'practicas']);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->enum('estado', ['vigente', 'finalizado', 'renovado'])->default('vigente');
            $table->enum('motivo_fin', [
                'renuncia', 'despido', 'fin_contrato_plazo',
                'fin_año_escolar', 'no_renovacion', 'jubilacion', 'otro'
            ])->nullable();
            $table->uuid('documento_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('empleado_id')->references('id')->on('empleados')->onDelete('cascade');
            $table->foreign('documento_id')->references('id')->on('documentos')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};