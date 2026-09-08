<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda CÓMO se calculó cada línea de la planilla, no solo cuánto salió.
 *
 * Hasta ahora una línea solo tenía el monto en soles. Si RR.HH. quería
 * ponerle a alguien un 5% de descuento, tenía que sacar la cuenta a mano —o
 * irse a la pantalla de Conceptos de Pago a cambiar el catálogo, que le
 * cambia el valor a TODO el colegio para arreglarle la planilla a uno.
 *
 * Con estos dos campos la línea se puede escribir como "5%" o como "S/ 100"
 * ahí mismo, y al volver a abrirla sigue diciendo 5% en vez de un 125 suelto
 * del que nadie recuerda de dónde salió.
 *
 * El monto en soles se sigue guardando: es lo que se imprime en la boleta y
 * lo que suma el total. Estos campos son el rastro de cómo se llegó a él, y
 * quedan en null en las líneas viejas y en las que se escriben en soles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_detalles', function (Blueprint $table) {
            $table->enum('calculo', ['fijo', 'porcentaje'])->nullable()->after('monto_calculado');
            // El 5 de "5%", o los 100 de "S/ 100".
            $table->decimal('valor', 10, 2)->nullable()->after('calculo');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_detalles', function (Blueprint $table) {
            $table->dropColumn(['calculo', 'valor']);
        });
    }
};
