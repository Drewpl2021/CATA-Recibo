<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los ajustes del sistema que cambia el Administrador sin tocar código.
 *
 * Una fila por ajuste (clave → valor). El primero: si RR.HH. puede armar
 * planillas y boletas de años anteriores, para dejar de registro las que
 * antes se firmaban a mano. Nace activado: es justo lo que se pidió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->string('clave', 60)->primary();
            $table->text('valor')->nullable();
            $table->timestamps();
        });

        DB::table('configuraciones')->insert([
            'clave'      => 'permitir_anios_anteriores',
            'valor'      => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
