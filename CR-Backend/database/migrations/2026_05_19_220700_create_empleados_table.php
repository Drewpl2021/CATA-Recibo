<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('dni', 8)->unique();
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->uuid('area_id')->nullable();
            $table->uuid('cargo_id')->nullable();
            $table->string('telefono', 15)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->date('fecha_ingreso');
            $table->string('estado', 20)->default('activo');
            $table->enum('sistema_pensiones', ['AFP', 'ONP'])->default('ONP');
            $table->enum('afp', ['Habitat', 'Integra', 'Prima', 'Profuturo'])->nullable();
            $table->string('cuspp', 20)->nullable();
            $table->string('entidad_financiera', 100)->nullable();
            $table->string('numero_cuenta', 50)->nullable();
            $table->boolean('tiene_hijos')->default(false);
            $table->decimal('sueldo_base', 10, 2)->nullable();
            $table->enum('tipo_contrato', ['indeterminado', 'plazo_fijo', 'suplencia', 'practicas'])->nullable();
            $table->enum('forma_pago', ['banco', 'efectivo', 'otro'])->nullable();
            $table->uuid('sede_id')->nullable();
            $table->enum('nivel_estudios', [
                'primaria', 'secundaria', 'tecnico', 'universitario', 'maestria', 'doctorado',
            ])->nullable();
            $table->string('especialidad')->nullable();
            $table->string('institucion_estudios')->nullable();
            $table->string('contacto_emergencia_nombre')->nullable();
            $table->string('contacto_emergencia_telefono')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->timestamps();

            $table->foreign('area_id')->references('id')->on('areas')->nullOnDelete();
            $table->foreign('cargo_id')->references('id')->on('cargos')->nullOnDelete();
            $table->foreign('sede_id')->references('id')->on('sedes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
