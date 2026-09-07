<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda la firma y la huella digital "de archivo" de cada empleado — las dos
     * marcas que, igual que en un documento físico peruano, se estampan juntas en
     * la boleta cuando queda firmada. Va en su propia tabla (y no como columnas
     * sueltas en empleados) porque:
     *  - Agrupa todo lo que sirve para "verificar que es él cuando firma" en un
     *    solo lugar, separado de sus datos laborales.
     *  - Permite saber cuándo se registró/actualizó y quién lo hizo (RRHH puede
     *    subirla por el empleado, o el empleado la sube él mismo).
     *  - Las imágenes se guardan en el disco privado (storage/app/private) — una
     *    huella dactilar es dato sensible según la Ley N° 29733, así que nunca
     *    debe quedar accesible por una URL pública sin autenticación.
     */
    public function up(): void
    {
        Schema::create('identidades_firma', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('empleado_id')->unique();
            $table->string('firma_imagen')->nullable();
            $table->string('huella_imagen')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('empleado_id')->references('id')->on('empleados')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identidades_firma');
    }
};
