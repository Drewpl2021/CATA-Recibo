<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Empleado;
use App\Models\Planilla;

class PlanillaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $empleado = Empleado::where('dni', '12345678')->first();

        if (! $empleado) {
            throw new ModelNotFoundException('PlanillaDemoSeeder requiere el empleado de prueba (DNI 12345678) creado por UsuarioDemoSeeder.');
        }

        Planilla::where('empleado_id', $empleado->id)->delete();

        // [mes, anio, sueldo_base, bonificaciones, descuentos, total]
        $boletas = [
            [1, 2026, 2500.00, 417.66, 119.31, 2798.35],
            [2, 2026, 2500.00, 283.86, 64.43, 2719.43],
            [3, 2026, 2500.00, 178.94, 136.65, 2542.29],
            [4, 2026, 2500.00, 307.09, 60.07, 2747.02],
            [5, 2026, 2500.00, 261.59, 56.65, 2704.94],
            [6, 2026, 2500.00, 251.54, 154.98, 2596.56],
            [7, 2026, 2500.00, 257.44, 129.79, 2627.65],
            [8, 2026, 2500.00, 489.61, 200.16, 2789.45],
            [9, 2026, 2500.00, 240.81, 184.26, 2556.55],
            [10, 2026, 2500.00, 379.11, 150.31, 2728.80],
            [11, 2026, 2500.00, 314.21, 71.44, 2742.77],
            [12, 2026, 2500.00, 187.92, 51.39, 2636.53],
            [4, 2025, 0.00, 0.00, 0.00, 0.00],
        ];

        foreach ($boletas as [$mes, $anio, $sueldoBase, $bonificaciones, $descuentos, $total]) {
            Planilla::create([
                'empleado_id'    => $empleado->id,
                'mes'            => $mes,
                'anio'           => $anio,
                'sueldo_base'    => $sueldoBase,
                'bonificaciones' => $bonificaciones,
                'descuentos'     => $descuentos,
                'total'          => $total,
            ]);
        }
    }
}
