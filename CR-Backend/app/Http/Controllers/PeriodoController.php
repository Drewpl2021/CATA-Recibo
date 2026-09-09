<?php
namespace App\Http\Controllers;
use App\Models\Periodo;
use App\Models\Empleado;
use App\Models\Planilla;
use App\Traits\CalculaConceptosPlanilla;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Traits\ListadoPaginado;

class PeriodoController extends Controller
{
    use ListadoPaginado;

    use CalculaConceptosPlanilla;

    /**
     * GET /periodos
     *
     * Sin ?page devuelve la lista completa (así la piden los desplegables
     * de los formularios). Con ?page&size la corta el servidor y manda
     * además el total, para que el frontend no tenga que traerse todo
     * para saber cuántos hay. Ver App\Traits\ListadoPaginado.
     */
    public function index(Request $request)
    {
        return $this->responderListado(
            $request,
            Periodo::query()->orderBy('fecha_inicio', 'desc'),
            ['nombre']
        );
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:45',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);
        $periodo = Periodo::create($datos);
        return response()->json(['success' => true, 'data' => $periodo], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => Periodo::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $periodo = Periodo::findOrFail($id);
        $datos = $request->validate([
            'nombre' => 'sometimes|string|max:45',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => 'sometimes|date|after_or_equal:fecha_inicio',
        ]);
        $periodo->update($datos);
        return response()->json(['success' => true, 'data' => $periodo]);
    }

    public function destroy(string $id)
    {
        Periodo::findOrFail($id)->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Periodo eliminado.']]);
    }

    /**
     * Genera la Planilla del mes/año indicado para TODOS los empleados activos,
     * bajo este Periodo. Es idempotente: si un empleado ya tiene planilla de ese
     * mes, se omite (no falla el resto de la corrida). Cada planilla generada
     * dispara sus conceptos automáticos (pensión, EsSalud, fijos del catálogo,
     * Renta 5ta) tal como una planilla creada individualmente.
     */
    public function generarPlanilla(Request $request, string $id)
    {
        $periodo = Periodo::findOrFail($id);

        $request->validate([
            'mes'  => 'required|integer|min:1|max:12',
            'anio' => 'required|integer|min:2000',
            // Opcional: a quiénes se les genera. Sin esto va a TODO el
            // personal activo, que es como se usaba hasta ahora.
            'empleado_ids'   => 'sometimes|array|min:1',
            'empleado_ids.*' => 'uuid|exists:empleados,id|distinct',
            // Filtros para armar el grupo sin listar uno por uno
            // (se ignoran si ya se mandó empleado_ids).
            'area_id'  => 'sometimes|uuid|exists:areas,id',
            'cargo_id' => 'sometimes|uuid|exists:cargos,id',
            'sede_id'  => 'sometimes|uuid|exists:sedes,id',
        ]);

        $mes  = (int) $request->mes;
        $anio = (int) $request->anio;

        $fechaDelMes = Carbon::create($anio, $mes, 1);
        $inicioPeriodo = Carbon::parse($periodo->fecha_inicio)->startOfMonth();
        $finPeriodo    = Carbon::parse($periodo->fecha_fin)->endOfMonth();

        if ($fechaDelMes->lt($inicioPeriodo) || $fechaDelMes->gt($finPeriodo)) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => "El mes {$mes}/{$anio} no cae dentro del rango de este periodo ({$periodo->fecha_inicio} a {$periodo->fecha_fin})."],
            ], 422);
        }

        // A quiénes se les arma la planilla: una lista concreta, un grupo por
        // área/cargo/sede, o todo el personal activo si no se acota nada.
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

        $empleados = $consulta->orderBy('nombre')->get();

        if ($empleados->isEmpty()) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Ningún empleado activo coincide con el grupo indicado.'],
            ], 422);
        }

        $generadas = 0;
        $omitidas  = 0;
        $detalle   = [];

        foreach ($empleados as $empleado) {
            $nombreCompleto = trim($empleado->nombre . ' ' . $empleado->apellido);

            $yaExiste = Planilla::where('empleado_id', $empleado->id)
                ->where('mes', $mes)
                ->where('anio', $anio)
                ->exists();

            if ($yaExiste) {
                $omitidas++;
                $detalle[] = ['empleado' => $nombreCompleto, 'empleado_id' => $empleado->id, 'estado' => 'omitida', 'motivo' => 'Ya existe planilla para este mes'];
                continue;
            }

            if (empty($empleado->sueldo_base) || (float) $empleado->sueldo_base <= 0) {
                $omitidas++;
                $detalle[] = ['empleado' => $nombreCompleto, 'empleado_id' => $empleado->id, 'estado' => 'omitida', 'motivo' => 'Empleado sin sueldo_base configurado'];
                continue;
            }

            // Lo que le toca cobrar este mes: el sueldo entero si ya estaba, o
            // la parte proporcional si entró a mitad. Antes se le pagaba el mes
            // completo aunque hubiera entrado el día 28.
            $reparto = $this->repartoDeDiasDelMes($empleado, $mes, $anio);
            $sueldoDelMes = $this->sueldoDelMes($empleado, $mes, $anio);

            if ($sueldoDelMes === null) {
                $omitidas++;
                $detalle[] = [
                    'empleado' => $nombreCompleto, 'empleado_id' => $empleado->id, 'estado' => 'omitida',
                    'motivo'   => 'Todavía no había ingresado en ese mes (ingresó el ' . \Carbon\Carbon::parse($empleado->fecha_ingreso)->format('d/m/Y') . ')',
                ];
                continue;
            }

            $planilla = Planilla::create([
                'empleado_id'    => $empleado->id,
                'mes'            => $mes,
                'anio'           => $anio,
                'periodo_id'     => $periodo->id,
                'sueldo_base'    => $sueldoDelMes,
                'bonificaciones' => 0,
                'descuentos'     => 0,
                'total'          => 0,
            ]);

            $this->generarConceptosAutomaticos($planilla, $empleado);
            $planilla->recalcularTotal();

            $generadas++;
            $fila = ['empleado' => $nombreCompleto, 'empleado_id' => $empleado->id, 'estado' => 'generada', 'planilla_id' => $planilla->id];

            // Si se le prorrateó, se dice: un sueldo distinto al de su ficha
            // sin explicación parece un error de cálculo.
            if ($reparto['entro_este_mes']) {
                $fila['motivo'] = "Ingresó el " . \Carbon\Carbon::parse($empleado->fecha_ingreso)->format('d/m/Y') .
                    ": se le pagan {$reparto['dias_pagados']} de {$reparto['dias_del_mes']} días";
            }

            $detalle[] = $fila;
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'periodo' => $periodo->nombre,
                'mes'     => $mes,
                'anio'    => $anio,
                'resumen' => [
                    'generadas'  => $generadas,
                    'omitidas'   => $omitidas,
                    'evaluados'  => $empleados->count(),
                ],
                'detalle' => $detalle,
            ],
        ]);
    }
}
