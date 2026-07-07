<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ampliar el enum temporalmente para que acepte valores viejos y nuevos a la vez
        Schema::table('empleados', function (Blueprint $table) {
            $table->enum('tipo_contrato', [
                'por_hora', 'necesidad_servicio', 'indeterminado',
                'plazo_fijo', 'suplencia', 'practicas'
            ])->nullable()->after('sueldo_base')->change();
        });

        // 2. Migrar datos existentes a los nuevos valores
        DB::table('empleados')
            ->where('tipo_contrato', 'necesidad_servicio')
            ->update(['tipo_contrato' => 'plazo_fijo']);

        DB::table('empleados')
            ->where('tipo_contrato', 'por_hora')
            ->update(['tipo_contrato' => 'plazo_fijo']);

        // 3. Reducir el enum a solo los 4 valores finales
        Schema::table('empleados', function (Blueprint $table) {
            $table->enum('tipo_contrato', ['indeterminado', 'plazo_fijo', 'suplencia', 'practicas'])
                  ->nullable()
                  ->after('sueldo_base')
                  ->change();
        });
    }

    public function down(): void
    {
        // 1. Ampliar el enum de nuevo para poder revertir datos
        Schema::table('empleados', function (Blueprint $table) {
            $table->enum('tipo_contrato', [
                'por_hora', 'necesidad_servicio', 'indeterminado',
                'plazo_fijo', 'suplencia', 'practicas'
            ])->nullable()->after('sueldo_base')->change();
        });

        DB::table('empleados')
            ->where('tipo_contrato', 'plazo_fijo')
            ->update(['tipo_contrato' => 'necesidad_servicio']);

        // 2. Volver al enum original
        Schema::table('empleados', function (Blueprint $table) {
            $table->enum('tipo_contrato', ['por_hora', 'necesidad_servicio', 'indeterminado'])
                  ->nullable()
                  ->after('sueldo_base')
                  ->change();
        });
    }
};