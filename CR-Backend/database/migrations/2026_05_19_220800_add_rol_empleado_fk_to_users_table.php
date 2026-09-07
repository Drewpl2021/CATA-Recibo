<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('rol_id')->nullable()->after('password');
            $table->uuid('empleado_id')->nullable()->after('rol_id');
            $table->string('estado_registro')->default('activo')->after('empleado_id');

            $table->foreign('rol_id')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('empleado_id')->references('id')->on('empleados')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['rol_id']);
            $table->dropForeign(['empleado_id']);
            $table->dropColumn(['rol_id', 'empleado_id', 'estado_registro']);
        });
    }
};
