<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El neto de cada planilla con todos sus decimales, para sumar como Excel.
 *
 * El PLAME suma los netos SIN redondear (1,129.845 + …) y redondea solo el
 * total: 178,790.91. El sistema sumaba los netos ya redondeados al centavo
 * y daba 178,790.93. RR.HH. pidió que cuadre con el Excel, así que cada
 * planilla guarda también su neto exacto (`total_exacto`) y los totales se
 * sacan de ahí. `total` sigue siendo el neto al centavo, el que se paga y
 * sale en la boleta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilla', function (Blueprint $table) {
            $table->decimal('total_exacto', 16, 6)->nullable()->after('total');
        });

        // Las que ya existen: sueldo + ingresos − descuentos − adelantos, con
        // los 6 decimales de cada línea (lo mismo que hace recalcularTotal).
        $suma = fn (string $tipo) => "COALESCE((SELECT SUM(pd.monto_calculado) FROM payroll_detalles pd
            JOIN payment_concepts pc ON pc.id = pd.payment_concept_id
            WHERE pd.planilla_id = planilla.id AND pc.tipo = '{$tipo}'), 0)";

        DB::statement('UPDATE planilla SET total_exacto = sueldo_base + ' . $suma('bonificacion')
            . ' - ' . $suma('descuento') . ' - ' . $suma('adelanto'));
    }

    public function down(): void
    {
        Schema::table('planilla', function (Blueprint $table) {
            $table->dropColumn('total_exacto');
        });
    }
};
