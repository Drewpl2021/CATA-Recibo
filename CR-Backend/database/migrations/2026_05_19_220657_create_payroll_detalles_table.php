<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_detalles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('planilla_id');
            $table->uuid('payment_concept_id');
            $table->decimal('monto_calculado', 10, 2);
            $table->string('estado', 45)->default('activo');
            $table->timestamps();

            $table->foreign('planilla_id')->references('id')->on('planilla');
            $table->foreign('payment_concept_id')->references('id')->on('payment_concepts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_detalles');
    }
};