<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modulos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('modulo_padre_id');
            $table->string('nombre');
            $table->string('ruta')->nullable();
            $table->string('icono')->nullable();
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->foreign('modulo_padre_id')
                  ->references('id')->on('modulo_padre')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modulos');
    }
};