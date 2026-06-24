<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilla', function (Blueprint $table) {
            $table->string('estado_registro')->default('activo');
        });
    }

    public function down(): void
    {
        Schema::table('planilla', function (Blueprint $table) {
            $table->dropColumn('estado_registro');
        });
    }
};