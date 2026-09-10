<?php

namespace App\Traits;

use App\Models\Empleado;
use App\Models\Planilla;
use App\Models\PlanillaCorrida;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Armar las planillas de un mes para un grupo de trabajadores.
 *
 * Estaba escrito dentro de PeriodoController::generarPlanilla. Al aparecer las
 * corridas hacía falta lo mismo desde otro sitio, y copiarlo habría dejado dos
 * versiones de la regla más delicada del sistema —a quién se le paga, cuánto y
 * a quién se salta— que se irían separando con el tiempo.
 *
 * Lo que hace, en orden:
 *
 *   1. Decide A QUIÉNES: una lista concreta de ids, un grupo por área/cargo/
 *      sede, o todo el personal activo si no se acota nada.
 *   2. Se salta al que ya tiene planilla de ese mes (relanzar no duplica),
 *      al que no tiene sueldo puesto y al que todavía no había ingresado.
 *   3. Prorratea el sueldo del que entró a mitad de mes.
 *   4. Le mete sus conceptos automáticos (pensión, EsSalud, los fijos del
 *      catálogo, Renta de 5ta) y recalcula su total.
 *
 * Devuelve el resumen Y el detalle fila por fila, con el motivo de cada
 * omisión: un "generadas: 0, omitidas: 4" a secas no le dice a RR.HH. qué
 * arreglar.
 */
trait GeneraPlanillasEnLote
{
    use CalculaConceptosPlanilla;

    /** Las reglas de validación del grupo, iguales en los dos endpoints. */
    protected function reglasDelGrupo(): array
    {
        return [
            // A quiénes se les genera. Sin esto va a TODO el personal activo.
            'empleado_ids'   => 'sometimes|array|min:1',
            'empleado_ids.*' => 'uuid|exists:empleados,id|distinct',
            // Filtros para armar el grupo sin listar uno por uno
            // (se ignoran si ya se mandó empleado_ids).
            'area_id'  => 'sometimes|uuid|exists:areas,id',
            'cargo_id' => 'sometimes|uuid|exists:cargos,id',
            'sede_id'  => 'sometimes|uuid|exists:sedes,id',
        ];
    }

    /**
     * El personal al que le toca esta corrida, ya resuelto.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Empleado>
     */
    protected function empleadosDelGrupo(Request $request)
    {
        $consulta = Empleado::where('estado', 'activo');

        if ($request->filled('empleado_ids')) {
            $consulta->whereIn('id', $request->empleado_ids);
        } else {
            foreach (['area_id', 'cargo_id', 'sede_id'] as $filtro) {
                if ($request->filled($filtro)) {
                    $consulta->where($filtro, $request->input($filtro));
                }
            }
        }

        return $consulta->orderBy('apellido')->orderBy('nombre')->get();
    }

    /**
     * Genera las planillas del mes para esa gente.
     *
     * @param  \Illuminate\Support\Collection  $empleados
     * @return array{generadas:int, omitidas:int, evaluados:int, detalle:array}
     */
    protected function generarLote(
        $empleados,
        int $mes,
        int $anio,
        ?string $periodoId = null,
        ?PlanillaCorrida $corrida = null
    ): array {
        $generadas = 0;
        $omitidas  = 0;
        $detalle   = [];

        foreach ($empleados as $empleado) {
            $nombreCompleto = trim($empleado->nombre . ' ' . $empleado->apellido);
            $base = ['empleado' => $nombreCompleto, 'empleado_id' => $empleado->id];

            // Relanzar la generación no duplica: el que ya tiene su planilla
            // de ese mes se queda como está, con lo que se le haya ajustado.
            $yaExiste = Planilla::where('empleado_id', $empleado->id)
                ->where('mes', $mes)
                ->where('anio', $anio)
                ->first();

            if ($yaExiste) {
                $omitidas++;
                $detalle[] = $base + [
                    'estado' => 'omitida',
                    'motivo' => 'Ya existe planilla para este mes',
                    'planilla_id' => $yaExiste->id,
                ];
                continue;
            }

            if (empty($empleado->sueldo_base) || (float) $empleado->sueldo_base <= 0) {
                $omitidas++;
                $detalle[] = $base + ['estado' => 'omitida', 'motivo' => 'Empleado sin sueldo_base configurado'];
                continue;
            }

            // Lo que le toca cobrar este mes: el sueldo entero si ya estaba, o
            // la parte proporcional si entró a mitad.
            $reparto      = $this->repartoDeDiasDelMes($empleado, $mes, $anio);
            $sueldoDelMes = $this->sueldoDelMes($empleado, $mes, $anio);

            if ($sueldoDelMes === null) {
                $omitidas++;
                $detalle[] = $base + [
                    'estado' => 'omitida',
                    'motivo' => 'Todavía no había ingresado en ese mes (ingresó el '
                        . Carbon::parse($empleado->fecha_ingreso)->format('d/m/Y') . ')',
                ];
                continue;
            }

            $planilla = Planilla::create([
                'empleado_id'    => $empleado->id,
                'mes'            => $mes,
                'anio'           => $anio,
                'periodo_id'     => $periodoId,
                'corrida_id'     => $corrida?->id,
                'sueldo_base'    => $sueldoDelMes,
                'bonificaciones' => 0,
                'descuentos'     => 0,
                'total'          => 0,
            ]);

            $this->generarConceptosAutomaticos($planilla, $empleado);
            $planilla->recalcularTotal();

            $generadas++;
            $fila = $base + ['estado' => 'generada', 'planilla_id' => $planilla->id];

            // Si se le prorrateó, se dice: un sueldo distinto al de su ficha
            // sin explicación parece un error de cálculo.
            if ($reparto['entro_este_mes']) {
                $fila['motivo'] = 'Ingresó el ' . Carbon::parse($empleado->fecha_ingreso)->format('d/m/Y')
                    . ": se le pagan {$reparto['dias_pagados']} de {$reparto['dias_del_mes']} días";
            }

            $detalle[] = $fila;
        }

        return [
            'generadas' => $generadas,
            'omitidas'  => $omitidas,
            'evaluados' => $empleados->count(),
            'detalle'   => $detalle,
        ];
    }
}
