<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las líneas de la planilla con sus decimales completos, como el PLAME.
 *
 * El Excel del colegio calcula la comisión de AFP como 44.89485, el EsSalud
 * como 239.085 o el diezmo como 181.125, suma esos montos tal cual y recién
 * redondea el total. El sistema redondeaba cada línea a 2 decimales antes de
 * sumar, y en 17 de 94 trabajadores el total de descuentos y el neto salían
 * con un céntimo de diferencia (Gutiérrez Calsin: 2,389.49 contra 2,389.50).
 *
 * Ahora cada línea guarda 6 decimales; la boleta y las pantallas la muestran
 * con 2, y los totales se redondean al final, igual que el Excel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_detalles', function (Blueprint $table) {
            $table->decimal('monto_calculado', 14, 6)->change();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_detalles', function (Blueprint $table) {
            $table->decimal('monto_calculado', 10, 2)->change();
        });
    }
};
