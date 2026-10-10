<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los textos «de una sola vez» del portal del colegio (cata.edu.pe).
 *
 * Cada página del portal tiene contenido que no es una lista sino una ficha
 * única: los datos del colegio (nombre, teléfono, WhatsApp, dirección), la
 * cabecera de Matrícula, el texto de Quiénes somos… Para no crear una tabla
 * de una fila por cada uno, van aquí como en `configuraciones`: una fila por
 * sección, y su contenido en JSON con los campos que pide el contrato de la
 * API del portal. Las listas (redes, sedes, noticias…) tienen sus tablas.
 *
 * Todas las tablas del módulo llevan el prefijo `portal_`: el sistema ya
 * tiene `sedes`, `documentos` y `configuraciones` de RR.HH., y lo que se
 * publica en internet no debe mezclarse con eso.
 *
 * Nace vacía. El contenido se carga desde el panel (o con el importador
 * inicial): nada de lo que muestra el portal se escribe en el código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_secciones', function (Blueprint $table) {
            // 'sitio', 'matricula-cabecera', 'quienes-somos'…
            $table->string('clave', 60)->primary();
            $table->json('contenido');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_secciones');
    }
};
