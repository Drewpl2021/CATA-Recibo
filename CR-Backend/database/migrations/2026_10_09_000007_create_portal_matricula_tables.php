<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las listas de la página Matrícula del portal y las opciones de grado del
 * formulario «Solicitar información». La cabecera, la nota de vacantes, la
 * del cronograma y los textos del formulario van en portal_secciones.
 * Reglas comunes: ver la migración de las listas de Inicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Un proceso por tipo de estudiante (§3.11). Pasos y listas siempre se
        // editan con su proceso y en su orden: van en JSON.
        Schema::create('portal_procesos_matricula', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            $table->string('nombre', 100);
            $table->string('resumen')->nullable();
            // [{ titulo, detalle }]
            $table->json('pasos');
            // [{ titulo, items: string[] }]
            $table->json('listas');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->index(['estado', 'orden']);
        });

        // Vacantes por nivel (§3.12). `disponibilidad` es el `estado` del
        // contrato; se llama distinto para no chocar con el `estado` de
        // publicación que llevan todas las tablas del portal.
        Schema::create('portal_vacantes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            $table->string('nivel', 100);
            $table->string('detalle')->nullable();
            // disponible, pocas, agotadas, consultar
            $table->string('disponibilidad', 20)->default('consultar');
            $table->unsignedInteger('vacantes')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->index(['estado', 'orden']);
        });

        // El cronograma (§3.13), en orden cronológico.
        Schema::create('portal_fechas_matricula', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            $table->string('titulo');
            $table->date('inicio');
            // null = un solo día
            $table->date('fin')->nullable();
            $table->string('detalle')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->index(['estado', 'inicio']);
        });

        // Preguntas frecuentes (§3.14).
        Schema::create('portal_preguntas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('clave', 60)->unique();
            $table->string('pregunta');
            $table->text('respuesta');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->index(['estado', 'orden']);
        });

        // Las opciones del selector de grado (§3.8), agrupadas por nivel. El
        // contrato no les da id: el grupo se reconoce por su nivel.
        Schema::create('portal_grados', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nivel', 100);
            // string[]
            $table->json('opciones');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->index(['estado', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_grados');
        Schema::dropIfExists('portal_preguntas');
        Schema::dropIfExists('portal_fechas_matricula');
        Schema::dropIfExists('portal_vacantes');
        Schema::dropIfExists('portal_procesos_matricula');
    }
};
