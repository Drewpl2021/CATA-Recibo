<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modulo_padre', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre')->unique();
            $table->string('icono')->nullable();
            $table->integer('orden')->default(0);
            $table->string('estado_registro')->default('activo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modulo_padre');
    }
};
