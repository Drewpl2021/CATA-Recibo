<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las redes sociales oficiales del colegio, como las muestra el portal: la
 * barra flotante, el menú móvil, el pie y «Síguenos» de Contacto.
 *
 * Hasta 8, sin repetir red (de ahí el unique). El nombre, el icono y el color
 * de cada red los pone el portal; aquí va lo que cambia: la cuenta, su
 * dirección y, si la dirección aún no está confirmada, el aviso que se
 * muestra junto a ella (`aclaracion`).
 *
 * `estado`: 'publicado' o 'borrador'. Como en el resto del sistema, casi nada
 * se borra: una red que se deja de mostrar pasa a borrador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_redes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // facebook, youtube, tiktok, whatsapp, instagram, x, linkedin
            $table->string('red', 20)->unique();
            $table->string('cuenta', 60);
            $table->string('url', 255);
            $table->string('aclaracion', 80)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 20)->default('publicado');
            $table->timestamps();

            $table->index(['estado', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_redes');
    }
};
