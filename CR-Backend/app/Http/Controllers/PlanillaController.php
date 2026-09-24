<?php
namespace App\Http\Controllers;
use App\Models\Planilla;
use App\Models\Empleado;
use App\Traits\CalculaConceptosPlanilla;
use App\Support\LibroExcel;
use App\Traits\ExportaExcel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Traits\ListadoPaginado;

class PlanillaController extends Controller
{
    use ListadoPaginado;
    use CalculaConceptosPlanilla;
    use ExportaExcel;
    /**
     * GET /planilla?empleado_id=&empleado_ids=&mes=&anio=&periodo_id=&corrida_id=&sin_corrida=&page=&size=&search=
     *     &sede_id=&area_id=&cargo_id=&tipo_contrato=&estado_empleado=
     *
     * Es la tabla que más crece del sistema: un registro por trabajador y
     * por mes. Los filtros van sobre el índice planilla_empleado_periodo_idx
     * / planilla_periodo_idx, y con ?page el corte lo hace el servidor.
     */
    public function index(Request $request)
    {
        $query = $this->consultaFiltrada($request, ['empleado', 'corrida']);

        return $this->responderListado(
            $request,
            $query->orderBy('anio', 'desc')->orderBy('mes', 'desc'),
            ['empleado.nombre', 'empleado.apellido', 'empleado.dni'],
            // La masa salarial es de TODAS las planillas que pasan el filtro,
            // no de las diez que se están viendo. reorder() quita el ORDER BY,
            // que en una consulta de suma no pinta nada y molesta a MySQL.
            fn (Builder $filtrada) => [
                'masaSalarial' => (float) $filtrada->reorder()->sum('total'),
            ]
        );
    }

    /**
     * Los filtros de la pantalla de Planillas, en un solo sitio.
     *
     * Los comparten el listado y la exportación a propósito: son la MISMA
     * pantalla. Si cada uno filtrara por su cuenta, el día que se agregue un
     * filtro el CSV acabaría trayendo gente que en pantalla no se ve — y
     * nadie revisa un archivo de 150 filas para darse cuenta.
     */
    private function consultaFiltrada(Request $request, array $relaciones): Builder
    {
        // Se valida lo que viene de la pantalla: un id inventado en la
        // dirección tiene que ser un aviso, no una consulta rara a la base.
        $request->validate([
            'sede_id'         => 'nullable|uuid|exists:sedes,id',
            'area_id'         => 'nullable|uuid|exists:areas,id',
            'cargo_id'        => 'nullable|uuid|exists:cargos,id',
            'tipo_contrato'   => 'nullable|in:indeterminado,plazo_fijo,suplencia,practicas',
            'estado_empleado' => 'nullable|in:activo,inactivo',
        ]);

        $query = Planilla::with($relaciones);

        if ($request->filled('empleado_id')) {
            $query->where('empleado_id', $request->empleado_id);
        }
        if ($request->filled('mes')) {
            $query->where('mes', $request->mes);
        }
        if ($request->filled('anio')) {
            $query->where('anio', $request->anio);
        }
        if ($request->filled('periodo_id')) {
            $query->where('periodo_id', $request->periodo_id);
        }

        // ?corrida_id= : las filas de UNA planilla con nombre. Es el segundo
        // nivel de la pantalla: se entra a "Planilla TIC" y salen los suyos.
        if ($request->filled('corrida_id')) {
            $query->where('corrida_id', $request->corrida_id);
        }

        // ?sin_corrida=1 : el grupo "Sin agrupar". Son las planillas de antes
        // de que existieran las corridas, que se quedaron como estaban.
        if ($request->boolean('sin_corrida')) {
            $query->whereNull('corrida_id');
        }

        // ?empleado_ids=id1,id2,... — solo por los trabajadores que se están
        // viendo. Lo usa Emisión de Boletas: la pantalla pinta una página de
        // diez empleados y necesita saber cuáles de ESOS diez ya tienen su
        // planilla del mes; antes se traía las de todo el colegio para
        // marcar diez filas. El tope va a juego con el de la paginación.
        if ($request->filled('empleado_ids')) {
            $ids = array_slice(array_filter(explode(',', (string) $request->input('empleado_ids'))), 0, 200);
            $query->whereIn('empleado_id', $ids);
        }

        // Filtros que miran al TRABAJADOR, no a la planilla. Son los que
        // permiten bajar "la planilla de la sede Jerusalén" o "la de los
        // docentes": la planilla no guarda sede ni área, las tiene su
        // empleado. Van todos en un solo whereHas para no repetir la
        // subconsulta una vez por filtro.
        $delTrabajador = array_filter([
            'sede_id'       => $request->input('sede_id'),
            'area_id'       => $request->input('area_id'),
            'cargo_id'      => $request->input('cargo_id'),
            'tipo_contrato' => $request->input('tipo_contrato'),
            'estado'        => $request->input('estado_empleado'),
        ], fn ($valor) => $valor !== null && $valor !== '');

        if ($delTrabajador) {
            $query->whereHas('empleado', function (Builder $q) use ($delTrabajador) {
                foreach ($delTrabajador as $columna => $valor) {
                    // Con el prefijo de la tabla: áreas, cargos y sedes
                    // también tienen `estado`, y MySQL no sabría de cuál.
                    $q->where('empleados.' . $columna, $valor);
                }
            });
        }

        $rolNombre = $request->user()->rol?->nombre;
        if ($rolNombre !== 'admin') {
            $query->where('estado_registro', 'activo');
        }

        return $query;
    }

    /**
     * GET /planilla/exportar — la planilla completa, en CSV.
     *
     * Es el reporte que RR.HH. llevaba a mano: una fila por trabajador con
     * su ficha, su básico, UNA COLUMNA POR CONCEPTO y su neto. Las columnas
     * de conceptos no están escritas en ningún lado: se arman con los que
     * de verdad aparecen en lo exportado, así que un concepto nuevo sale
     * solo, sin tocar esto.
     *
     * Respeta los mismos filtros que la pantalla: lo que se ve es lo que se
     * descarga. Dentro de una planilla con nombre baja solo la suya; sin
     * filtro de corrida, la de todos los trabajadores del mes.
     */
    public function exportar(Request $request)
    {
        $query = $this->consultaFiltrada($request, [
            'empleado.area', 'empleado.cargo', 'empleado.sede',
            'corrida',
            'payrollDetalles.paymentConcept',
        ]);

        // El mismo buscador de la pantalla: si arriba escribieron un
        // apellido, el archivo sale con esa misma gente.
        $this->aplicarBusqueda($request, $query, ['empleado.nombre', 'empleado.apellido', 'empleado.dni']);

        $planillas = $query->get()->sortBy([
            fn ($p) => mb_strtolower($p->empleado->apellido ?? ''),
            fn ($p) => mb_strtolower($p->empleado->nombre ?? ''),
        ])->values();

        // Las columnas de conceptos: los que aparecen de verdad, agrupados
        // como en la boleta (primero lo que suma, después lo que resta).
        $orden = ['bonificacion' => 1, 'descuento' => 2, 'aportacion' => 3, 'adelanto' => 4];
        $conceptos = [];

        foreach ($planillas as $planilla) {
            foreach ($planilla->payrollDetalles as $linea) {
                $nombre = $linea->paymentConcept->nombre ?? null;
                if ($nombre !== null) {
                    $conceptos[$nombre] = $orden[$linea->paymentConcept->tipo ?? ''] ?? 9;
                }
            }
        }

        asort($conceptos);
        $conceptos = array_keys($conceptos);

        $cabecera = array_merge([
            'N°', 'DNI', 'Apellidos', 'Nombres', 'Área', 'Cargo', 'Sede',
            'Fecha de ingreso', 'Estado', 'Tipo de contrato',
            'Sistema de pensión', 'AFP', 'CUSPP',
            'Forma de pago', 'Banco', 'N° de cuenta', 'CCI',
            'Planilla', 'Mes', 'Año', 'Sueldo base',
        // Sin columnas de "Bonificaciones" y "Descuentos" sueltas: cada monto
        // sale ya en la columna de SU concepto, que es lo que se declara.
        ], $conceptos, ['Neto a pagar']);

        $sumas = array_fill_keys(['base', 'neto'], 0.0);

        // Y el total de CADA concepto, que es lo que se declara al PLAME:
        // cuánto se retuvo de ONP, cuánto de AFP, cuánto de EsSalud. Con esas
        // columnas en blanco había que sumarlas a mano en Excel, que es justo
        // el trabajo que este reporte viene a quitar.
        $sumasConcepto = [];
        $filas = [];

        foreach ($planillas as $i => $planilla) {
            $e = $planilla->empleado;

            // Los montos de ESTA planilla, por nombre de concepto.
            $montos = [];
            foreach ($planilla->payrollDetalles as $linea) {
                $nombre = $linea->paymentConcept->nombre ?? null;
                if ($nombre !== null) {
                    $montos[$nombre] = round((float) ($montos[$nombre] ?? 0) + (float) $linea->monto_calculado, 2);
                }
            }

            $fila = [
                $i + 1,
                (string) ($e->dni ?? ''),
                $e->apellido ?? '',
                $e->nombre ?? '',
                $e->area->nombre ?? '',
                $e->cargo->nombre ?? '',
                $e->sede->nombre ?? '',
                $e && $e->fecha_ingreso ? \Carbon\Carbon::parse($e->fecha_ingreso)->format('d/m/Y') : '',
                $e->estado ?? '',
                $e->tipo_contrato ?? '',
                // Vacío no es un olvido: es "no aporta a ninguna pensión".
                $e && $e->sistema_pensiones ? $e->sistema_pensiones : 'No aporta',
                $e->afp ?? '',
                (string) ($e->cuspp ?? ''),
                $e->forma_pago ?? '',
                $e->entidad_financiera ?? '',
                (string) ($e->numero_cuenta ?? ''),
                (string) ($e->cci ?? ''),
                $planilla->corrida->nombre ?? 'Sin agrupar',
                \App\Support\Meses::nombre((int) $planilla->mes),
                (int) $planilla->anio,
                round((float) $planilla->sueldo_base, 2),
            ];

            foreach ($conceptos as $nombre) {
                // Vacío, no cero: "no se le aplicó" y "se le aplicó S/ 0"
                // no son lo mismo cuando alguien revisa por qué cobró de menos.
                if (! isset($montos[$nombre])) {
                    $fila[] = null;
                    continue;
                }

                $fila[] = $montos[$nombre];
                $sumasConcepto[$nombre] = round(($sumasConcepto[$nombre] ?? 0) + $montos[$nombre], 2);
            }

            $fila[] = round((float) $planilla->total, 2);

            $sumas['base'] += (float) $planilla->sueldo_base;
            $sumas['neto'] += (float) $planilla->total;

            $filas[] = $fila;
        }

        // La fila de totales, que es lo que se firma al final del reporte.
        $estiloFilas = [];
        if ($planillas->isNotEmpty()) {
            $totales = array_merge(
                ['', '', 'TOTALES', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
                 round($sumas['base'], 2)],
                array_map(fn ($nombre) => round($sumasConcepto[$nombre] ?? 0, 2), $conceptos),
                [round($sumas['neto'], 2)]
            );

            $filas[] = $totales;
            $estiloFilas[count($filas)] = array_map(
                fn ($valor) => is_string($valor) ? LibroExcel::TOTAL_TEXTO : LibroExcel::TOTAL_MONTO,
                $totales
            );
        }

        // Los montos van como números: el reporte se abre y ya se puede
        // filtrar y sumar. Lo que es dato de identidad (DNI, cuenta, CCI) va
        // como texto, para que no pierda ceros ni se convierta en notación
        // científica.
        $primerConcepto = 21;
        $estiloColumnas = [];
        foreach ($cabecera as $i => $titulo) {
            $estiloColumnas[$i] = match (true) {
                in_array($titulo, ['DNI', 'Fecha de ingreso', 'CUSPP', 'N° de cuenta', 'CCI'], true) => LibroExcel::TEXTO,
                $titulo === 'Sueldo base' || $i >= $primerConcepto => LibroExcel::MONTO,
                default => LibroExcel::NORMAL,
            };
        }

        $libro = new LibroExcel();
        $this->hojaDeReporte($libro, 'Planilla', $cabecera, $filas, $estiloColumnas, $estiloFilas);
        $this->hojaDeFiltros($libro, $this->filtrosDelReporte($request), $request->user()?->name);

        return $libro->descargar($this->nombreDelArchivo($request, $planillas->first()));
    }

    /** "Planilla TIC - Septiembre 2026.xlsx", o el mes suelto. */
    /**
     * Los filtros de la pantalla, escritos como los lee una persona: con el
     * nombre de la sede y del mes, no con el id ni el número.
     *
     * @return array<string, string|null>
     */
    private function filtrosDelReporte(Request $request): array
    {
        $mes  = $request->input('mes');
        $anio = $request->input('anio');

        $periodo = match (true) {
            $mes && $anio => \App\Support\Meses::nombre((int) $mes) . ' ' . $anio,
            (bool) $mes   => \App\Support\Meses::nombre((int) $mes) . ' de todos los años',
            (bool) $anio  => 'Todo el año ' . $anio,
            default       => null,
        };

        $empleado = $request->filled('empleado_id')
            ? Empleado::find($request->input('empleado_id'))
            : null;

        return [
            'Mes y año'            => $periodo,
            'Año escolar'          => $this->nombreDeCatalogo(\App\Models\Periodo::class, $request->input('periodo_id')),
            'Sede'                 => $this->nombreDeCatalogo(\App\Models\Sede::class, $request->input('sede_id')),
            'Área'                 => $this->nombreDeCatalogo(\App\Models\Area::class, $request->input('area_id')),
            'Cargo'                => $this->nombreDeCatalogo(\App\Models\Cargo::class, $request->input('cargo_id')),
            'Tipo de contrato'     => $request->input('tipo_contrato'),
            'Estado del trabajador' => match ($request->input('estado_empleado')) {
                'activo'   => 'Solo activos',
                'inactivo' => 'Solo cesados',
                default    => null,
            },
            'Trabajador'           => $empleado ? $empleado->apellido . ', ' . $empleado->nombre : null,
            'Planilla'             => $this->nombreDeCatalogo(\App\Models\PlanillaCorrida::class, $request->input('corrida_id')),
            'Solo sin agrupar'     => $request->boolean('sin_corrida') ? 'Sí' : null,
            'Búsqueda'             => $request->input('search'),
        ];
    }

    private function nombreDelArchivo(Request $request, ?Planilla $primera): string
    {
        $mes  = (int) ($request->input('mes') ?: $primera->mes ?? 0);
        $anio = (int) ($request->input('anio') ?: $primera->anio ?? 0);

        $periodo = $mes ? \App\Support\Meses::nombre($mes) : 'Todos los meses';
        $periodo .= $anio ? ' ' . $anio : '';

        $ambito = $request->boolean('sin_corrida')
            ? 'Sin agrupar'
            : ($primera?->corrida->nombre ?? 'Planilla general');

        return $this->nombreExcelSeguro($ambito . ' - ' . $periodo);
    }

    public function store(Request $request)
    {
        $request->validate([
            'empleado_id'    => 'required|exists:empleados,id',
            'mes'            => 'required|integer|min:1|max:12',
            'anio'           => 'required|integer|min:2000',
            // Sin 'bonificaciones' ni 'descuentos': lo que suma o resta a un
            // sueldo va por conceptos, con su etiqueta y su rastro.
            'periodo_id'     => 'nullable|uuid|exists:periodos,id',
            'corrida_id'     => 'nullable|uuid|exists:planilla_corridas,id',
        ]);

        $existe = Planilla::where('empleado_id', $request->empleado_id)
            ->where('mes', $request->mes)
            ->where('anio', $request->anio)
            ->first();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una planilla para este empleado en el periodo indicado.'
            ], 422);
        }

        // Jalar sueldo_base directamente del empleado — no se puede editar.
        // Si entró a mitad de mes se prorratea por los días que le tocan: darle
        // el mes entero a quien empezó el día 28 es pagarle de más.
        $empleado    = Empleado::findOrFail($request->empleado_id);
        $sueldo_base = $this->sueldoDelMes($empleado, (int) $request->mes, (int) $request->anio);

        if ($sueldo_base === null) {
            return response()->json([
                'success' => false,
                'message' => 'Este empleado todavía no había ingresado en ' . $request->mes . '/' . $request->anio .
                             ' (ingresó el ' . \Carbon\Carbon::parse($empleado->fecha_ingreso)->format('d/m/Y') . ').',
            ], 422);
        }

        $planilla = Planilla::create([
            'empleado_id'    => $request->empleado_id,
            'mes'            => $request->mes,
            'anio'           => $request->anio,
            'periodo_id'     => $request->periodo_id ?? null,
            'corrida_id'     => $request->corrida_id ?? null,
            'sueldo_base'    => $sueldo_base,
            'total'          => 0, // se recalcula abajo (aún no tiene PayrollDetalle, pero centraliza la fórmula)
        ]);

        $this->generarConceptosAutomaticos($planilla, $empleado);
        $planilla->recalcularTotal();

        return response()->json(['success' => true, 'data' => $planilla->load('payrollDetalles.paymentConcept')], 201);
    }

    /**
     * POST /planilla/{id}/conceptos — deja sus líneas como diga la pantalla.
     *
     * Recibe una lista de `{nombre, monto}` y la sincroniza de una vez: crea o
     * actualiza los que traen monto, y borra los que llegan en cero. Todo
     * dentro de una transacción, porque dejar media planilla sincronizada es
     * peor que no haber empezado.
     *
     * Existe para que la pantalla de Emisión de Boletas deje de guardar dos
     * totales anónimos. Ahí se escriben conceptos con nombre —diezmo,
     * alimentación, CTS— y se guardaban sumados en dos columnas, perdiendo
     * justo lo que importa: cuál era cuál.
     *
     * Solo toca los conceptos QUE SE NOMBRAN. Los que no aparecen en la lista
     * se quedan como están, así que las líneas automáticas de otro origen no
     * se pierden por no venir mencionadas.
     */
    public function sincronizarConceptos(Request $request, string $id)
    {
        $planilla = Planilla::findOrFail($id);

        $datos = $request->validate([
            'conceptos'          => 'present|array',
            'conceptos.*.nombre' => 'required|string|max:150',
            'conceptos.*.monto'  => 'nullable|numeric|min:0',
        ]);

        /*
         * Los que NO se aceptan a mano.
         *
         * Pensión, EsSalud y Renta de 5ta los calcula el motor según la ficha
         * de cada quien; la Asignación Familiar sale de `tiene_hijos`. Dejar
         * que una pantalla los escriba permitiría pisar el valor correcto con
         * uno malo, y encima sin que nadie se entere. Se rechaza acá y no solo
         * en el desplegable: una pantalla no es una cerradura.
         */
        $protegidos = array_merge(
            \App\Support\ConceptosDePago::CALCULO_ESPECIAL,
            [\App\Support\ConceptosDePago::ASIGNACION_FAMILIAR]
        );

        $nombres  = array_column($datos['conceptos'], 'nombre');
        $chocan   = array_values(array_intersect($nombres, $protegidos));

        if (! empty($chocan)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'conceptos' => ['Estos conceptos los calcula el sistema y no se escriben a mano: ' . implode(', ', $chocan) . '.'],
            ]);
        }

        $catalogo = \App\Models\PaymentConcept::whereIn('nombre', $nombres)->get()->keyBy('nombre');

        $desconocidos = array_values(array_diff($nombres, $catalogo->keys()->all()));
        if (! empty($desconocidos)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'conceptos' => ['No existen en el catálogo: ' . implode(', ', $desconocidos) . '.'],
            ]);
        }

        $puestos = 0;
        $quitados = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($datos, $catalogo, $planilla, &$puestos, &$quitados) {
            foreach ($datos['conceptos'] as $linea) {
                $concepto = $catalogo[$linea['nombre']];
                $monto    = round((float) ($linea['monto'] ?? 0), 2);

                if ($monto <= 0) {
                    $quitados += \App\Models\PayrollDetalle::where('planilla_id', $planilla->id)
                        ->where('payment_concept_id', $concepto->id)
                        ->delete();
                    continue;
                }

                \App\Models\PayrollDetalle::updateOrCreate(
                    ['planilla_id' => $planilla->id, 'payment_concept_id' => $concepto->id],
                    ['monto_calculado' => $monto]
                );
                $puestos++;
            }
        });

        $planilla->recalcularTotal();

        return response()->json([
            'success' => true,
            'data'    => [
                'planilla' => $planilla->fresh()->load('payrollDetalles.paymentConcept'),
                'resumen'  => ['puestos' => $puestos, 'quitados' => $quitados],
            ],
        ]);
    }

    public function show(string $id)
    {
        $planilla = Planilla::with('empleado')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $planilla]);
    }

    /**
     * PUT /planilla/{id} — ya no queda nada editable acá.
     *
     * Lo único que aceptaba eran `bonificaciones` y `descuentos`, dos montos
     * sueltos sin nombre ni motivo. Ahora lo que sube o baja un sueldo se
     * ajusta por conceptos (POST /payroll-detalles o "aplicar a grupo"), que
     * sí dejan etiqueta, regla y rastro. El sueldo base nunca fue editable:
     * sale de la ficha del trabajador.
     *
     * Se mantiene respondiendo 200 y recalculando —por si el total quedó
     * viejo respecto a sus conceptos— en vez de rechazar, para no romper a
     * quien lo llame. Queda como candidato a eliminarse del todo.
     */
    public function update(Request $request, string $id)
    {
        $planilla = Planilla::findOrFail($id);

        $planilla->recalcularTotal();

        return response()->json(['success' => true, 'data' => $planilla->fresh()]);
    }

    public function destroy(string $id)
    {
        $planilla = Planilla::findOrFail($id);
        $planilla->update(['estado_registro' => 'inactivo']);
        return response()->json(['success' => true, 'data' => ['message' => 'Planilla eliminada correctamente.']]);
    }
}