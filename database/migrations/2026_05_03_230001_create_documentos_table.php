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
            $table->string('tipo', 50);
            $table->string('archivo', 255);
            $table->uuid('firmado_por')->nullable();
            $table->string('codigo_firma', 100)->nullable();
            $table->timestamp('fecha_firma')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};