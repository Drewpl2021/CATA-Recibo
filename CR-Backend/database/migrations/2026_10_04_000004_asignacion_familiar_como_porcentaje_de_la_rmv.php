<?php

use App\Support\ConceptosDePago;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nada de montos de ley escritos a mano.
 *
 * 1. La Asignación Familiar deja de ser un monto que se tipea cada año: la
 *    ley dice "10% de la RMV", así que se guarda ese 10% y el monto sale de
 *    la RMV del año. Si sube el sueldo mínimo, sube sola.
 *
 * 2. Los conceptos de ley del catálogo (Asignación Familiar, ONP, AFP,
 *    EsSalud, Bonificación Extraordinaria) traían su 113 / 13% / 9% copiado.
 *    El motor no los usaba —lee los montos de ley del año—, pero estaban a la
 *    vista y se podían "corregir" sin efecto. Se vacían: su valor vive solo
 *    en Ajustes del sistema → Montos de ley.
 */
return new class extends Migration
{
    private const DE_LEY = [
        ConceptosDePago::ASIGNACION_FAMILIAR,
        ConceptosDePago::BONIF_EXTRAORDINARIA,
        ConceptosDePago::ONP,
        ConceptosDePago::SPP_FONDO,
        ConceptosDePago::SPP_PRIMA_SEGURO,
        ConceptosDePago::SPP_COMISION,
        ConceptosDePago::ESSALUD,
    ];

    public function up(): void
    {
        Schema::table('valores_legales', function (Blueprint $table) {
            $table->decimal('asignacion_familiar_pct', 5, 2)->default(10)->after('rmv');
        });
        Schema::table('valores_legales', function (Blueprint $table) {
            $table->dropColumn('asignacion_familiar');
        });

        DB::table('payment_concepts')->whereIn('nombre', self::DE_LEY)
            ->update(['calculo' => null, 'valor' => null, 'aplica_a_todos' => false]);
    }

    public function down(): void
    {
        Schema::table('valores_legales', function (Blueprint $table) {
            $table->decimal('asignacion_familiar', 10, 2)->default(0)->after('rmv');
        });
        DB::table('valores_legales')->update(['asignacion_familiar' => DB::raw('ROUND(rmv * asignacion_familiar_pct / 100, 2)')]);
        Schema::table('valores_legales', function (Blueprint $table) {
            $table->dropColumn('asignacion_familiar_pct');
        });
    }
};
