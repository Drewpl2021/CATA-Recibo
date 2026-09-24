<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuándo vio cada quien la guía de primeros pasos.
 *
 * Es UNA columna y no una tabla de progreso a propósito. Si los pasos se
 * guardaran uno por uno, el sistema tendría dos verdades sobre lo mismo: la
 * tabla diría "ya firmó sus boletas" y las boletas dirían otra cosa. Lo que
 * está hecho se calcula cada vez de los datos de verdad —si ya puso su
 * contraseña, si firmó los términos, si subió su hoja de vida—, así que acá
 * solo hace falta recordar si la guía ya se le abrió sola una vez.
 *
 * Null = todavía no la ha visto, y se le abre al entrar.
 *
 * Va en `users` y no en `empleados` porque también la ven las cuentas de
 * Administración y de RR.HH., que no tienen ficha de trabajador.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'guia_vista_en')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('guia_vista_en')->nullable()->after('foto');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'guia_vista_en')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('guia_vista_en');
        });
    }
};
