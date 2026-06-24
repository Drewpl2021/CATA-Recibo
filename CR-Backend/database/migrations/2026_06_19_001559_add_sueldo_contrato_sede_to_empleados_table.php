<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->decimal('sueldo_base', 10, 2)->nullable()->after('cuspp');
            $table->enum('tipo_contrato', ['por_hora', 'necesidad_servicio', 'indeterminado'])->nullable()->after('sueldo_base');
            $table->enum('forma_pago', ['banco', 'efectivo', 'otro'])->nullable()->after('tipo_contrato');
            $table->uuid('sede_id')->nullable()->after('forma_pago');

            $table->foreign('sede_id')->references('id')->on('sedes')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropForeign(['sede_id']);
            $table->dropColumn(['sueldo_base', 'tipo_contrato', 'forma_pago', 'sede_id']);
        });
    }
};