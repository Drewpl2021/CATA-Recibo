<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos que le quedan guardados a cada trabajador.
 *
 * Hasta ahora la campanita del menú no guardaba nada: cada vez que se
 * abría la pantalla se pedían los documentos del usuario y se contaban
 * los que no estuvieran firmados. Eso servía para "tienes N pendientes",
 * pero no para "el 3 de setiembre llegó tu boleta de agosto": en cuanto
 * el docente firmaba, el aviso desaparecía sin dejar rastro.
 *
 * Con esta tabla el aviso queda: se puede paginar el historial, ver la
 * fecha en que llegó cada boleta y distinguir lo leído de lo que no.
 *
 * No se usa la tabla `notifications` de Laravel a propósito: su columna
 * `data` es un JSON genérico, y acá hace falta consultar y ordenar por
 * campos concretos (a quién, de qué documento, leída o no) desde el mismo
 * listado paginado que usa el resto del sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // De momento solo existe 'boleta_disponible', pero el campo deja
            // sitio a los que vengan (contrato por vencer, documento nuevo)
            // sin tener que migrar otra vez.
            $table->string('tipo', 40)->default('boleta_disponible');

            $table->string('titulo', 150);
            $table->string('mensaje', 255);

            // A dónde lleva el aviso: la boleta que hay que firmar. Si el
            // documento se borra, el aviso se queda pero sin enlace.
            $table->uuid('documento_id')->nullable();

            $table->timestamp('leida_at')->nullable();
            $table->timestamps();

            $table->foreign('documento_id')->references('id')->on('documentos')->nullOnDelete();

            // La consulta de siempre: los avisos de este usuario, del más
            // nuevo al más viejo. Y el contador de no leídas.
            $table->index(['user_id', 'created_at'], 'notificaciones_usuario_fecha_idx');
            $table->index(['user_id', 'leida_at'], 'notificaciones_usuario_leida_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
