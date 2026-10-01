<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\Planilla;
use App\Models\Sede;
use App\Support\Meses;
use App\Traits\ExportaExcel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title as ChartTitle;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
    /**
     * El panel entero en un Excel, con el filtro que se está mirando.
     *
     * Lo que se ve es lo que baja: el mismo mes y la misma sede. Antes
     * bajaba solo texto y números —ni uno solo de los gráficos que se ven
     * en pantalla—, así que para enseñarlo en una reunión había que
     * rearmar los gráficos a mano en Excel. Ahora van adentro de verdad:
     * son gráficos NATIVOS (apuntan a las celdas de al lado, no son una
     * foto), así que se pueden tocar, cambiarles el color, o copiarlos a
     * una presentación como cualquier gráfico hecho a mano.
     *
     * Es el único reporte de la app que usa PhpSpreadsheet en vez de
     * LibroExcel —el escritor propio, sin librería, que arma el resto de
     * los Excel del sistema—: un gráfico de verdad implica varias piezas
     * de XML relacionadas entre sí (el dibujo, el gráfico, la hoja que los
     * aloja), justo el tipo de detalle de formato para el que sí conviene
     * apoyarse en una librería ya probada en vez de escribirlo a mano.
     */
    public function exportar(Request $request)
    {
        ['mes' => $mes, 'anio' => $anio, 'sede' => $sede] = $this->filtroPedido($request);

        $datos   = $this->datos($mes, $anio, $sede);
        $resumen = $datos['resumen'];
        $firma   = $datos['firmaBoletas'];

        $periodo    = Meses::nombre($mes) . ' ' . $anio;
        $nombreSede = $sede ? (Sede::find($sede)->nombre ?? 'Sede') : 'Todas las sedes';

        // Etiqueta y monto / etiqueta y cuenta, tal como los manda el panel.
        $conMonto  = fn (array $lista) => array_map(fn ($x) => [$x['etiqueta'], $x['valor'], true], $lista);
        $conCuenta = fn (array $lista) => array_map(fn ($x) => [$x['etiqueta'], $x['valor']], $lista);

        $libro = new Spreadsheet();
        $libro->getProperties()->setTitle('Panel de control ' . $periodo)->setCreator('CATA-Recibo');

        $this->hojaResumen($libro, [
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

        $this->hojaConGraficos($libro, 'Nómina', [
            'A dónde se va la nómina (S/)'      => ['datos' => $conMonto($datos['composicionNomina']), 'grafico' => 'barras'],
            'Remuneración por área (S/)'        => ['datos' => $conMonto($datos['remuneracionPorArea']), 'grafico' => 'barras'],
            'Los conceptos que más pesan (S/)'  => ['datos' => $conMonto($datos['topConceptos']), 'grafico' => 'barras'],
            "Nómina mes a mes de {$anio} (S/)"  => ['datos' => $conMonto($datos['tendenciaNomina']), 'grafico' => 'linea'],
        ]);

        $this->hojaConGraficos($libro, 'Plantilla', [
            'Personal por sede'         => ['datos' => $conCuenta($datos['personalPorSede']), 'grafico' => 'dona'],
            'Sistema de pensiones'      => ['datos' => $conCuenta($datos['sistemaPensiones']), 'grafico' => 'dona'],
            'Tipo de contrato'          => ['datos' => $conCuenta($datos['tipoContrato']), 'grafico' => 'torta'],
            'Antigüedad en el colegio'  => ['datos' => $conCuenta($datos['antiguedad']), 'grafico' => 'barras'],
        ]);

        $this->tablaSimple(
            $libro,
            'Contratos por vencer',
            ['Trabajador', 'Cargo', 'Vence el', 'Días que faltan'],
            array_map(fn (array $c) => [
                $c['nombre'],
                $c['cargo'],
                Carbon::parse($c['fecha'])->format('d/m/Y'),
                $c['dias'],
            ], $datos['contratosPorVencer']),
            columnasTexto: [2]
        );

        $this->tablaSimple(
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
            columnasTexto: [0]
        );

        // La misma hoja de filtros que los otros reportes del sistema, para
        // que se lean igual y quede escrito quién bajó qué.
        $filtrosFilas = [['Mes y año', $periodo], ['Sede', $sede ? $nombreSede : 'Todas las sedes']];
        $this->tablaSimple($libro, 'Filtros', ['Filtro', 'Lo que se eligió'], $filtrosFilas);
        $hojaFiltros = $libro->getSheetByName('Filtros');
        $filaExtra = count($filtrosFilas) + 3;
        $hojaFiltros->setCellValue("A{$filaExtra}", 'Descargado el');
        $hojaFiltros->setCellValue("B{$filaExtra}", now()->format('d/m/Y H:i'));
        if ($quien = $request->user()?->name) {
            $hojaFiltros->setCellValue('A' . ($filaExtra + 1), 'Descargado por');
            $hojaFiltros->setCellValue('B' . ($filaExtra + 1), $quien);
        }

        // "Resumen" es la que se abre primero: es la misma que arma
        // hojaResumen() reutilizando la hoja en blanco que trae
        // Spreadsheet() recién creada, así que ya queda de primera sin
        // tener que reordenar nada acá.
        $libro->setActiveSheetIndex(0);

        $ruta = tempnam(sys_get_temp_dir(), 'panel');
        $escritor = new Xlsx($libro);
        // Sin esto, PhpSpreadsheet arma el archivo IGUAL pero se guarda los
        // gráficos para sí: por default no los escribe al .xlsx.
        $escritor->setIncludeCharts(true);
        $escritor->save($ruta);
        $libro->disconnectWorksheets();

        return response()
            ->download($ruta, $this->nombreExcelSeguro(
                'Panel de control ' . $periodo . ($sede ? ' - ' . $nombreSede : '')
            ), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend(true);
    }

    /** Cuándo se bajó el archivo, para saber de qué día son las cifras. */
    private function cuando(): string
    {
        return Carbon::now()->format('d/m/Y H:i');
    }

    /**
     * La hoja "Resumen": las cifras sueltas de siempre, y una dona con la
     * firma de las boletas del mes —el único bloque de esta hoja que
     * también es un gráfico en pantalla (el anillo de firmadas/vistas/
     * pendientes), así que además de la tabla lleva su gráfico al lado.
     *
     * @param  array<string, array<int, array{0: string, 1: mixed, 2?: bool}>>  $bloques
     */
    private function hojaResumen(Spreadsheet $libro, array $bloques): void
    {
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Resumen');

        $rangos = $this->escribirBloques($hoja, 1, $bloques);
        $this->anchoColumnas($hoja, [1 => 36, 2 => 18]);

        if (isset($rangos['Firma de las boletas del mes'])) {
            $this->agregarGrafico($hoja, 'dona', 'Firma de las boletas del mes', $rangos['Firma de las boletas del mes'], 'D2');
        }
    }

    /**
     * Una hoja de "concepto y valor" con varios bloques, cada uno con su
     * propio gráfico flotando a la derecha de la tabla —la tabla no se
     * toca: el gráfico apunta a esas mismas celdas, nunca lleva los
     * números adentro.
     *
     * @param  array<string, array{datos: array<int, array{0: string, 1: mixed, 2?: bool}>, grafico: string}>  $bloques
     *         título de la sección => datos [concepto, valor, es monto] + qué tipo de gráfico
     */
    private function hojaConGraficos(Spreadsheet $libro, string $nombre, array $bloques): void
    {
        $hoja = $libro->createSheet();
        $hoja->setTitle($nombre);

        $soloDatos = array_map(fn (array $b) => $b['datos'], $bloques);
        $rangos = $this->escribirBloques($hoja, 1, $soloDatos);
        $this->anchoColumnas($hoja, [1 => 34, 2 => 16]);

        $filaGrafico = 2;
        foreach ($bloques as $titulo => $bloque) {
            if (! isset($rangos[$titulo])) {
                continue;
            }

            $this->agregarGrafico($hoja, $bloque['grafico'], $titulo, $rangos[$titulo], "D{$filaGrafico}");
            // Cada gráfico ocupa unas 15 filas de alto; el siguiente empieza
            // debajo del anterior, con un respiro de 2 filas entre los dos.
            $filaGrafico += 17;
        }
    }

    /**
     * Escribe varios bloques de "concepto y valor" uno debajo del otro, en
     * las columnas A y B desde la fila indicada —la misma idea que tenía
     * la vieja hojaDeCifras, ahora con PhpSpreadsheet porque estas hojas
     * también llevan gráficos—. Devuelve en qué filas cayó cada bloque: un
     * gráfico de Excel no lleva los números adentro, apunta a celdas de la
     * hoja, así que hace falta saber exactamente dónde quedó cada cosa.
     *
     * @param  array<string, array<int, array{0: string, 1: mixed, 2?: bool}>>  $bloques
     * @return array<string, array{colCategorias: string, colValores: string, filaInicio: int, filaFin: int}>
     */
    private function escribirBloques(Worksheet $hoja, int $filaInicial, array $bloques): array
    {
        $fila = $filaInicial;
        $rangos = [];

        foreach ($bloques as $titulo => $lineas) {
            $hoja->setCellValue("A{$fila}", $titulo);
            $hoja->mergeCells("A{$fila}:B{$fila}");
            $hoja->getStyle("A{$fila}")->getFont()->setBold(true)->setSize(12);
            $hoja->getStyle("A{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E7EEF9');
            $fila++;

            $filaInicioBloque = $fila;

            foreach ($lineas as $linea) {
                $hoja->setCellValue("A{$fila}", (string) $linea[0]);
                // setCellValue a secas y no Explicit: la mayoría de estos
                // valores SÍ son números (para poder sumarlos en Excel sin
                // tocarlos), pero el bloque "Lo que se está mirando" trae
                // texto (el periodo, la sede, la fecha de descarga) — forzar
                // tipo numérico ahí habría dejado celdas rotas.
                $hoja->setCellValue("B{$fila}", $linea[1]);
                if ($linea[2] ?? false) {
                    $hoja->getStyle("B{$fila}")->getNumberFormat()->setFormatCode('#,##0.00');
                }
                $fila++;
            }

            $rangos[$titulo] = [
                'colCategorias' => 'A',
                'colValores'    => 'B',
                'filaInicio'    => $filaInicioBloque,
                'filaFin'       => $fila - 1,
            ];

            $fila++; // línea en blanco entre bloques, igual que se leían en pantalla
        }

        return $rangos;
    }

    /**
     * Un gráfico NATIVO de Excel: apunta a las celdas donde ya se escribió
     * el bloque (ver escribirBloques()) en vez de llevar los números
     * adentro, así que se puede editar, recolorear o copiar a una
     * presentación como cualquier gráfico hecho a mano en Excel.
     *
     * $tipo: 'barras' | 'linea' | 'dona' | 'torta'.
     */
    private function agregarGrafico(Worksheet $hoja, string $tipo, string $titulo, array $rango, string $celdaAncla): void
    {
        $cuantos = $rango['filaFin'] - $rango['filaInicio'] + 1;
        if ($cuantos < 1) {
            // El bloque salió vacío (sin planillas ese mes, por ejemplo): un
            // gráfico sin ni un punto no dice nada, mejor no ponerlo.
            return;
        }

        $hojaNombre = $hoja->getTitle();
        $rangoCategorias = "'{$hojaNombre}'!\${$rango['colCategorias']}\${$rango['filaInicio']}:\${$rango['colCategorias']}\${$rango['filaFin']}";
        $rangoValores    = "'{$hojaNombre}'!\${$rango['colValores']}\${$rango['filaInicio']}:\${$rango['colValores']}\${$rango['filaFin']}";

        $categorias = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $rangoCategorias, null, $cuantos)];
        // El azul institucional en las de una sola serie (barras y líneas);
        // en las de varios colores (dona, torta) se deja la paleta que ya
        // trae Excel, que distingue bien sus porciones sin tener que
        // pintarlas una por una.
        $colorSerie = in_array($tipo, ['dona', 'torta'], true) ? null : '1B4282';
        $valores = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $rangoValores, null, $cuantos, null, null, $colorSerie)];

        $tipoReal = match ($tipo) {
            'barras' => DataSeries::TYPE_BARCHART,
            'linea'  => DataSeries::TYPE_LINECHART,
            'dona'   => DataSeries::TYPE_DONUTCHART,
            'torta'  => DataSeries::TYPE_PIECHART,
            default  => DataSeries::TYPE_BARCHART,
        };

        $agrupacion = match ($tipo) {
            'barras' => DataSeries::GROUPING_CLUSTERED,
            'linea'  => DataSeries::GROUPING_STANDARD,
            default  => null,
        };

        // Las barras van horizontales (DIRECTION_BAR): "Remuneración por
        // área" y "Los conceptos que más pesan" traen nombres largos —el
        // nombre de un área, el de un concepto de pago— que en una barra
        // vertical se amontonan de costado y no se leen.
        $direccion = $tipo === 'barras' ? DataSeries::DIRECTION_BAR : null;

        $serie = new DataSeries($tipoReal, $agrupacion, [0], [], $categorias, $valores, $direccion);

        $plotArea = new PlotArea(null, [$serie]);
        $leyenda = in_array($tipo, ['dona', 'torta'], true) ? new Legend(Legend::POSITION_RIGHT, null, false) : null;
        $grafico = new Chart(uniqid('grafico_'), new ChartTitle($titulo), $leyenda, $plotArea);

        $grafico->setTopLeftPosition($celdaAncla);
        [$colAncla, $filaAncla] = Coordinate::coordinateFromString($celdaAncla);
        $colFin = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($colAncla) + 6);
        $grafico->setBottomRightPosition($colFin . ((int) $filaAncla + 15));

        $hoja->addChart($grafico);
    }

    /**
     * Una tabla simple: títulos en azul, primera fila fija y cada columna
     * tan ancha como lo que lleva dentro — lo mismo que hacía hojaDeReporte
     * con LibroExcel, pero con PhpSpreadsheet porque este libro ya no usa
     * aquel escritor.
     *
     * @param  array<int, string>             $titulos
     * @param  array<int, array<int, mixed>>  $filas
     * @param  array<int, int>                $columnasTexto  columnas (desde 0) que NUNCA se
     *         reinterpretan como número o fecha — las fechas escritas como
     *         "15/03/2026" van acá, para que Excel no las vuelva a
     *         convertir y de paso les cambie el formato.
     */
    private function tablaSimple(
        Spreadsheet $libro,
        string $nombre,
        array $titulos,
        array $filas,
        bool $conTitulos = true,
        array $columnasTexto = []
    ): void {
        $hoja = $libro->createSheet();
        $hoja->setTitle($nombre);

        $filaActual = 1;

        if ($conTitulos) {
            foreach ($titulos as $i => $titulo) {
                $col = Coordinate::stringFromColumnIndex($i + 1);
                $hoja->setCellValue("{$col}{$filaActual}", $titulo);
            }
            $hoja->getStyle('A1:' . Coordinate::stringFromColumnIndex(count($titulos)) . '1')
                ->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $hoja->getStyle('A1:' . Coordinate::stringFromColumnIndex(max(count($titulos), 1)) . '1')
                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B4282');
            $hoja->freezePane('A2');
            $filaActual++;
        }

        foreach ($filas as $fila) {
            foreach (array_values($fila) as $i => $valor) {
                $col = Coordinate::stringFromColumnIndex($i + 1);
                if (in_array($i, $columnasTexto, true)) {
                    $hoja->setCellValueExplicit("{$col}{$filaActual}", (string) $valor, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $hoja->setCellValue("{$col}{$filaActual}", $valor);
                }
            }
            $filaActual++;
        }

        // El ancho de cada columna sale de las primeras filas: mirar
        // miles de filas de un reporte grande costaría más que escribirlo.
        $referencia = $titulos ?: ($filas[0] ?? []);
        foreach (array_values($referencia) as $i => $_) {
            $largo = mb_strlen((string) ($referencia[$i] ?? ''));
            foreach (array_slice($filas, 0, 200) as $fila) {
                $largo = max($largo, mb_strlen((string) (array_values($fila)[$i] ?? '')));
            }
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth(min(38, max(10, $largo + 2)));
        }
    }

    /** @param  array<int, float>  $anchos  columna (desde 1) => ancho */
    private function anchoColumnas(Worksheet $hoja, array $anchos): void
    {
        foreach ($anchos as $col => $ancho) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth($ancho);
        }
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
