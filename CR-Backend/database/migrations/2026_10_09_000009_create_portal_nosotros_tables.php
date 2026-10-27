<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nosotros en el portal: sus subpáginas (§3.20), el personal docente (§3.21)
 * y los logros (§3.22). El título y la frase del índice de Nosotros y de
 * Logros van en portal_secciones. Reglas comunes: ver las listas de Inicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Cada subpágina de Nosotros es una lista de bloques que el portal
        // muestra en orden. Los bloques son de diez tipos con formas muy
        // distintas y siempre se editan con su página: van en JSON. Sus
        // imágenes se guardan en portal_imagenes y el bloque apunta a ellas
        // por id ("imagenId"), igual que en portal_secciones.
        Schema::create('portal_paginas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 60)->unique();
            // Para el menú de Nosotros (contrato 2.13, §7.2): el grupo
            // ("Institución") y el nombre con que sale en él. Se guardan desde
            // ya para que el menú no tenga que volver a escribirse en el código.
            $table->string('grupo', 60)->nullable();
            $table->string('nombre', 100)->nullable();
            $table->string('titulo');
            $table->string('bajada')->nullable();
            // La frase de su tarjeta en el índice de Nosotros. null = la tarjeta
            // muestra solo el nombre.
            $table->string('resumen')->nullable();
            $table->json('bloques');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->index(['estado', 'orden']);
        });

        // Los grupos de la página Personal Docente, con su filtro. Son una
        // lista propia: además de niveles hay grupos como "Primaria · Sede
        // CATA Jerusalén", y el contrato los publica aunque no tengan docentes.
        Schema::create('portal_docentes_niveles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            $table->string('nombre', 100);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->index(['estado', 'orden']);
        });

        // Solo lo que el colegio decide publicar de cada docente, con su
        // autorización de uso de imagen. No apunta a `empleados`: la ficha de
        // RR.HH. tiene datos personales que nunca deben llegar a internet.
        Schema::create('portal_docentes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            $table->string('nombre', 120);
            $table->string('cargo', 120);
            $table->uuid('nivel_id');
            $table->uuid('imagen_id')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->foreign('nivel_id')->references('id')->on('portal_docentes_niveles');
            $table->foreign('imagen_id')->references('id')->on('portal_imagenes');
            $table->index(['estado', 'orden']);
        });

        // Solo logros confirmados por el colegio, cada uno con su fuente.
        Schema::create('portal_logros', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            $table->string('titulo');
            // academico, deportivo, artistico, institucional
            $table->string('categoria', 20);
            // null = sin año publicado; no se inventa.
            $table->unsignedSmallInteger('anio')->nullable();
            $table->string('nivel', 100)->nullable();
            $table->text('descripcion')->nullable();
            $table->uuid('imagen_id')->nullable();
            $table->boolean('destacado')->default(false);
            // { etiqueta, url|null }
            $table->json('fuente')->nullable();
            $table->string('aclaracion', 100)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->foreign('imagen_id')->references('id')->on('portal_imagenes');
            $table->index(['estado', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_logros');
        Schema::dropIfExists('portal_docentes');
        Schema::dropIfExists('portal_docentes_niveles');
        Schema::dropIfExists('portal_paginas');
    }
};
