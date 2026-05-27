<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->uuid('area_id')->nullable()->after('area');
            $table->uuid('cargo_id')->nullable()->after('cargo');
            $table->foreign('area_id')->references('id')->on('areas')->nullOnDelete();
            $table->foreign('cargo_id')->references('id')->on('cargos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropForeign(['cargo_id']);
            $table->dropColumn(['area_id', 'cargo_id']);
        });
    }
};