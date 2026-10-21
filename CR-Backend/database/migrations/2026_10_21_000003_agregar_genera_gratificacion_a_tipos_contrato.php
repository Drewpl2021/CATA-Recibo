<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qué tipos de contrato generan gratificación (Ley 27735), como `genera_cts`.
 *
 * No la generan las prácticas (modalidades formativas: cobran media
 * subvención aparte) ni las jornadas de menos de cuatro horas al día. El
 * colegio confirmó el 21/10/2026 que «Contrato/Parcial» trabaja menos de
 * cuatro horas: por eso tampoco genera CTS, y se le desmarca acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_contrato', function (Blueprint $table) {
            $table->boolean('genera_gratificacion')->default(true)->after('genera_cts');
        });

        $sinBeneficios = fn () => DB::table('tipos_contrato')
            ->where(fn ($q) => $q->where('nombre', 'like', 'Pr_ctica%')->orWhere('nombre', 'like', '%Parcial%'));
        $sinBeneficios()->update(['genera_gratificacion' => false]);
        DB::table('tipos_contrato')->where('nombre', 'like', '%Parcial%')->update(['genera_cts' => false]);
    }

    public function down(): void
    {
        Schema::table('tipos_contrato', function (Blueprint $table) {
            $table->dropColumn('genera_gratificacion');
        });
    }
};
