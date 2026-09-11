<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_concepts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombre', 150)->unique();
            // Cómo se llama ESE concepto en la boleta impresa, cuando el
            // colegio lo nombra distinto que el catálogo.
            $table->string('etiqueta_boleta', 150)->nullable();
            $table->string('tipo', 45); // bonificacion | descuento | aportacion | adelanto
            $table->string('calculo', 45)->nullable(); // fijo | porcentaje
            $table->decimal('valor', 10, 2)->nullable();
            $table->string('descripcion', 255)->nullable();
            // Si es true, este concepto se aplica solo a TODOS los empleados cada vez que
            // se genera una planilla (fijo=mismo monto para todos, porcentaje=% del sueldo
            // de cada uno). Si es false (default), se agrega manualmente caso por caso.
            $table->boolean('aplica_a_todos')->default(false);
            $table->timestamps();

            // Se listan y se filtran por tipo, y el motor busca los fijos.
            $table->index('tipo', 'payment_concepts_tipo_idx');
            $table->index('aplica_a_todos', 'payment_concepts_aplica_a_todos_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_concepts');
    }
};