<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pone al día las bases creadas ANTES de que ciertas columnas se agregaran
 * editando las migraciones "create_*" en lugar de escribir una nueva.
 *
 * `migrate` no vuelve a correr una migración ya ejecutada, así que en esas
 * bases las columnas nunca aparecieron y, por ejemplo, aceptar los términos
 * de uso fallaba con "Unknown column 'terminos_firmados'".
 *
 * Cada paso pregunta primero si la columna existe: en una base nueva (donde
 * la migración original ya la creó) no hace nada, y se puede correr dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'terminos_firmados')) {
                $table->boolean('terminos_firmados')->default(false);
            }
            if (! Schema::hasColumn('users', 'terminos_firmados_en')) {
                $table->timestamp('terminos_firmados_en')->nullable();
            }
            if (! Schema::hasColumn('users', 'terminos_version')) {
                $table->string('terminos_version', 20)->nullable();
            }
            if (! Schema::hasColumn('users', 'terminos_ip')) {
                $table->string('terminos_ip', 45)->nullable();
            }
        });

        Schema::table('documentos', function (Blueprint $table) {
            if (! Schema::hasColumn('documentos', 'fecha_aviso')) {
                $table->timestamp('fecha_aviso')->nullable();
            }
            if (! Schema::hasColumn('documentos', 'aviso_correo')) {
                $table->string('aviso_correo', 150)->nullable();
            }
            if (! Schema::hasColumn('documentos', 'fecha_descarga')) {
                $table->timestamp('fecha_descarga')->nullable();
            }
            if (! Schema::hasColumn('documentos', 'descargas')) {
                $table->unsignedInteger('descargas')->default(0);
            }
        });

        Schema::table('payment_concepts', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_concepts', 'etiqueta_boleta')) {
                $table->string('etiqueta_boleta', 150)->nullable();
            }
        });

        if (! Schema::hasColumn('planilla', 'corrida_id')) {
            Schema::table('planilla', function (Blueprint $table) {
                $table->uuid('corrida_id')->nullable();
                $table->foreign('corrida_id')->references('id')->on('planilla_corridas')->nullOnDelete();
            });
        }
    }

    /**
     * No se deshace a propósito: en una base nueva estas columnas las crean
     * las migraciones originales, y quitarlas aquí las dejaría sin ellas.
     */
    public function down(): void
    {
    }
};
