<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilla', function (Blueprint $table) {
            $table->uuid('periodo_id')->nullable()->after('anio');
            $table->foreign('periodo_id')->references('id')->on('periodos')->nullOnDelete();
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->uuid('periodo_id')->nullable()->after('empleado_id');
            $table->foreign('periodo_id')->references('id')->on('periodos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('planilla', function (Blueprint $table) {
            $table->dropForeign(['periodo_id']);
            $table->dropColumn('periodo_id');
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropForeign(['periodo_id']);
            $table->dropColumn('periodo_id');
        });
    }
};