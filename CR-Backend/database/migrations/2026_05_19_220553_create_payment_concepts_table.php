<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_concepts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre', 45);
            $table->string('tipo', 45); // bonificacion | descuento
            $table->string('calculo', 45)->nullable(); // fijo | porcentaje
            $table->decimal('valor', 10, 2)->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_concepts');
    }
};