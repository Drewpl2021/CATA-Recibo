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
 *   GET  /income-tax/export?anio=                todas las hojas en un Excel
 */
class Renta5taController extends Controller
{
    private const ABREV = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];

    /** El personal del año: los activos y quien tenga planilla o historial ese año. */
    private function personalDelAnio(int $anio)
    {
        return Empleado::with('cargo:id,nombre', 'area:id,nombre')
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
     */
    public function index(Request $request)
    {
        $anio = $this->anio($request);
        $hoy = now();
        $mesPorDefecto = $anio === (int) $hoy->year ? (int) $hoy->month : ($anio < (int) $hoy->year ? 12 : 1);
        $mes = (int) ($request->validate(['mes' => 'nullable|integer|min:1|max:12'])['mes'] ?? $mesPorDefecto);
        $motor = new MotorRenta5ta();

        $filas = $this->personalDelAnio($anio)->map(function (Empleado $e) use ($motor, $anio, $mes) {
            $hoja = $motor->hoja($e, $anio);
            $delMes = $hoja['meses'][$mes - 1];
            $real = $delMes['trabaja'] ? $delMes['retencion_real'] : null;

            return [
                'id'                    => $e->id,
                'dni'                   => $e->dni,
                'nombre'                => trim("{$e->apellido} {$e->nombre}"),
                'cargo'                 => $e->cargo?->nombre,
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
                'paga_5ta'              => $hoja['resumen']['impuesto_anual'] > 0 || $hoja['resumen']['retenido'] > 0,
                'meses_con_diferencia'  => $this->mesesConDiferencia($hoja['meses']),
                'meses_sin_dato'        => $hoja['resumen']['meses_sin_dato'],
            ];
        })->values();

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
    public function exportar(Request $request)
    {
        $anio = $this->anio($request);
        $motor = new MotorRenta5ta();
        $personal = $this->personalDelAnio($anio);

        $resumen = [['N°', 'DNI', 'Apellidos y Nombres', 'Impuesto del año', 'Retenido', 'Por retener', 'Meses sin dato']];
        $hojas = [];
        $estiloHojas = [];
        $progreso = Progreso::actual()->etapa('Armando la hoja de cada trabajador', $personal->count());

        foreach ($personal->values() as $i => $e) {
            $progreso->avanzar();
            $h = $motor->hoja($e, $anio);
            $resumen[] = [$i + 1, (string) $e->dni, trim("{$e->apellido}, {$e->nombre}", ', '),
                $h['resumen']['impuesto_anual'], $h['resumen']['retenido'], $h['resumen']['por_retener'],
                implode(', ', array_map(fn ($m) => self::ABREV[$m], $h['resumen']['meses_sin_dato']))];

            $estiloHojas[count($hojas)] = LibroExcel::SUBTITULO;
            $hojas[] = ['Trabajador: ' . trim("{$e->apellido} {$e->nombre}") . " · DNI {$e->dni}"];
            $estiloHojas[count($hojas)] = array_fill(0, 14, LibroExcel::TITULO);
            $hojas[] = array_merge(['Detalle'], array_values(self::ABREV), ['']);
            $filasHoja = [
                'Dato de'                        => fn ($f) => ['planilla' => 'Sistema', 'historial' => 'Historial', 'proyectado' => 'Proyectado', 'no_trabaja' => '—'][$f['fuente']] ?? $f['fuente'],
                'Remuneración del mes'           => fn ($f) => $f['remuneracion_mes'],
                'Nº meses que faltan'            => fn ($f) => $f['meses_que_faltan'],
                'Remuneración proyectada'        => fn ($f) => $f['remuneracion_proyectada'],
                'Gratificaciones (con 9%)'       => fn ($f) => $f['gratificaciones'],
                'Remuneraciones anteriores'      => fn ($f) => $f['remuneraciones_anteriores'],
                'Renta bruta anual'              => fn ($f) => $f['renta_bruta'],
                '(−) 7 UIT'                      => fn ($f) => $f['deduccion'],
                'Renta neta'                     => fn ($f) => $f['renta_neta'],
                'Impuesto del año'               => fn ($f) => $f['impuesto_anual'],
                '(−) Retenido antes'             => fn ($f) => $f['retenido_antes'],
                'Divisor'                        => fn ($f) => $f['divisor'],
                'Retención del mes'              => fn ($f) => $f['retencion_ordinaria'],
                'Retención adicional (extraord.)' => fn ($f) => $f['retencion_adicional'],
                'Total retención calculada'      => fn ($f) => $f['retencion'],
                'Retención real (planilla/historial)' => fn ($f) => $f['retencion_real'],
            ];
            foreach ($filasHoja as $etiqueta => $valor) {
                $hojas[] = array_merge([$etiqueta], array_map(fn ($f) => $f['trabaja'] ? $valor($f) : null, $h['meses']));
            }
            $hojas[] = [''];
        }

        $libro = new LibroExcel();
        $libro->hoja('Resumen', $resumen, [
            'anchos' => [6, 12, 36, 16, 14, 14, 24], 'estiloColumnas' => [1 => LibroExcel::TEXTO, 3 => LibroExcel::MONTO, 4 => LibroExcel::MONTO, 5 => LibroExcel::MONTO],
            'estiloFilas' => [0 => array_fill(0, 7, LibroExcel::TITULO)], 'congelarPrimeraFila' => true,
        ]);
        $libro->hoja('Retención', $hojas, [
            'anchos' => array_merge([34], array_fill(1, 12, 12)),
            'estiloColumnas' => array_fill(1, 12, LibroExcel::MONTO),
            'estiloFilas' => $estiloHojas,
        ]);

        return $libro->descargar("Renta de 5ta {$anio}.xlsx");
    }
}
