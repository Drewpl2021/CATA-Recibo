<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La página Proyectos del portal (§3.17 y §3.18): sus categorías, cada
 * proyecto con su detalle y su galería. El título y la frase de la página
 * van en portal_secciones. Reglas comunes: ver las listas de Inicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_proyecto_categorias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            $table->string('nombre', 100);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('portal_proyectos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            // La dirección del detalle. No cambia una vez publicado.
            $table->string('slug', 60)->unique();
            $table->string('titulo');
            $table->string('lema')->nullable();
            $table->text('resumen');
            $table->uuid('categoria_id');
            // string[]: niveles o grados que participan
            $table->json('niveles');
            // El `estado` del contrato (en-curso, proximo, finalizado), con
            // otro nombre por el `estado` de publicación.
            $table->string('etapa', 20)->nullable();
            $table->date('fecha')->nullable();
            $table->uuid('imagen_id')->nullable();
            // string[]: párrafos
            $table->json('descripcion');
            // [{ titulo, tipo, items: [{ titulo, texto }] }]
            $table->json('secciones');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            // Una categoría con proyectos no se borra: se los dejaría sin categoría.
            $table->foreign('categoria_id')->references('id')->on('portal_proyecto_categorias');
            $table->foreign('imagen_id')->references('id')->on('portal_imagenes');
            $table->index(['estado', 'orden']);
        });

        Schema::create('portal_proyecto_imagenes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('proyecto_id');
            $table->uuid('imagen_id');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->foreign('proyecto_id')->references('id')->on('portal_proyectos')->cascadeOnDelete();
            $table->foreign('imagen_id')->references('id')->on('portal_imagenes');
            $table->index(['proyecto_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_proyecto_imagenes');
        Schema::dropIfExists('portal_proyectos');
        Schema::dropIfExists('portal_proyecto_categorias');
    }
};
