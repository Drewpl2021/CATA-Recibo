<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De dónde salió una planilla del mes: armada por el sistema (null) o traída
 * del PLAME de RR.HH. ('plame').
 *
 * Los meses que no pasaron por el sistema (enero a julio de 2026) se cargan
 * desde el PLAME tal como se pagaron y declararon. Esas planillas no se
 * recalculan ni se reabren: si el PLAME tenía un error, se corrige el Excel y
 * se vuelve a importar, y la importación nueva reemplaza a la anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planilla_corridas', function (Blueprint $table) {
            $table->string('origen', 20)->nullable()->after('estado');
            $table->string('archivo_origen', 150)->nullable()->after('origen');
        });
    }

    public function down(): void
    {
        Schema::table('planilla_corridas', function (Blueprint $table) {
            $table->dropColumn(['origen', 'archivo_origen']);
        });
    }
};
