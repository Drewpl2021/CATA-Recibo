<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las listas de Inicio del portal: el carrusel, las cifras, los niveles, las
 * sedes y las noticias. Los textos de una sola vez (Quiénes somos, el título
 * de Niveles) van en portal_secciones.
 *
 * Reglas comunes a todas las tablas de lista del portal:
 *
 *   - `id` uuid, como el resto del sistema; `clave` es el `id` que ve el
 *     portal ("b-matriculas", "inicial"). Va aparte porque el contrato lo
 *     quiere estable y legible, y lo pone quien carga el contenido.
 *   - `orden`: el portal no reordena nada, muestra las listas como llegan.
 *   - `estado`: 'publicado' o 'borrador'. Casi nada se borra.
 *   - Los objetos pequeños del contrato (Telefono, Enlace, AccionHero, el
 *     horario) van en JSON: siempre se leen y se editan enteros.
 *   - Las imágenes, por llave a portal_imagenes (ver esa migración).
 */
return new class extends Migration
{
    public function up(): void
    {
        // El carrusel de Inicio (contrato §3.2).
        Schema::create('portal_banners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            // aviso, matricula, evento, noticia
            $table->string('categoria', 20);
            $table->string('titulo');
            $table->date('fecha')->nullable();
            $table->uuid('imagen_id');
            $table->uuid('imagen_movil_id')->nullable();
            // Punto que no se recorta, en % desde arriba a la izquierda. null = centro.
            $table->unsignedTinyInteger('foco_x')->nullable();
            $table->unsignedTinyInteger('foco_y')->nullable();
            $table->boolean('texto_en_imagen')->default(false);
            // izquierda, derecha
            $table->string('lado_accion', 10)->default('derecha');
            // AccionHero: { etiqueta, destino }
            $table->json('accion')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->foreign('imagen_id')->references('id')->on('portal_imagenes');
            $table->foreign('imagen_movil_id')->references('id')->on('portal_imagenes');
            $table->index(['estado', 'orden']);
        });

        // Cifras de Inicio, de Logros y de Propuesta Educativa: mismo tipo
        // (Cifra, §3.9), tres listas. `ambito` dice de cuál es.
        Schema::create('portal_cifras', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // portada, logros, propuesta
            $table->string('ambito', 20);
            $table->string('clave', 60);
            $table->unsignedInteger('valor');
            $table->string('sufijo', 10)->nullable();
            $table->string('etiqueta');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->unique(['ambito', 'clave']);
            $table->index(['ambito', 'estado', 'orden']);
        });

        // Los niveles de Inicio (§3.5). Son una lista propia y no la misma que
        // la de Propuesta Educativa ni la de Docentes: en los ejemplos del
        // contrato cambian el nombre ("Educación Inicial" / "Inicial"), el
        // rango, la foto y hasta qué niveles aparecen.
        Schema::create('portal_niveles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            $table->string('nombre', 100);
            $table->string('rango')->nullable();
            $table->string('sede')->nullable();
            $table->string('reconocimiento')->nullable();
            $table->uuid('imagen_id')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->foreign('imagen_id')->references('id')->on('portal_imagenes');
            $table->index(['estado', 'orden']);
        });

        // Las sedes (§3.6). No es la tabla `sedes` de RR.HH.: aquí va lo que
        // se publica (foto, mapa, horario), y una sede puede mostrarse con
        // otro nombre o no mostrarse.
        Schema::create('portal_sedes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            $table->string('nombre', 100);
            $table->string('direccion');
            $table->string('referencia')->nullable();
            $table->string('distrito')->nullable();
            // Telefono: { numero, visible }
            $table->json('telefono')->nullable();
            $table->string('nota')->nullable();
            $table->uuid('imagen_id')->nullable();
            $table->string('mapa_url', 2048)->nullable();
            $table->string('correo', 120)->nullable();
            // [{ dias, horas }]
            $table->json('horario');
            $table->string('mapa_embed_url', 2048)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->foreign('imagen_id')->references('id')->on('portal_imagenes');
            $table->index(['estado', 'orden']);
        });

        // Noticias y comunicados (§3.7). Se listan por fecha, de la más
        // reciente a la más antigua; `orden` desempata las del mismo día.
        Schema::create('portal_noticias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            $table->string('slug', 120)->unique();
            // noticia, comunicado
            $table->string('tipo', 20);
            $table->string('titulo');
            $table->text('resumen')->nullable();
            $table->date('fecha');
            $table->uuid('imagen_id')->nullable();
            // Enlace: { etiqueta, url, externo }
            $table->json('enlace')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->foreign('imagen_id')->references('id')->on('portal_imagenes');
            $table->index(['estado', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_noticias');
        Schema::dropIfExists('portal_sedes');
        Schema::dropIfExists('portal_niveles');
        Schema::dropIfExists('portal_cifras');
        Schema::dropIfExists('portal_banners');
    }
};
