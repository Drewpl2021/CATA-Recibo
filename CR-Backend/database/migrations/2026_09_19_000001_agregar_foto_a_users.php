<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La foto de perfil de cada cuenta.
 *
 * Guarda la RUTA, no la imagen: el archivo va al disco privado
 * (storage/app/private), igual que la firma y la huella. Una foto de la cara
 * es un dato personal (Ley N° 29733) y no puede quedar colgando de una URL
 * pública que cualquiera pueda adivinar o compartir.
 *
 * Va en `users` y no en `empleados` a propósito: la cuenta institucional de
 * RR.HH. o de sistemas no tiene ficha de empleado, y también necesita
 * reconocerse en la cabecera.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'foto')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('foto', 255)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'foto')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
