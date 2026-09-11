<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién cambió qué, y cuándo.
 *
 * En un sistema de planillas es lo primero que se pregunta ante un reclamo:
 * "¿quién me bajó el sueldo?", "¿quién le aplicó ese descuento?", "¿quién
 * reabrió una planilla ya pagada?". Antes no quedaba rastro de nada.
 *
 * Solo se escribe, nunca se edita: por eso el id es un contador y no un
 * UUID —las filas llegan en orden y así se guardan—, y solo lleva
 * `created_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();

            // Quién. El nombre se copia además del id: si mañana se borra la
            // cuenta, el registro tiene que seguir diciendo quién fue.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre', 150)->nullable();

            // Qué: "cambió", sobre un "empleado", el de este id.
            $table->string('accion', 20);
            $table->string('entidad', 40);
            $table->string('entidad_id', 36)->nullable();

            // En palabras, para leerlo sin abrir el JSON: "Cambió el sueldo de
            // Wilber Apaza (DNI 42558107)".
            $table->string('descripcion', 255);

            // Campo por campo: {"sueldo_base": ["2700.00", "3000.00"]}.
            $table->json('cambios')->nullable();

            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // La historia de una ficha, y el listado ordenado por fecha.
            $table->index(['entidad', 'entidad_id'], 'auditoria_entidad_idx');
            $table->index('created_at', 'auditoria_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
