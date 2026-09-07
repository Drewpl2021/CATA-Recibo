<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\Planilla;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Las cifras del Panel de Control.
 *
 * Todo sale de la base: hasta ahora la pantalla traía los números escritos
 * a mano en el componente (127 empleados, S/ 148,300 de nómina), así que
 * enseñaba lo mismo aunque el colegio no tuviera ni un trabajador dado de
 * alta.
 *
 * Va todo en UNA sola respuesta a propósito: son ocho consultas de agregado
 * sobre tablas que ya tienen sus índices, y hacerlas en ocho peticiones
 * distintas solo añadiría latencia y parpadeo a la pantalla.
 *
 * Dos gráficos cambiaron de tema porque el dato que pedían no existe:
 * "distribución por sexo" (empleados no guarda el sexo) y "por nivel
 * educativo" (nivel_estudios es el grado académico del trabajador, no el
 * nivel donde enseña). En su sitio van el sistema de pensiones y el estado
 * de firma de las boletas del mes, que sí son datos reales y le sirven a
 * RR.HH.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'mes'  => 'nullable|integer|min:1|max:12',
            'anio' => 'nullable|integer|min:2000',
        ]);

        $hoy  = Carbon::now();
        $mes  = (int) ($request->input('mes')  ?: $hoy->month);
        $anio = (int) ($request->input('anio') ?: $hoy->year);

        return response()->json([
            'success' => true,
            'data'    => [
                'periodo'            => ['mes' => $mes, 'anio' => $anio],
                'resumen'            => $this->resumen($mes, $anio, $hoy),
                'remuneracionPorArea'=> $this->remuneracionPorArea($mes, $anio),
                'sistemaPensiones'   => $this->sistemaPensiones(),
                'tipoContrato'       => $this->tipoContrato(),
                'tendenciaNomina'    => $this->tendenciaNomina($anio),
                'firmaBoletas'       => $this->firmaBoletas($mes, $anio),
                'contratosPorVencer' => $this->contratosPorVencer($hoy),
            ],
        ]);
    }

    /** Las cuatro cifras de arriba. */
    private function resumen(int $mes, int $anio, Carbon $hoy): array
    {
        $activos = Empleado::where('estado', 'activo')->count();

        $altasDelMes = Empleado::where('estado', 'activo')
            ->whereYear('fecha_ingreso', $hoy->year)
            ->whereMonth('fecha_ingreso', $hoy->month)
            ->count();

        $planillasDelMes = Planilla::where('mes', $mes)->where('anio', $anio);

        $nomina = (float) (clone $planillasDelMes)->sum('total');
        $cuantasPlanillas = (clone $planillasDelMes)->count();

        // Boletas emitidas: documentos de tipo boleta atados a una planilla
        // de este mes. Se cuenta contra las planillas, no contra el total de
        // empleados: a quien no se le armó planilla no se le puede emitir.
        $boletas = Documento::where('tipo', 'boleta')
            ->whereIn('planilla_id', (clone $planillasDelMes)->select('id'))
            ->count();

        $porVencer = Contrato::where('estado', 'vigente')
            ->where('estado_registro', 'activo')
            ->whereNotNull('fecha_fin')
            ->whereBetween('fecha_fin', [$hoy->toDateString(), $hoy->copy()->addDays(30)->toDateString()])
            ->count();

        return [
            'empleadosActivos' => $activos,
            'altasDelMes'      => $altasDelMes,
            'nominaDelMes'     => round($nomina, 2),
            'planillasDelMes'  => $cuantasPlanillas,
            'boletasEmitidas'  => $boletas,
            'contratosPorVencer' => $porVencer,
        ];
    }

    /**
     * Cuánto se paga en cada área este mes.
     *
     * Sale de las planillas del mes, no del sueldo de la ficha: lo que
     * importa es lo que se pagó de verdad, con sus bonos y descuentos.
     */
    private function remuneracionPorArea(int $mes, int $anio): array
    {
        $filas = Planilla::query()
            ->join('empleados', 'empleados.id', '=', 'planilla.empleado_id')
            ->leftJoin('areas', 'areas.id', '=', 'empleados.area_id')
            ->where('planilla.mes', $mes)
            ->where('planilla.anio', $anio)
            ->groupBy('areas.id', 'areas.nombre')
            ->orderByDesc(DB::raw('SUM(planilla.total)'))
            ->get([
                DB::raw('COALESCE(areas.nombre, "Sin área") as etiqueta'),
                DB::raw('SUM(planilla.total) as valor'),
            ]);

        return $filas->map(fn ($f) => [
            'etiqueta' => $f->etiqueta,
            'valor'    => round((float) $f->valor, 2),
        ])->all();
    }

    /** Cuántos están en ONP y cuántos en AFP. */
    private function sistemaPensiones(): array
    {
        $filas = Empleado::query()
            ->where('estado', 'activo')
            ->groupBy('sistema_pensiones')
            ->get([
                DB::raw('COALESCE(sistema_pensiones, "Sin definir") as etiqueta'),
                DB::raw('COUNT(*) as valor'),
            ]);

        return $filas->map(fn ($f) => [
            'etiqueta' => $f->etiqueta,
            'valor'    => (int) $f->valor,
        ])->all();
    }

    /** Con qué tipo de contrato está cada quien. */
    private function tipoContrato(): array
    {
        $nombres = [
            'indeterminado' => 'Indeterminado',
            'plazo_fijo'    => 'Plazo fijo',
            'suplencia'     => 'Suplencia',
            'practicas'     => 'Prácticas',
        ];

        $filas = Contrato::query()
            ->where('estado', 'vigente')
            ->where('estado_registro', 'activo')
            ->groupBy('tipo_contrato')
            ->get(['tipo_contrato', DB::raw('COUNT(*) as valor')]);

        return $filas->map(fn ($f) => [
            'etiqueta' => $nombres[$f->tipo_contrato] ?? $f->tipo_contrato,
            'valor'    => (int) $f->valor,
        ])->all();
    }

    /**
     * Cuánto se pagó cada mes del año.
     *
     * Devuelve los doce meses aunque no haya planilla: un hueco en el medio
     * de la línea se lee como un error, y un cero se lee como lo que es.
     */
    private function tendenciaNomina(int $anio): array
    {
        $porMes = Planilla::where('anio', $anio)
            ->groupBy('mes')
            ->pluck(DB::raw('SUM(total)'), 'mes');

        $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'];
        $salida = [];

        foreach ($meses as $i => $etiqueta) {
            $salida[] = [
                'etiqueta' => $etiqueta,
                'valor'    => round((float) ($porMes[$i + 1] ?? 0), 2),
            ];
        }

        return $salida;
    }

    /** En qué va la firma de las boletas de este mes. */
    private function firmaBoletas(int $mes, int $anio): array
    {
        $planillas = Planilla::where('mes', $mes)->where('anio', $anio)->select('id');

        $porEstado = Documento::where('tipo', 'boleta')
            ->whereIn('planilla_id', $planillas)
            ->groupBy('estado_firma')
            ->pluck(DB::raw('COUNT(*)'), 'estado_firma');

        return [
            'firmadas'   => (int) ($porEstado['firmado'] ?? 0),
            'vistas'     => (int) ($porEstado['visto'] ?? 0),
            'pendientes' => (int) ($porEstado['pendiente'] ?? 0),
        ];
    }

    /** Los contratos que se acaban pronto, con quién y cuándo. */
    private function contratosPorVencer(Carbon $hoy): array
    {
        $contratos = Contrato::with('empleado.cargo')
            ->where('estado', 'vigente')
            ->where('estado_registro', 'activo')
            ->whereNotNull('fecha_fin')
            ->whereBetween('fecha_fin', [$hoy->toDateString(), $hoy->copy()->addDays(60)->toDateString()])
            ->orderBy('fecha_fin')
            ->limit(6)
            ->get();

        return $contratos->map(function (Contrato $c) use ($hoy) {
            $dias = $hoy->diffInDays(Carbon::parse($c->fecha_fin), false);

            return [
                'nombre'  => trim(($c->empleado->nombre ?? '') . ' ' . ($c->empleado->apellido ?? '')),
                'cargo'   => $c->empleado->cargo->nombre ?? '—',
                'fecha'   => $c->fecha_fin,
                'dias'    => (int) $dias,
                // Cuánto corre prisa, para pintarlo de un color u otro.
                'urgencia' => $dias <= 15 ? 'urgente' : ($dias <= 30 ? 'proximo' : 'normal'),
            ];
        })->all();
    }
}
