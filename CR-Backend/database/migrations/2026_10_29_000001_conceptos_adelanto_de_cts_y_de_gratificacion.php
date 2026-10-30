<?php

use App\Support\ConceptosDePago;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * «Adelanto de CTS» y «Adelanto de Gratificación».
 *
 * El PLAME de mayo trae la CTS como ingreso y el mismo monto como «ADELANTO
 * DE CTS» (se deposita aparte); el de julio, la gratificación y su
 * «ADELANTO DE GRATIFICACIONES». Sin conceptos propios, el importe mandaba
 * los dos adelantos de mayo a «Adelanto de Sueldo» y el de julio a la misma
 * gratificación, y no se podía importar.
 *
 * Las bases nuevas los traen del seeder; esta migración los agrega a las
 * que ya existen. No duplica: si ya está, lo deja.
 */
return new class extends Migration
{
    private const CONCEPTOS = [
        ConceptosDePago::ADELANTO_CTS => 'La CTS que figura como ingreso en la planilla del mes y se resta del neto porque se deposita aparte, en su cuenta CTS.',
        ConceptosDePago::ADELANTO_GRATIFICACION => 'La gratificación que ya se le entregó antes y se resta del neto en la planilla de julio o diciembre, donde figura como ingreso.',
    ];

    public function up(): void
    {
        // Solo en una base ya sembrada: en una vacía los pone el seeder.
        if (! DB::table('payment_concepts')->exists()) {
            return;
        }

        foreach (self::CONCEPTOS as $nombre => $descripcion) {
            if (DB::table('payment_concepts')->where('nombre', $nombre)->exists()) {
                continue;
            }
            DB::table('payment_concepts')->insert([
                'id' => (string) Str::uuid7(), 'nombre' => $nombre, 'tipo' => 'adelanto',
                'calculo' => null, 'valor' => null, 'descripcion' => $descripcion,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Solo si nadie los usa: un concepto con montos en planillas no se borra.
        foreach (array_keys(self::CONCEPTOS) as $nombre) {
            $id = DB::table('payment_concepts')->where('nombre', $nombre)->value('id');
            if ($id && ! DB::table('payroll_detalles')->where('payment_concept_id', $id)->exists()) {
                DB::table('payment_concepts')->where('id', $id)->delete();
            }
        }
    }
};
