<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planilla', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('empleado_id');
            $table->integer('mes');
            $table->integer('anio');
            $table->decimal('sueldo_base', 10, 2);
            $table->decimal('bonificaciones', 10, 2)->default(0);
            $table->decimal('descuentos', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planilla');
    }
};