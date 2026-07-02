<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->enum('nivel_estudios', [
                'primaria',
                'secundaria',
                'tecnico',
                'universitario',
                'maestria',
                'doctorado'
            ])->nullable()->after('firma_imagen');

            $table->string('especialidad')->nullable()->after('nivel_estudios');

            $table->string('institucion_estudios')->nullable()->after('especialidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn(['nivel_estudios', 'especialidad', 'institucion_estudios']);
        });
    }
};