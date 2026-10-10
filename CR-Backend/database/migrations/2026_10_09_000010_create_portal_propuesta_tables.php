<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El cuerpo de Propuesta Educativa en el portal (§3.23 a §3.26): pilares,
 * niveles, la academia preuniversitaria y las plataformas digitales. La
 * cabecera, la introducción de los pilares y el título de la academia van en
 * portal_secciones; sus cifras, en portal_cifras. Reglas comunes: ver las
 * listas de Inicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_pilares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            $table->string('titulo');
            $table->text('texto');
            // string[]: aspectos del pilar ("Espiritual")
            $table->json('puntos');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->index(['estado', 'orden']);
        });

        // Los niveles como los presenta Propuesta Educativa. Lista propia,
        // aparte de la de Inicio: ver portal_niveles.
        Schema::create('portal_niveles_propuesta', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            $table->string('nombre', 100);
            $table->string('rango')->nullable();
            // string[]: párrafos
            $table->json('descripcion');
            // string[]: frases breves
            $table->json('rasgos');
            // [{ titulo, texto }]
            $table->json('talleres');
            $table->uuid('imagen_id')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->foreign('imagen_id')->references('id')->on('portal_imagenes');
            $table->index(['estado', 'orden']);
        });

        Schema::create('portal_universidades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            $table->string('nombre');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->index(['estado', 'orden']);
        });

        // Los ciclos de cada universidad. Precios y fechas solo si están
        // vigentes: si no, null, y el portal no los muestra.
        Schema::create('portal_ciclos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            $table->uuid('universidad_id');
            $table->string('nombre', 100);
            $table->string('turno', 60)->nullable();
            // ["presencial", "virtual"]: al menos una
            $table->json('modalidades');
            // En soles.
            $table->decimal('precio', 8, 2)->nullable();
            $table->decimal('precio_virtual', 8, 2)->nullable();
            $table->date('inicio')->nullable();
            $table->date('fin')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->foreign('universidad_id')->references('id')->on('portal_universidades')->cascadeOnDelete();
            $table->index(['universidad_id', 'orden']);
        });

        Schema::create('portal_plataformas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            // [{ titulo, texto }]: qué ofrece a quién
            $table->json('funciones');
            // Enlace: { etiqueta, url, externo }
            $table->json('enlace')->nullable();
            $table->string('aclaracion', 100)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->index(['estado', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_plataformas');
        Schema::dropIfExists('portal_ciclos');
        Schema::dropIfExists('portal_universidades');
        Schema::dropIfExists('portal_niveles_propuesta');
        Schema::dropIfExists('portal_pilares');
    }
};
