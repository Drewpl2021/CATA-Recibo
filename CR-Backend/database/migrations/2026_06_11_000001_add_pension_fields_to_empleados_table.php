<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->enum('sistema_pensiones', ['AFP', 'ONP'])->default('ONP')->after('estado');
            $table->enum('afp', ['Habitat', 'Integra', 'Prima', 'Profuturo'])->nullable()->after('sistema_pensiones');
            $table->string('cuspp', 20)->nullable()->after('afp');
            $table->string('entidad_financiera', 100)->nullable()->after('cuspp');
            $table->string('numero_cuenta', 50)->nullable()->after('entidad_financiera');
            $table->boolean('tiene_hijos')->default(false)->after('numero_cuenta');
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn([
                'sistema_pensiones',
                'afp',
                'cuspp',
                'entidad_financiera',
                'numero_cuenta',
                'tiene_hijos',
            ]);
        });
    }
};