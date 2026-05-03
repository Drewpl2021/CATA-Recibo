<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('dni', 8)->unique();
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('cargo', 100);
            $table->string('area', 100);
            $table->string('telefono', 15)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->date('fecha_ingreso');
            $table->string('estado', 20)->default('activo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};