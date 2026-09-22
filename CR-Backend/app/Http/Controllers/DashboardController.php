<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\Planilla;
use App\Models\Sede;
use App\Support\LibroExcel;
use App\Support\Meses;
use App\Traits\ExportaExcel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
    use ExportaExcel;

    public function index(Request $request)
    {
        ['mes' => $mes, 'anio' => $anio, 'sede' => $sede] = $this->filtroPedido($request);

        return response()->json([
            'success' => true,
            'data'    => $this->datos($mes, $anio, $sede),
        ]);
    }

    /**
     * El mes, el año y la sede que se están mirando.
     *
     * Lo piden los dos —la pantalla y el Excel— y tiene que salir del mismo
     * sitio: si el archivo leyera los filtros por su cuenta acabaría bajando
     * un mes distinto del que se ve en pantalla.
     */
    private function filtroPedido(Request $request): array
    {
        $request->validate([
            'mes'     => 'nullable|integer|min:1|max:12',
            'anio'    => 'nullable|integer|min:2000',
            'sede_id' => 'nullable|uuid|exists:sedes,id',
        ]);

        $hoy = Carbon::now();

        return [
            'mes'  => (int) ($request->input('mes')  ?: $hoy->month),
            'anio' => (int) ($request->input('anio') ?: $hoy->year),
            // Un colegio con dos locales necesita poder mirar uno solo: la
            // pregunta de RR.HH. es "cuánto cuesta Jerusalén", no el total.
            'sede' => $request->input('sede_id'),
        ];
    }

    /** Todas las cifras del panel para ese mes y esa sede. */
    private function datos(int $mes, int $anio, ?string $sede): array
    {
        $hoy = Carbon::now();

        // Las líneas de las planillas del mes, sumadas por concepto. Las
        // usan dos gráficos y se leen UNA vez.
        $conceptosDelMes = $this->conceptosDelMes($mes, $anio, $sede);

        // Las boletas del mes por estado de firma. Las usan el resumen, los
        // pendientes y el gráfico de firmas, que antes las contaban cada uno
        // por su lado: tres recorridos de lo mismo.
        $boletasDelMes = $this->boletasDelMes($mes, $anio, $sede);

        return [
            'periodo'            => ['mes' => $mes, 'anio' => $anio, 'sede_id' => $sede],
            'resumen'            => $this->resumen($mes, $anio, $hoy, $sede, $boletasDelMes),
            // Lo que RR.HH. tiene pendiente de hacer, no de mirar.
            'pendientes'         => $this->pendientes($mes, $anio, $hoy, $sede, $boletasDelMes),
            'cumpleanos'         => $this->cumpleanosDelMes($mes, $anio, $sede, $hoy),
            'remuneracionPorArea'=> $this->remuneracionPorArea($mes, $anio),
            'sistemaPensiones'   => $this->sistemaPensiones(),
            'tipoContrato'       => $this->tipoContrato(),
            'tendenciaNomina'    => $this->tendenciaNomina($anio),
            'firmaBoletas'       => $this->firmaBoletas($boletasDelMes),
            'contratosPorVencer' => $this->contratosPorVencer($hoy),
            // ── Los cuatro que faltaban ──
            // La composición y el top salen de la MISMA lectura: los dos
            // suman las líneas de las planillas del mes, y recorrerlas
            // dos veces costaba el doble para nada.
            'composicionNomina'  => $this->composicionNomina($mes, $anio, $sede, $conceptosDelMes),
            'personalPorSede'    => $this->personalPorSede($sede),
            'antiguedad'         => $this->antiguedad($sede, $hoy),
            'topConceptos'       => $this->topConceptos($conceptosDelMes),
        ];
    }

    /**
     * El panel entero en un Excel, con el filtro que se está mirando.
     *
     * Lo que se ve es lo que baja: el mismo mes y la misma sede. Sale en
     * cinco hojas porque el panel no es una tabla —son cifras agrupadas por
     * tema—, y de este modo cada bloque de la pantalla tiene su sitio: el
     * resumen con los pendientes, la plata, la plantilla, los contratos que
     * se acaban y los cumpleaños. Los montos van como número, así que en
     * Excel se pueden sumar sin tocarlos.
     */
    public function exportar(Request $request)
    {
        ['mes' => $mes, 'anio' => $anio, 'sede' => $sede] = $this->filtroPedido($request);

        $datos   = $this->datos($mes, $anio, $sede);
        $resumen = $datos['resumen'];
        $firma   = $datos['firmaBoletas'];

        $periodo     = Meses::nombre($mes) . ' ' . $anio;
        $nombreSede  = $sede ? (Sede::find($sede)->nombre ?? 'Sede') : 'Todas las sedes';

        // Etiqueta y monto / etiqueta y cuenta, tal como los manda el panel.
        $conMonto = fn (array $lista) => array_map(fn ($x) => [$x['etiqueta'], $x['valor'], true], $lista);
        $conCuenta = fn (array $lista) => array_map(fn ($x) => [$x['etiqueta'], $x['valor']], $lista);

        $libro = new LibroExcel();

        $this->hojaDeCifras($libro, 'Resumen', [
            'Lo que se está mirando' => [
                ['Periodo', $periodo],
                ['Sede', $nombreSede],
                ['Descargado el', $this->cuando()],
            ],
            'Las cifras del mes' => [
                ['Personal activo', $resumen['empleadosActivos']],
                ['Altas de este mes', $resumen['altasDelMes']],
                ['Nómina del mes (S/)', $resumen['nominaDelMes'], true],
                ['Planillas armadas', $resumen['planillasDelMes']],
                ['Boletas emitidas', $resumen['boletasEmitidas']],
                ['Contratos que vencen en 30 días', $resumen['contratosPorVencer']],
            ],
            'Firma de las boletas del mes' => [
                ['Firmadas', $firma['firmadas']],
                ['Vistas, pero sin firmar', $firma['vistas']],
                ['Sin abrir', $firma['pendientes']],
            ],
            'Pendientes' => array_map(
                fn (array $p) => [Str::ucfirst($p['texto']), $p['cuantos']],
                $datos['pendientes']
            ),
        ]);

        $this->hojaDeCifras($libro, 'Nómina', [
            'A dónde se va la nómina (S/)'      => $conMonto($datos['composicionNomina']),
            'Remuneración por área (S/)'        => $conMonto($datos['remuneracionPorArea']),
            'Los conceptos que más pesan (S/)'  => $conMonto($datos['topConceptos']),
            "Nómina mes a mes de {$anio} (S/)"  => $conMonto($datos['tendenciaNomina']),
        ]);

        $this->hojaDeCifras($libro, 'Plantilla', [
            'Personal por sede'          => $conCuenta($datos['personalPorSede']),
            'Sistema de pensiones'       => $conCuenta($datos['sistemaPensiones']),
            'Tipo de contrato'           => $conCuenta($datos['tipoContrato']),
            'Antigüedad en el colegio'   => $conCuenta($datos['antiguedad']),
        ]);

        $this->hojaDeReporte(
            $libro,
            'Contratos por vencer',
            ['Trabajador', 'Cargo', 'Vence el', 'Días que faltan'],
            array_map(fn (array $c) => [
                $c['nombre'],
                $c['cargo'],
                Carbon::parse($c['fecha'])->format('d/m/Y'),
                $c['dias'],
            ], $datos['contratosPorVencer']),
            // La fecha como texto: escrita así, Excel no la reinterpreta.
            [2 => LibroExcel::TEXTO]
        );

        $this->hojaDeReporte(
            $libro,
            'Cumpleaños',
            ['Día', 'Trabajador', 'Cargo', 'Área', 'Sede', 'Cumple'],
            array_map(fn (array $c) => [
                $c['fecha'],
                $c['nombre'],
                $c['cargo'],
                $c['area'],
                $c['sede'],
                $c['edad'] > 0 ? $c['edad'] . ' años' : '',
            ], $datos['cumpleanos']),
            [0 => LibroExcel::TEXTO]
        );

        return $libro->descargar($this->nombreExcelSeguro(
            'Panel de control ' . $periodo . ($sede ? ' - ' . $nombreSede : '')
        ));
    }

    /** Cuándo se bajó el archivo, para saber de qué día son las cifras. */
    private function cuando(): string
    {
        return Carbon::now()->format('d/m/Y H:i');
    }

    /**
     * Una hoja de "concepto y valor", con sus secciones.
     *
     * El panel no es una tabla: son cifras sueltas agrupadas por tema. Una
     * hoja por gráfico serían diez pestañas; así cada hoja lleva sus bloques
     * separados por un título, que es como se leen en pantalla.
     *
     * @param  array<string, array<int, array{0: string, 1: mixed, 2?: bool}>>  $bloques
     *         título de la sección => filas [concepto, valor, es monto]
     */
    private function hojaDeCifras(LibroExcel $libro, string $nombre, array $bloques): void
    {
        $filas = [];
        $estiloFilas = [];

        foreach ($bloques as $titulo => $lineas) {
            // Una línea en blanco entre secciones: se distinguen de un vistazo.
            if ($filas) {
                $filas[] = ['', ''];
            }

            $filas[] = [$titulo, ''];
            // Las claves van corridas una fila porque hojaDeReporte pone los
            // títulos de columna delante.
            $estiloFilas[count($filas)] = LibroExcel::TITULO_OPCIONAL;

            foreach ($lineas as $linea) {
                $filas[] = [$linea[0], $linea[1]];
                $estiloFilas[count($filas)] = [
                    LibroExcel::NORMAL,
                    ($linea[2] ?? false) ? LibroExcel::MONTO : LibroExcel::NORMAL,
                ];
            }
        }

        $this->hojaDeReporte($libro, $nombre, ['Concepto', 'Valor'], $filas, [], $estiloFilas);
    }

    /** Las cuatro cifras de arriba. */
    private function resumen(int $mes, int $anio, Carbon $hoy, ?string $sede, array $boletasDelMes): array
    {
        $activos = $this->empleadosActivos($sede)->count();

        $altasDelMes = $this->empleadosActivos($sede)
            ->whereYear('fecha_ingreso', $hoy->year)
            ->whereMonth('fecha_ingreso', $hoy->month)
            ->count();

        $planillasDelMes = $this->planillasDelMes($mes, $anio, $sede);

        $nomina = (float) (clone $planillasDelMes)->sum('total');
        $cuantasPlanillas = (clone $planillasDelMes)->count();

        // Boletas emitidas: documentos de tipo boleta atados a una planilla
        // de este mes. Se cuenta contra las planillas, no contra el total de
        // empleados: a quien no se le armó planilla no se le puede emitir.
        $boletas = $boletasDelMes['total'];

        $porVencer = $this->contratosVigentes($sede)
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
                // Nulo ya no es "no lo sé": es el que no aporta a ninguna pensión.
                DB::raw('COALESCE(sistema_pensiones, "No aporta") as etiqueta'),
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
    /**
     * Las boletas de las planillas del mes, contadas por estado de firma.
     *
     * Respeta la sede, como el resto del panel. El gráfico de firmas antes
     * la ignoraba: filtrabas por una sede y el gráfico seguía enseñando las
     * boletas de todo el colegio.
     */
    private function boletasDelMes(int $mes, int $anio, ?string $sede): array
    {
        $porEstado = Documento::where('tipo', 'boleta')
            ->whereIn('planilla_id', $this->planillasDelMes($mes, $anio, $sede)->select('id'))
            ->groupBy('estado_firma')
            ->pluck(DB::raw('COUNT(*)'), 'estado_firma');

        return [
            'firmado'   => (int) ($porEstado['firmado'] ?? 0),
            'visto'     => (int) ($porEstado['visto'] ?? 0),
            'pendiente' => (int) ($porEstado['pendiente'] ?? 0),
            'total'     => (int) $porEstado->sum(),
        ];
    }

    private function firmaBoletas(array $boletasDelMes): array
    {
        return [
            'firmadas'   => $boletasDelMes['firmado'],
            'vistas'     => $boletasDelMes['visto'],
            'pendientes' => $boletasDelMes['pendiente'],
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

    // ══ Ayudantes de filtro ═══════════════════════════════════════
    //
    // Los tres arrancan la consulta ya acotada a la sede cuando se pidió
    // una. Van aquí y no repetidos en cada bloque porque, con ocho consultas
    // distintas, basta olvidarse del filtro en una para que la pantalla
    // mezcle las cifras de los dos locales y nadie lo note.

    private function empleadosActivos(?string $sede)
    {
        // Con la tabla delante: sedes, areas y cargos también tienen `estado`,
        // y en cuanto una consulta hace join con ellas MySQL no sabe cuál es.
        $q = Empleado::where('empleados.estado', 'activo');
        return $sede ? $q->where('empleados.sede_id', $sede) : $q;
    }

    private function planillasDelMes(int $mes, int $anio, ?string $sede)
    {
        $q = Planilla::where('mes', $mes)->where('anio', $anio);
        return $sede
            ? $q->whereIn('empleado_id', Empleado::where('sede_id', $sede)->select('id'))
            : $q;
    }

    private function contratosVigentes(?string $sede)
    {
        $q = Contrato::where('estado', 'vigente')->where('estado_registro', 'activo');
        return $sede
            ? $q->whereIn('empleado_id', Empleado::where('sede_id', $sede)->select('id'))
            : $q;
    }

    /**
     * Lo que RR.HH. tiene PENDIENTE de hacer, no de mirar.
     *
     * El resto del panel cuenta cosas; esto señala trabajo. Cada línea es
     * algo que alguien está esperando —una vacación sin responder, una
     * boleta sin emitir— y lleva a dónde se resuelve.
     */
    private function pendientes(int $mes, int $anio, Carbon $hoy, ?string $sede, array $boletasDelMes): array
    {
        $vacacionesPorAprobar = DB::table('vacaciones')
            ->where('estado', 'pendiente')
            ->when($sede, fn ($q) => $q->whereIn(
                'empleado_id',
                Empleado::where('sede_id', $sede)->select('id')
            ))
            ->count();

        $planillas = $this->planillasDelMes($mes, $anio, $sede);

        // A quién le falta su boleta de este mes: tiene planilla armada pero
        // no se le emitió el documento.
        $sinBoleta = (clone $planillas)
            ->whereNotIn('id', Documento::where('tipo', 'boleta')->whereNotNull('planilla_id')->select('planilla_id'))
            ->count();

        $sinFirmar = $boletasDelMes['total'] - $boletasDelMes['firmado'];

        // Sin sueldo no se le puede armar planilla: la generación lo salta y
        // la persona se queda sin cobrar sin que nadie se dé cuenta.
        $sinSueldo = $this->empleadosActivos($sede)
            ->where(fn ($q) => $q->whereNull('sueldo_base')->orWhere('sueldo_base', '<=', 0))
            ->count();

        $contratosPorVencer = $this->contratosVigentes($sede)
            ->whereNotNull('fecha_fin')
            ->whereBetween('fecha_fin', [$hoy->toDateString(), $hoy->copy()->addDays(30)->toDateString()])
            ->count();

        return [
            ['clave' => 'vacaciones', 'cuantos' => $vacacionesPorAprobar,
             'texto' => 'solicitudes de vacaciones esperando respuesta', 'ruta' => '/inicio/vacaciones'],
            ['clave' => 'sin_boleta', 'cuantos' => $sinBoleta,
             'texto' => 'trabajadores con planilla pero sin boleta emitida', 'ruta' => '/inicio/planillas'],
            ['clave' => 'sin_firmar', 'cuantos' => $sinFirmar,
             'texto' => 'boletas del mes que el trabajador aún no firma', 'ruta' => '/inicio/documentos?filtro=boletas_por_firmar'],
            ['clave' => 'sin_sueldo', 'cuantos' => $sinSueldo,
             'texto' => 'trabajadores activos sin sueldo configurado', 'ruta' => '/inicio/empleados'],
            ['clave' => 'contratos', 'cuantos' => $contratosPorVencer,
             'texto' => 'contratos que vencen en los próximos 30 días', 'ruta' => '/inicio/contratos'],
        ];
    }

    /**
     * Quién cumple años este mes.
     *
     * No es una cifra de planilla, pero es de las cosas que RR.HH. de un
     * colegio mira todos los meses y hoy tenía que ir a buscar a mano en las
     * fichas una por una.
     */
    private function cumpleanosDelMes(int $mes, int $anio, ?string $sede, Carbon $hoy): array
    {
        // Sin tope: en un mes cumplen doce o quince personas y cortar la
        // lista en veinte solo dejaría a alguien fuera sin avisar.
        return $this->empleadosActivos($sede)
            ->whereNotNull('fecha_nacimiento')
            ->whereMonth('fecha_nacimiento', $mes)
            ->with(['cargo:id,nombre', 'area:id,nombre', 'sede:id,nombre'])
            ->orderByRaw('DAY(fecha_nacimiento)')
            ->get()
            ->map(function (Empleado $e) use ($mes, $anio, $hoy) {
                $nacimiento = Carbon::parse($e->fecha_nacimiento);
                $dia = (int) $nacimiento->day;

                return [
                    'nombre' => trim($e->nombre . ' ' . $e->apellido),
                    'cargo'  => $e->cargo->nombre ?? '—',
                    'area'   => $e->area->nombre ?? '—',
                    'sede'   => $e->sede->nombre ?? '—',
                    'dia'    => $dia,
                    'fecha'  => $nacimiento->format('d/m'),
                    // Los años que cumple en el periodo que se está mirando,
                    // no la edad de hoy: si se mira un mes que ya pasó, lo
                    // que interesa es lo que cumplió entonces.
                    'edad'   => $anio - (int) $nacimiento->year,
                    'es_hoy' => $hoy->year === $anio && $hoy->month === $mes && $hoy->day === $dia,
                    // Ya fue, es hoy, o está por venir: sirve para saber a
                    // quién todavía se le puede saludar.
                    'ya_paso' => $hoy->year > $anio
                        || ($hoy->year === $anio && ($hoy->month > $mes || ($hoy->month === $mes && $hoy->day > $dia))),
                ];
            })
            ->all();
    }

    /**
     * A dónde se va la plata este mes.
     *
     * Es LA pregunta de una planilla, y hasta ahora el panel no la
     * contestaba: enseñaba el neto y el reparto por área, pero no cuánto de
     * ese gasto es sueldo, cuánto se añade en bonificaciones, cuánto se
     * retiene y cuánto pone el colegio de su bolsillo.
     *
     * Las aportaciones van aparte a propósito: NO salen del sueldo del
     * trabajador, las paga el colegio encima del neto.
     */
    /**
     * Lo que suma cada concepto en el mes, de una sola lectura.
     *
     * Antes esto se consultaba SEIS veces: una por cada tipo para el gráfico
     * de composición y otra para el top de conceptos, y las seis recorrían
     * las mismas líneas —ocho mil con 150 trabajadores—. Como el catálogo
     * tiene veintidós conceptos, agrupando por nombre sale todo de un tirón
     * y el reparto por tipo se hace aquí, sobre veintidós filas.
     *
     * Medido sobre un mes de 150 trabajadores: de 6 consultas a 1.
     */
    private function conceptosDelMes(int $mes, int $anio, ?string $sede): Collection
    {
        $ids = $this->planillasDelMes($mes, $anio, $sede)->select('id');

        return DB::table('payroll_detalles as pd')
            ->join('payment_concepts as pc', 'pc.id', '=', 'pd.payment_concept_id')
            ->whereIn('pd.planilla_id', $ids)
            ->groupBy('pc.tipo', 'pc.nombre')
            ->select('pc.tipo', 'pc.nombre', DB::raw('SUM(pd.monto_calculado) as total'))
            ->get();
    }

    private function composicionNomina(int $mes, int $anio, ?string $sede, Collection $conceptos): array
    {
        $planillas = $this->planillasDelMes($mes, $anio, $sede);

        $porTipo = fn (string $tipo) => round(
            (float) $conceptos->where('tipo', $tipo)->sum('total'),
            2
        );

        $basico = (float) (clone $planillas)->sum('sueldo_base');

        return [
            ['etiqueta' => 'Sueldo básico',  'valor' => round($basico, 2)],
            ['etiqueta' => 'Bonificaciones', 'valor' => $porTipo('bonificacion')],
            ['etiqueta' => 'Descuentos',     'valor' => $porTipo('descuento')],
            ['etiqueta' => 'Adelantos',      'valor' => $porTipo('adelanto')],
            ['etiqueta' => 'Aporta el colegio', 'valor' => $porTipo('aportacion')],
        ];
    }

    /** Cuánta gente hay en cada local. */
    private function personalPorSede(?string $sede): array
    {
        return $this->empleadosActivos($sede)
            ->select('sedes.nombre', DB::raw('COUNT(*) as total'))
            ->leftJoin('sedes', 'sedes.id', '=', 'empleados.sede_id')
            ->groupBy('sedes.nombre')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($f) => [
                'etiqueta' => $f->nombre ?? 'Sin sede',
                'valor'    => (int) $f->total,
            ])
            ->all();
    }

    /**
     * Cuánto lleva cada quien en el colegio.
     *
     * Sirve para dos cosas que RR.HH. mira: quién está por cumplir años de
     * servicio (CTS, gratificación completa) y si la plantilla es estable o
     * rota mucho.
     */
    private function antiguedad(?string $sede, Carbon $hoy): array
    {
        $tramos = [
            'Menos de 1 año' => [0, 1],
            'De 1 a 3 años'  => [1, 3],
            'De 3 a 5 años'  => [3, 5],
            'De 5 a 10 años' => [5, 10],
            'Más de 10 años' => [10, 200],
        ];

        $fechas = $this->empleadosActivos($sede)
            ->whereNotNull('fecha_ingreso')
            ->pluck('fecha_ingreso');

        $conteo = array_fill_keys(array_keys($tramos), 0);

        foreach ($fechas as $fecha) {
            $anios = Carbon::parse($fecha)->diffInYears($hoy);
            foreach ($tramos as $etiqueta => [$desde, $hasta]) {
                if ($anios >= $desde && $anios < $hasta) {
                    $conteo[$etiqueta]++;
                    break;
                }
            }
        }

        return collect($conteo)
            ->map(fn ($valor, $etiqueta) => ['etiqueta' => $etiqueta, 'valor' => $valor])
            ->values()
            ->all();
    }

    /**
     * Los conceptos que más pesan este mes, sin contar los de ley.
     *
     * La pensión y EsSalud siempre van a estar arriba porque le tocan a
     * todo el mundo; lo que dice algo es qué OTRA cosa está costando: los
     * adelantos, el comedor, una bonificación que se repartió.
     */
    private function topConceptos(Collection $conceptos): array
    {
        // Los de cálculo especial (pensión, EsSalud, Renta 5ta) se dejan
        // fuera: siempre serían los seis primeros y no dicen nada — ya
        // tienen su propio gráfico.
        return $conceptos
            ->whereNotIn('nombre', \App\Support\ConceptosDePago::CALCULO_ESPECIAL)
            ->sortByDesc('total')
            ->take(6)
            ->map(fn ($f) => [
                'etiqueta' => $f->nombre,
                'valor'    => round((float) $f->total, 2),
            ])
            ->values()
            ->all();
    }
}
