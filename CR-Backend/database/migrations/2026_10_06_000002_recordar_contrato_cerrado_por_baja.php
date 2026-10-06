<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Para que dar de baja se pueda deshacer.
 *
 * Al dar de baja se cierra el contrato vigente con la fecha de hoy. Al
 * volver a activar a esa persona, el contrato se quedaba cerrado: sin
 * contrato vigente, la Renta de 5ta le sumaba vacaciones truncas (como si
 * fuera contratado) y su fin de contrato (31/12) se perdía. Pasó con diez
 * trabajadores a los que se dio de baja y se reactivó al probar la barra.
 *
 * Ahora el contrato recuerda que lo cerró una baja y cuál era su fin de
 * antes, y la reactivación lo reabre tal cual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->boolean('cerrado_por_baja')->default(false)->after('motivo_fin');
            $table->date('fin_antes_de_baja')->nullable()->after('cerrado_por_baja');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn(['cerrado_por_baja', 'fin_antes_de_baja']);
        });
    }
};
