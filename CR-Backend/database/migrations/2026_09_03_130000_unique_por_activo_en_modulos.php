<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `modulos` y `modulo_padre` se dan de baja de forma lógica
 * (estado_registro = 'inactivo'), pero su columna `nombre` tenía un índice
 * UNIQUE de tabla completa. Resultado: al eliminar un módulo y querer volver
 * a crear otro con el mismo nombre, MySQL respondía con un 500 por clave
 * duplicada — el nombre quedaba "quemado" por un registro que ya nadie ve.
 *
 * Se quita el índice de la base y la unicidad pasa a la validación de los
 * controladores, que la exige SOLO entre los registros activos:
 *
 *     Rule::unique('modulos', 'nombre')->where('estado_registro', 'activo')
 *
 * Es el compromiso habitual con baja lógica en MySQL, que no admite índices
 * únicos parciales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modulos', function (Blueprint $table) {
            $table->dropUnique('modulos_nombre_unique');
        });

        Schema::table('modulo_padre', function (Blueprint $table) {
            $table->dropUnique('modulo_padre_nombre_unique');
        });
    }

    public function down(): void
    {
        Schema::table('modulos', function (Blueprint $table) {
            $table->unique('nombre');
        });

        Schema::table('modulo_padre', function (Blueprint $table) {
            $table->unique('nombre');
        });
    }
};
