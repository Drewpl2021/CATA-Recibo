<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacaciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('empleado_id');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->integer('dias_solicitados');
            $table->text('motivo')->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->uuid('aprobado_por')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacaciones');
    }
};