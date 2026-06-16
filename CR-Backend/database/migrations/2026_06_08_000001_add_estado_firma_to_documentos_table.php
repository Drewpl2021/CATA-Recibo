<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->enum('estado_firma', ['pendiente', 'visto', 'firmado'])
                  ->default('pendiente')
                  ->after('fecha_firma');
            $table->uuid('planilla_id')->nullable()->after('estado_firma');
            $table->timestamp('fecha_visto')->nullable()->after('planilla_id');

            $table->foreign('planilla_id')
                  ->references('id')
                  ->on('planilla')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropForeign(['planilla_id']);
            $table->dropColumn(['estado_firma', 'planilla_id', 'fecha_visto']);
        });
    }
};