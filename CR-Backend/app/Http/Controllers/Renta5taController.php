<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Planilla;
use App\Models\RentaQuintaPrevia;
use App\Support\LibroExcel;
use App\Support\Meses;
use App\Support\Progreso;
use App\Support\Renta5ta\MotorRenta5ta;
use App\Support\Renta5ta\RecalculoRenta5ta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * El módulo «Renta de 5ta»: cuánto le toca retener a cada trabajador en el
 * año, con el procedimiento de SUNAT, y de dónde sale cada número.
 *
 *   GET  /income-tax?anio=&mes=                  la lista del personal, con el detalle de ese mes
 *   GET  /income-tax/{empleado}?anio=            su hoja de retención, mes por mes
 *   PUT  /income-tax/{empleado}/history          un mes pagado antes del sistema
 *   GET  /income-tax/history/template?anio=      el Excel para cargar el historial
 *   POST /income-tax/history                     subir ese Excel
 *   POST /income-tax/recalculate                 rehacer la 5ta de las planillas abiertas
 *   GET  /income-tax/export?anio=&mes=&filtros   la lista filtrada y la hoja de cada uno, en Excel
 */
class Renta5taController extends Controller
{
    private const ABREV = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];

    /** El personal del año: los activos y quien tenga planilla o historial ese año. */
    private function personalDelAnio(int $anio)
    {
        return Empleado::with('cargo:id,nombre', 'area:id,nombre', 'sede:id,nombre')
            ->where(fn ($q) => $q->where('estado', 'activo')
                ->orWhereIn('id', Planilla::where('anio', $anio)->select('empleado_id'))
                ->orWhereIn('id', RentaQuintaPrevia::where('anio', $anio)->select('empleado_id')))
            ->orderBy('apellido')->orderBy('nombre')->get();
    }

    private function anio(Request $request): int
    {
        return (int) ($request->validate(['anio' => 'nullable|integer|min:2000|max:2100'])['anio'] ?? now()->year);
    }

    /**
     * La lista del personal para un año y un mes: el impuesto del año de cada
     * uno y, de ese mes, cuánto le correspondía retener y cuánto se le
     * retuvo de verdad. Trae también en qué meses lo retenido no fue lo que
     * correspondía y cuáles no tienen dato, para los filtros de la pantalla.
     *
     * Llega entera: la pantalla filtra sobre lo que ya tiene, al instante. El
     * Excel (exportar) aplica esos mismos filtros aquí, con filtrar().
     */
    public function index(Request $request)
    {
        $anio = $this->anio($request);
        $mes = $this->mes($request, $anio);
        ['filas' => $filas] = $this->calcularLista($anio, $mes);

        $delMes = $filas->filter(fn ($f) => $f['trabaja_mes']);

        return response()->json(['success' => true, 'data' => [
            'anio'      => $anio,
            'mes'       => $mes,
            'uit'       => (float) \App\Models\ValorLegal::delAnio($anio)->uit,
            'filas'     => $filas,
            'resumen'   => [
                'trabajadores'     => $filas->count(),
                'pagan_5ta'        => $filas->where('paga_5ta', true)->count(),
                'con_diferencias'  => $filas->filter(fn ($f) => count($f['meses_con_diferencia']) > 0)->count(),
                'sin_historial'    => $filas->filter(fn ($f) => count($f['meses_sin_dato']) > 0)->count(),
                'impuesto_anual'   => round($filas->sum('impuesto_anual'), 2),
                'retenido'         => round($filas->sum('retenido'), 2),
                // Del mes elegido: lo que correspondía a todos y lo retenido de verdad.
                'corresponde_mes'  => round($delMes->sum('corresponde_mes'), 2),
                'retenido_mes'     => $delMes->contains(fn ($f) => $f['retenido_mes'] !== null)
                    ? round($delMes->sum(fn ($f) => $f['retenido_mes'] ?? 0), 2) : null,
            ],
        ]]);
    }

    /** El mes pedido; sin él, el mes en curso (o diciembre en un año que ya pasó). */
    private function mes(Request $request, int $anio): int
    {
        $hoy = now();
        $porDefecto = $anio === (int) $hoy->year ? (int) $hoy->month : ($anio < (int) $hoy->year ? 12 : 1);

        return (int) ($request->validate(['mes' => 'nullable|integer|min:1|max:12'])['mes'] ?? $porDefecto);
    }

    /**
     * Una fila por trabajador del año, con lo del mes elegido, y su hoja
     * completa (el Excel la necesita y así no se calcula dos veces).
     *
     * @return array{filas: \Illuminate\Support\Collection, hojas: array<string, array>, empleados: array<string, Empleado>}
     */
    private function calcularLista(int $anio, int $mes, ?Progreso $progreso = null): array
    {
        $motor = new MotorRenta5ta();
        $hojas = [];
        $empleados = [];

        $filas = $this->personalDelAnio($anio)->map(function (Empleado $e) use ($motor, $anio, $mes, $progreso, &$hojas, &$empleados) {
            $progreso?->avanzar();
            $hoja = $motor->hoja($e, $anio);
            $hojas[$e->id] = $hoja;
            $empleados[$e->id] = $e;
            $delMes = $hoja['meses'][$mes - 1];
            $real = $delMes['trabaja'] ? $delMes['retencion_real'] : null;
            $diferencias = $this->mesesConDiferencia($hoja['meses']);
            $paga = $hoja['resumen']['impuesto_anual'] > 0 || $hoja['resumen']['retenido'] > 0;

            return [
                'id'                    => $e->id,
                'dni'                   => $e->dni,
                'nombre'                => trim("{$e->apellido} {$e->nombre}"),
                'cargo_id'              => $e->cargo_id,
                'cargo'                 => $e->cargo?->nombre,
                'area_id'               => $e->area_id,
                'area'                  => $e->area?->nombre,
                'sede_id'               => $e->sede_id,
                'sede'                  => $e->sede?->nombre,
                'estado'                => $e->estado,
                'impuesto_anual'        => $hoja['resumen']['impuesto_anual'],
                'retenido'              => $hoja['resumen']['retenido'],
                'por_retener'           => $hoja['resumen']['por_retener'],
                // El mes elegido.
                'trabaja_mes'           => $delMes['trabaja'],
                'fuente_mes'            => $delMes['fuente'],
                'remuneracion_mes'      => $delMes['trabaja'] ? $delMes['remuneracion_mes'] : 0,
                'corresponde_mes'       => $delMes['trabaja'] ? $delMes['retencion'] : 0,
                'retenido_mes'          => $real,
                'diferencia_mes'        => $real === null ? null : round($real - $delMes['retencion'], 2),
                // Para los filtros.
                'paga_5ta'              => $paga,
                'meses_con_diferencia'  => $diferencias,
                'meses_sin_dato'        => $hoja['resumen']['meses_sin_dato'],
                'situacion'             => match (true) {
                    count($diferencias) > 0                       => 'diferencias',
                    count($hoja['resumen']['meses_sin_dato']) > 0 => 'sin_historial',
                    ! $paga                                       => 'no_paga',
                    default                                       => 'al_dia',
                },
            ];
        })->values();

        return ['filas' => $filas, 'hojas' => $hojas, 'empleados' => $empleados];
    }

    /** Lo que cada filtro de la pantalla deja pasar. Mismas claves que el embudo. */
    private const FILTROS_SITUACION = ['pagan', 'no_pagan', 'diferencias', 'sin_historial', 'al_dia'];

    /**
     * Los filtros del embudo y el buscador, igual que los aplica la pantalla
     * (renta5ta-list.component.ts → aplicarFiltro): lo que se ve es lo que baja.
     */
    private function filtrar(\Illuminate\Support\Collection $filas, array $f): \Illuminate\Support\Collection
    {
        $busqueda = mb_strtolower(trim((string) ($f['search'] ?? '')));

        return $filas->filter(function ($fila) use ($f, $busqueda) {
            $ok = match ($f['situacion'] ?? null) {
                'pagan'         => $fila['paga_5ta'],
                'no_pagan'      => ! $fila['paga_5ta'],
                'diferencias'   => count($fila['meses_con_diferencia']) > 0,
                'sin_historial' => count($fila['meses_sin_dato']) > 0,
                'al_dia'        => $fila['situacion'] === 'al_dia',
                default         => true,
            };

            return $ok
                && (empty($f['dato_mes']) || $fila['fuente_mes'] === $f['dato_mes'])
                && (empty($f['sede_id']) || (string) $fila['sede_id'] === (string) $f['sede_id'])
                && (empty($f['area_id']) || (string) $fila['area_id'] === (string) $f['area_id'])
                && (empty($f['cargo_id']) || (string) $fila['cargo_id'] === (string) $f['cargo_id'])
                && (empty($f['estado']) || $fila['estado'] === $f['estado'])
                && ($busqueda === '' || collect([$fila['dni'], $fila['nombre'], $fila['cargo']])
                    ->contains(fn ($v) => str_contains(mb_strtolower((string) $v), $busqueda)));
        })->values();
    }

    /** Los meses con dato (planilla o historial) en que lo retenido no fue lo que correspondía. */
    private function mesesConDiferencia(array $meses): array
    {
        return array_values(array_map(fn ($m) => $m['mes'], array_filter($meses, fn ($m) => $m['trabaja']
            && $m['retencion_real'] !== null
            && abs($m['retencion_real'] - $m['retencion']) > 0.01)));
    }

    public function show(Request $request, string $empleado)
    {
        $anio = $this->anio($request);
        $e = Empleado::with('cargo:id,nombre', 'area:id,nombre')->findOrFail($empleado);

        return response()->json(['success' => true, 'data' => [
            'empleado' => [
                'id' => $e->id, 'dni' => $e->dni, 'nombre' => trim("{$e->nombre} {$e->apellido}"),
                'cargo' => $e->cargo?->nombre, 'area' => $e->area?->nombre, 'estado' => $e->estado,
                'sueldo_base' => (float) $e->sueldo_base, 'fecha_ingreso' => $e->fecha_ingreso,
            ],
        ] + (new MotorRenta5ta())->hoja($e, $anio)]);
    }

    /**
     * PUT /income-tax/{empleado}/history — lo que cobró y le retuvieron un mes
     * que se pagó antes del sistema. Vacíos los dos: se borra ese mes.
     */
    public function guardarHistorial(Request $request, string $empleado)
    {
        $datos = $request->validate([
            'anio'         => 'required|integer|min:2000|max:2100',
            'mes'          => 'required|integer|min:1|max:12',
            'remuneracion' => 'nullable|numeric|min:0|max:9999999',
            'retencion'    => 'nullable|numeric|min:0|max:9999999',
        ]);
        $e = Empleado::findOrFail($empleado);
        $anio = (int) $datos['anio'];
        $mes = (int) $datos['mes'];

        if (Planilla::where('empleado_id', $e->id)->where('anio', $anio)->where('mes', $mes)->where('estado_registro', 'activo')->exists()) {
            return response()->json([
                'success' => false,
                'message' => Meses::nombre($mes) . " {$anio} ya tiene planilla en el sistema: ese mes sale de ahí, no del historial.",
            ], 422);
        }

        $clave = ['empleado_id' => $e->id, 'anio' => $anio, 'mes' => $mes];
        if ($datos['remuneracion'] === null && $datos['retencion'] === null) {
            RentaQuintaPrevia::where($clave)->delete();
        } else {
            RentaQuintaPrevia::updateOrCreate($clave, [
                'remuneracion' => (float) ($datos['remuneracion'] ?? 0),
                'retencion'    => (float) ($datos['retencion'] ?? 0),
                'origen'       => 'Escrito en Renta de 5ta por ' . ($request->user()?->name ?? 'RR.HH.'),
            ]);
        }

        // Lo de los meses siguientes cambia: se recalculan las planillas abiertas.
        $recalculo = (new RecalculoRenta5ta())->empleado($e, $anio, $mes + 1);

        return response()->json(['success' => true, 'data' => [
            'recalculadas' => $recalculo['recalculadas'],
        ] + (new MotorRenta5ta())->hoja($e, $anio)]);
    }

    /** GET /income-tax/history/template — el historial del año para llenar en Excel. */
    public function modeloHistorial(Request $request)
    {
        $anio = $this->anio($request);
        $titulos = ['N°', 'DNI', 'Apellidos y Nombres'];
        foreach (self::ABREV as $abrev) {
            $titulos[] = "{$abrev} - Remuneración";
            $titulos[] = "{$abrev} - Retención 5ta";
        }

        $filas = [$titulos];
        $previos = RentaQuintaPrevia::where('anio', $anio)->get()->groupBy('empleado_id');
        $conPlanilla = Planilla::where('anio', $anio)->where('estado_registro', 'activo')->get(['empleado_id', 'mes'])
            ->groupBy('empleado_id')->map(fn ($g) => $g->pluck('mes')->map(fn ($m) => (int) $m)->all());

        foreach ($this->personalDelAnio($anio)->values() as $i => $e) {
            $fila = [$i + 1, (string) $e->dni, trim("{$e->apellido}, {$e->nombre}", ', ')];
            $suyos = ($previos[$e->id] ?? collect())->keyBy('mes');
            foreach (array_keys(self::ABREV) as $m) {
                // Los meses con planilla en el sistema no se llenan: salen de ahí.
                if (in_array($m, $conPlanilla[$e->id] ?? [], true)) {
                    $fila[] = 'En el sistema';
                    $fila[] = 'En el sistema';
                    continue;
                }
                $fila[] = isset($suyos[$m]) ? (float) $suyos[$m]->remuneracion : null;
                $fila[] = isset($suyos[$m]) ? (float) $suyos[$m]->retencion : null;
            }
            $filas[] = $fila;
        }

        $estilos = [LibroExcel::TITULO, LibroExcel::TITULO, LibroExcel::TITULO];
        $anchos = [6, 12, 34];
        $columnas = [1 => LibroExcel::TEXTO];
        for ($c = 3; $c < count($titulos); $c++) {
            $estilos[$c] = $c % 2 ? LibroExcel::TITULO : LibroExcel::TITULO_DESCUENTO;
            $anchos[$c] = 13;
            $columnas[$c] = LibroExcel::MONTO;
        }

        $libro = new LibroExcel();
        $libro->hoja('Historial 5ta', $filas, [
            'anchos' => $anchos, 'estiloColumnas' => $columnas, 'estiloFilas' => [0 => $estilos],
            'altoFilas' => [0 => 42], 'congelarPrimeraFila' => true,
        ]);
        $libro->hoja('Instrucciones', [
            ["Historial de Renta de 5ta {$anio}"],
            [''],
            ['Para qué sirve: la 5ta de cada mes resta lo que ya se le retuvo en el año y suma lo que ya cobró. Los meses que se pagaron antes del sistema hay que cargarlos aquí.'],
            ['Remuneración: todo lo que cobró ese mes y paga 5ta (sueldo, asignación familiar, bonificaciones, gratificación con su 9%…). Sin la movilidad.'],
            ['Retención 5ta: lo que se le descontó de 5ta ese mes, tal como salió en su boleta o en la PLAME.'],
            ['«En el sistema»: ese mes ya tiene planilla en el sistema y sale de ahí. No hace falta llenarlo.'],
            ['Vacía: no cambia lo que ya estaba. Para borrar un mes, escribe 0 en las dos columnas.'],
        ], ['anchos' => [0 => 110], 'estiloFilas' => [0 => LibroExcel::ENCABEZADO], 'estiloColumnas' => [0 => LibroExcel::PARRAFO]]);

        return $libro->descargar("Historial 5ta {$anio}.xlsx");
    }

    /** POST /income-tax/history — el Excel del historial, lleno. */
    public function cargarHistorial(Request $request)
    {
        $datos = $request->validate([
            'anio'    => 'required|integer|min:2000|max:2100',
            'archivo' => 'required|file|mimes:xlsx,xls|max:10240',
        ], ['archivo.mimes' => 'Sube el Excel del historial (.xlsx).']);
        $anio = (int) $datos['anio'];

        $hoja = IOFactory::load($request->file('archivo')->getRealPath())->getSheet(0)->toArray(null, true, false, false);
        $titulos = array_map(fn ($t) => mb_strtolower(trim((string) $t)), array_shift($hoja) ?? []);

        $colDni = array_search('dni', $titulos, true);
        $columnas = [];   // [mes => [remuneración, retención]]
        foreach (self::ABREV as $m => $abrev) {
            $rem = array_search(mb_strtolower("{$abrev} - Remuneración"), $titulos, true);
            $ret = array_search(mb_strtolower("{$abrev} - Retención 5ta"), $titulos, true);
            if ($rem !== false && $ret !== false) {
                $columnas[$m] = [$rem, $ret];
            }
        }
        if ($colDni === false || ! $columnas) {
            return response()->json(['success' => false, 'message' => 'No es el Excel del historial: descarga el modelo desde Renta de 5ta y llénalo.'], 422);
        }

        $porDni = Empleado::all(['id', 'dni', 'nombre', 'apellido'])->keyBy(fn ($e) => ltrim((string) $e->dni, '0'));
        $conPlanilla = Planilla::where('anio', $anio)->where('estado_registro', 'activo')->get(['empleado_id', 'mes'])
            ->map(fn ($p) => $p->empleado_id . '-' . (int) $p->mes)->flip();

        $numero = fn ($v) => is_numeric($v) ? round((float) $v, 2) : null;
        $guardados = 0;
        $saltados = 0;
        $noEncontrados = [];
        $afectados = [];

        $progreso = Progreso::actual()->etapa('Leyendo el historial de cada trabajador', count($hoja));
        DB::transaction(function () use ($hoja, $colDni, $columnas, $porDni, $conPlanilla, $numero, $anio, $progreso, &$guardados, &$saltados, &$noEncontrados, &$afectados) {
            foreach ($hoja as $fila) {
                $progreso->avanzar();
                $dni = ltrim(trim((string) ($fila[$colDni] ?? '')), '0');
                if ($dni === '') {
                    continue;
                }
                $e = $porDni[$dni] ?? null;
                if (! $e) {
                    $noEncontrados[] = $dni;
                    continue;
                }
                foreach ($columnas as $m => [$cRem, $cRet]) {
                    $rem = $numero($fila[$cRem] ?? null);
                    $ret = $numero($fila[$cRet] ?? null);
                    if ($rem === null && $ret === null) {
                        continue;   // vacía: no cambia nada
                    }
                    if (isset($conPlanilla[$e->id . '-' . $m])) {
                        $saltados++;
                        continue;
                    }
                    $clave = ['empleado_id' => $e->id, 'anio' => $anio, 'mes' => $m];
                    if (($rem ?? 0) == 0 && ($ret ?? 0) == 0) {
                        RentaQuintaPrevia::where($clave)->delete();
                    } else {
                        RentaQuintaPrevia::updateOrCreate($clave, ['remuneracion' => $rem ?? 0, 'retencion' => $ret ?? 0, 'origen' => 'Excel de historial']);
                    }
                    $guardados++;
                    $afectados[$e->id] = $e;
                }
            }
        });

        // Lo cargado cambia la 5ta de las planillas que ya existen.
        $recalculadas = 0;
        $progreso->etapa('Recalculando la 5ta de sus planillas', count($afectados));
        foreach ($afectados as $e) {
            $progreso->avanzar();
            $recalculadas += (new RecalculoRenta5ta())->empleado(Empleado::find($e->id), $anio)['recalculadas'];
        }

        return response()->json(['success' => true, 'data' => [
            'meses_guardados' => $guardados,
            'trabajadores'    => count($afectados),
            'meses_en_sistema' => $saltados,
            'no_encontrados'  => array_values(array_unique($noEncontrados)),
            'recalculadas'    => $recalculadas,
        ]]);
    }

    /** POST /income-tax/recalculate — la 5ta de todas las planillas abiertas del año. */
    public function recalcular(Request $request)
    {
        $anio = (int) $request->validate(['anio' => 'required|integer|min:2000|max:2100'])['anio'];
        $empleados = Empleado::whereIn('id', Planilla::where('anio', $anio)->select('empleado_id'))->get();

        $total = ['recalculadas' => 0, 'saltadas' => 0];
        $progreso = Progreso::actual()->etapa('Recalculando la 5ta de cada trabajador', $empleados->count());
        foreach ($empleados as $e) {
            $progreso->avanzar();
            $r = (new RecalculoRenta5ta())->empleado($e, $anio);
            $total['recalculadas'] += $r['recalculadas'];
            $total['saltadas'] += $r['saltadas'];
        }

        return response()->json(['success' => true, 'data' => $total + ['trabajadores' => $empleados->count()]]);
    }

    /**
     * GET /income-tax/export — el resumen y la hoja de retención de cada
     * trabajador, una debajo de otra, como la hoja «RETENCION» de la PLAME.
     */
    /**
     * El Excel de la pantalla: lo que se está viendo, con los mismos filtros
     * y la misma búsqueda.
     *
     *   Hoja «Lista»:     una fila por trabajador, como la tabla, con totales.
     *   Hoja «Retención»: la hoja de retención de cada uno de esos, mes por mes.
     */
    public function exportar(Request $request)
    {
        $anio = $this->anio($request);
        $mes = $this->mes($request, $anio);
        $f = $request->validate([
            'situacion' => 'nullable|in:' . implode(',', self::FILTROS_SITUACION),
            'dato_mes'  => 'nullable|in:planilla,historial,proyectado,no_trabaja',
            'sede_id'   => 'nullable|string|max:64',
            'area_id'   => 'nullable|string|max:64',
            'cargo_id'  => 'nullable|string|max:64',
            'estado'    => 'nullable|in:activo,inactivo',
            'search'    => 'nullable|string|max:100',
        ]);

        $progreso = Progreso::actual()->etapa('Calculando la 5ta de cada trabajador', $this->personalDelAnio($anio)->count());
        ['filas' => $todas, 'hojas' => $hojas, 'empleados' => $empleados] = $this->calcularLista($anio, $mes, $progreso);
        $filas = $this->filtrar($todas, $f);
        $nombreMes = Meses::nombre($mes);

        // ── La lista ──
        $situaciones = ['diferencias' => 'Difiere', 'sin_historial' => 'Faltan meses', 'no_paga' => 'No paga 5ta', 'al_dia' => 'Al día'];
        $fuentes = ['planilla' => 'Su planilla', 'historial' => 'Historial', 'proyectado' => 'Estimado', 'no_trabaja' => 'No trabajó'];
        $meses = fn (array $m) => implode(', ', array_map(fn ($x) => self::ABREV[$x], $m));

        $lista = [
            ["Renta de 5ta · {$nombreMes} {$anio}"],
            [$this->describirFiltros($f, $todas) . ' · ' . $filas->count() . ' de ' . $todas->count() . ' trabajador(es)'],
            [''],
            ['N°', 'DNI', 'Apellidos y nombres', 'Cargo', 'Área', 'Sede', 'Estado',
                "Impuesto {$anio}", 'Retenido en el año', 'Por retener',
                "Remuneración {$nombreMes}", "Le corresponde en {$nombreMes}", "Se le retuvo en {$nombreMes}", 'Diferencia', "Dato de {$nombreMes}",
                'Situación', 'Meses con diferencia', 'Meses sin dato'],
        ];
        foreach ($filas as $n => $x) {
            $lista[] = [$n + 1, (string) $x['dni'], $x['nombre'], $x['cargo'], $x['area'], $x['sede'],
                $x['estado'] === 'activo' ? 'Activo' : 'Cesado',
                $x['impuesto_anual'], $x['retenido'], $x['por_retener'],
                $x['trabaja_mes'] ? $x['remuneracion_mes'] : null,
                $x['trabaja_mes'] ? $x['corresponde_mes'] : null,
                $x['retenido_mes'], $x['diferencia_mes'], $fuentes[$x['fuente_mes']] ?? $x['fuente_mes'],
                $situaciones[$x['situacion']], $meses($x['meses_con_diferencia']), $meses($x['meses_sin_dato'])];
        }
        $lista[] = array_merge(['', '', 'TOTAL', '', '', '', ''], [
            round($filas->sum('impuesto_anual'), 2), round($filas->sum('retenido'), 2), round($filas->sum('por_retener'), 2),
            round($filas->sum(fn ($x) => $x['trabaja_mes'] ? $x['remuneracion_mes'] : 0), 2),
            round($filas->sum(fn ($x) => $x['trabaja_mes'] ? $x['corresponde_mes'] : 0), 2),
            round($filas->sum(fn ($x) => $x['retenido_mes'] ?? 0), 2),
            round($filas->sum(fn ($x) => $x['diferencia_mes'] ?? 0), 2),
            '', '', '', '',
        ]);

        $columnasMonto = [7, 8, 9, 10, 11, 12, 13];
        $estiloTotal = array_fill(0, 18, LibroExcel::TOTAL_TEXTO);
        foreach ($columnasMonto as $c) {
            $estiloTotal[$c] = LibroExcel::TOTAL_MONTO;
        }

        // ── La hoja de cada uno ──
        $retencion = [];
        $estiloRetencion = [];
        foreach ($filas as $x) {
            $e = $empleados[$x['id']];
            $h = $hojas[$x['id']];
            $estiloRetencion[count($retencion)] = LibroExcel::SUBTITULO;
            $retencion[] = ['Trabajador: ' . trim("{$e->apellido} {$e->nombre}") . " · DNI {$e->dni}"];
            $estiloRetencion[count($retencion)] = array_fill(0, 14, LibroExcel::TITULO);
            $retencion[] = array_merge(['Detalle'], array_values(self::ABREV));
            $filasHoja = [
                'Dato de'                        => fn ($m) => ['planilla' => 'Sistema', 'historial' => 'Historial', 'proyectado' => 'Estimado', 'no_trabaja' => '—'][$m['fuente']] ?? $m['fuente'],
                'Remuneración del mes'           => fn ($m) => $m['remuneracion_mes'],
                'Nº meses que faltan'            => fn ($m) => $m['meses_que_faltan'],
                'Remuneración proyectada'        => fn ($m) => $m['remuneracion_proyectada'],
                'Gratificaciones (con 9%)'       => fn ($m) => $m['gratificaciones'],
                'Remuneraciones anteriores'      => fn ($m) => $m['remuneraciones_anteriores'],
                'Renta bruta anual'              => fn ($m) => $m['renta_bruta'],
                '(−) 7 UIT'                      => fn ($m) => $m['deduccion'],
                'Renta neta'                     => fn ($m) => $m['renta_neta'],
                'Impuesto del año'               => fn ($m) => $m['impuesto_anual'],
                '(−) Retenido antes'             => fn ($m) => $m['retenido_antes'],
                'Divisor'                        => fn ($m) => $m['divisor'],
                'Retención del mes'              => fn ($m) => $m['retencion_ordinaria'],
                'Retención adicional (extraord.)' => fn ($m) => $m['retencion_adicional'],
                'Total retención calculada'      => fn ($m) => $m['retencion'],
                'Retención real (planilla/historial)' => fn ($m) => $m['retencion_real'],
            ];
            foreach ($filasHoja as $etiqueta => $valor) {
                $retencion[] = array_merge([$etiqueta], array_map(fn ($m) => $m['trabaja'] ? $valor($m) : null, $h['meses']));
            }
            $retencion[] = [''];
        }

        $libro = new LibroExcel();
        $libro->hoja('Lista', $lista, [
            'anchos' => [6, 12, 36, 26, 22, 18, 10, 14, 14, 14, 16, 16, 16, 12, 14, 14, 20, 20],
            'estiloColumnas' => [1 => LibroExcel::TEXTO] + array_fill_keys($columnasMonto, LibroExcel::MONTO),
            'estiloFilas' => [0 => LibroExcel::ENCABEZADO, 1 => LibroExcel::PARRAFO, 3 => array_fill(0, 18, LibroExcel::TITULO),
                count($lista) - 1 => $estiloTotal],
        ]);
        $libro->hoja('Retención', $retencion ?: [['Ningún trabajador con estos filtros.']], [
            'anchos' => array_merge([34], array_fill(1, 12, 12)),
            'estiloColumnas' => array_fill(1, 12, LibroExcel::MONTO),
            'estiloFilas' => $estiloRetencion,
        ]);

        $filtrado = array_filter($f) ? ' (filtrado)' : '';

        return $libro->descargar("Renta de 5ta - {$nombreMes} {$anio}{$filtrado}.xlsx");
    }

    /** «Filtros: Situación: Pagan 5ta, Sede: CATA Central» — para que el Excel diga qué es. */
    private function describirFiltros(array $f, \Illuminate\Support\Collection $todas): string
    {
        $nombreDe = fn (string $campo, string $id) => optional($todas->first(fn ($x) => (string) $x["{$campo}_id"] === $id))[$campo] ?? $id;
        $partes = array_filter([
            ! empty($f['situacion']) ? 'Situación: ' . ['pagan' => 'Pagan 5ta', 'no_pagan' => 'No pagan 5ta', 'diferencias' => 'Con diferencias', 'sin_historial' => 'Les falta historial', 'al_dia' => 'Al día'][$f['situacion']] : null,
            ! empty($f['dato_mes']) ? 'Dato del mes: ' . ['planilla' => 'Su planilla', 'historial' => 'Historial', 'proyectado' => 'Estimado', 'no_trabaja' => 'No trabajó'][$f['dato_mes']] : null,
            ! empty($f['sede_id']) ? 'Sede: ' . $nombreDe('sede', (string) $f['sede_id']) : null,
            ! empty($f['area_id']) ? 'Área: ' . $nombreDe('area', (string) $f['area_id']) : null,
            ! empty($f['cargo_id']) ? 'Cargo: ' . $nombreDe('cargo', (string) $f['cargo_id']) : null,
            ! empty($f['estado']) ? 'Estado: ' . ($f['estado'] === 'activo' ? 'Activos' : 'Cesados') : null,
            ! empty($f['search']) ? "Búsqueda: «{$f['search']}»" : null,
        ]);

        return $partes ? 'Filtros: ' . implode(', ', $partes) : 'Todo el personal';
    }
}
