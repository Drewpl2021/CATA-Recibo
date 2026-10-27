<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La ruta de medios del portal busca cada archivo que se pide en
 * portal_imagenes (solo sirve los registrados). Es la consulta de cada foto
 * de cada visita a cata.edu.pe: con índice.
 *
 * No es único: una misma subida puede usarse en dos lugares con distinto
 * texto alternativo, y entonces son dos filas con la misma ruta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_imagenes', function (Blueprint $table) {
            $table->index('ruta');
        });
    }

    public function down(): void
    {
        Schema::table('portal_imagenes', function (Blueprint $table) {
            $table->dropIndex(['ruta']);
        });
    }
};
