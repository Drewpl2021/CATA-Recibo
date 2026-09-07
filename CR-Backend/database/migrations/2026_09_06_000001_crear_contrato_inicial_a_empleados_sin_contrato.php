<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Le arma su contrato a quien fue dado de alta antes de que el alta lo
 * creara sola.
 *
 * Hasta ahora, dar de alta a un trabajador creaba su ficha y su usuario,
 * pero ningún contrato: la ficha decía "plazo fijo" y su historial de
 * contratos estaba vacío. Los datos para armarlo ya los tenía el propio
 * empleado (tipo_contrato y fecha_ingreso), así que esta migración los
 * pasa a donde corresponde.
 *
 * Solo toca a quien no tenga NINGÚN contrato: a nadie se le duplica ni se
 * le pisa lo que ya tiene.
 *
 * La fecha de término queda vacía a propósito, incluso en los de plazo
 * fijo: inventarse una sería peor que dejarla en blanco para que RR.HH.
 * la complete. En las altas nuevas ya se pide.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sinContrato = DB::table('empleados')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('contratos')
                  ->whereColumn('contratos.empleado_id', 'empleados.id');
            })
            ->get(['id', 'tipo_contrato', 'fecha_ingreso']);

        $ahora    = now();
        $creados  = 0;
        $saltados = 0;

        foreach ($sinContrato as $empleado) {
            // Sin tipo o sin fecha de ingreso no hay con qué armarlo; se salta
            // en vez de inventar datos.
            if (empty($empleado->tipo_contrato) || empty($empleado->fecha_ingreso)) {
                $saltados++;
                continue;
            }

            DB::table('contratos')->insert([
                'id'            => (string) Str::uuid(),
                'empleado_id'   => $empleado->id,
                'tipo_contrato' => $empleado->tipo_contrato,
                'fecha_inicio'  => $empleado->fecha_ingreso,
                'fecha_fin'     => null,
                'estado'        => 'vigente',
                'observaciones' => 'Contrato inicial reconstruido a partir de la ficha del trabajador.',
                'created_at'    => $ahora,
                'updated_at'    => $ahora,
            ]);

            $creados++;
        }

        // Se dice en voz alta: si alguien queda fuera hay que saberlo, no
        // descubrirlo meses después con un historial vacío.
        echo "   contratos creados: {$creados}
";
        if ($saltados > 0) {
            echo "   sin tipo de contrato o sin fecha de ingreso, se saltaron: {$saltados}
";
            echo "   (revísalos en Empleados y créales el contrato desde Contratos)
";
        }
    }

    public function down(): void
    {
        // Solo se van los que creó esta migración; los escritos a mano se quedan.
        DB::table('contratos')
            ->where('observaciones', 'Contrato inicial reconstruido a partir de la ficha del trabajador.')
            ->delete();
    }
};
