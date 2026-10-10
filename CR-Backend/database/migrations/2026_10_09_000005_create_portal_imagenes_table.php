<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las imágenes del portal, con lo que pide el contrato de cada una: dónde
 * está, su texto alternativo y su tamaño.
 *
 * Una fila por USO, no por archivo: el `alt` depende de dónde se muestra la
 * foto (en un afiche repite su texto; junto al nombre de un docente puede ir
 * vacío), así que la misma foto en dos lugares son dos filas.
 *
 * La imagen está en una de dos partes:
 *   - `ruta`: un archivo subido desde el panel (disco 'portal'), que se sirve
 *     por /api/portal/medios.
 *   - `url_externa`: una dirección https de fuera (las fotos que el colegio
 *     ya tiene publicadas en cata.edu.pe).
 *
 * `alt` no admite null: el contrato exige decidirlo al subir la imagen, y
 * "" significa "decorativa". Es text porque el de un afiche repite todo lo
 * que dice el afiche y puede pasar de los 150 caracteres recomendados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_imagenes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ruta')->nullable();
            $table->string('url_externa', 2048)->nullable();
            $table->text('alt');
            $table->unsignedInteger('ancho')->nullable();
            $table->unsignedInteger('alto')->nullable();
            $table->string('mime', 60)->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_imagenes');
    }
};
