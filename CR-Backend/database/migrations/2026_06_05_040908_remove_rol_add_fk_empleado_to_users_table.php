<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rol');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('empleado_id')->nullable()->change();
            $table->foreign('empleado_id')->references('id')->on('empleados')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['empleado_id']);
            $table->string('rol', 255)->nullable();
        });
    }
};