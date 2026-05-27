<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->foreign('empleado_id')->references('id')->on('empleados')->cascadeOnDelete();
        });

        Schema::table('descuentos', function (Blueprint $table) {
            $table->foreign('empleado_id')->references('id')->on('empleados')->cascadeOnDelete();
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->foreign('empleado_id')->references('id')->on('empleados')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropForeign(['empleado_id']);
        });

        Schema::table('descuentos', function (Blueprint $table) {
            $table->dropForeign(['empleado_id']);
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropForeign(['empleado_id']);
        });
    }
};