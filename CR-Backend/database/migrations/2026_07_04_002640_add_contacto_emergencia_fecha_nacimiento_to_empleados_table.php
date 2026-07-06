<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->string('contacto_emergencia_nombre')->nullable()->after('institucion_estudios');
            $table->string('contacto_emergencia_telefono')->nullable()->after('contacto_emergencia_nombre');
            $table->date('fecha_nacimiento')->nullable()->after('contacto_emergencia_telefono');
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn(['contacto_emergencia_nombre', 'contacto_emergencia_telefono', 'fecha_nacimiento']);
        });
    }
};