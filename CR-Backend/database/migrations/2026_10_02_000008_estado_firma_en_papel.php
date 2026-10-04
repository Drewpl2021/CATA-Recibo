<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Firmada en papel": la boleta de un año anterior que se arma ahora para
 * dejarla de registro. El trabajador ya la firmó a mano en su momento, así
 * que no queda "pendiente" ni se le pide firmarla otra vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->enum('estado_firma', ['pendiente', 'visto', 'firmado', 'en_papel'])->default('pendiente')->change();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->enum('estado_firma', ['pendiente', 'visto', 'firmado'])->default('pendiente')->change();
        });
    }
};
