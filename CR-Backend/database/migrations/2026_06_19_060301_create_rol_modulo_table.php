<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rol_modulo', function (Blueprint $table) {
            $table->uuid('rol_id');
            $table->uuid('modulo_id');
            $table->primary(['rol_id', 'modulo_id']);

            $table->foreign('rol_id')
                  ->references('id')->on('roles')
                  ->onDelete('cascade');

            $table->foreign('modulo_id')
                  ->references('id')->on('modulos')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_modulo');
    }
};