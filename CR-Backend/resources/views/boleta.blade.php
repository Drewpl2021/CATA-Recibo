<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            size: A4 landscape;
            margin: 5mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #222;
            zoom: 0.68;
        }

        .boleta {
            width: 96%;
            margin: 0 auto;
            padding: 15px;
            border: 2px solid #1B4282;
        }

        .boleta + .boleta {
            page-break-before: always;
        }

        .encabezado {
            display: table;
            width: 100%;
            border-bottom: 2px solid #1B4282;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .encabezado-logo {
            display: table-cell;
            width: 70px;
            vertical-align: middle;
        }

        .encabezado-logo img {
            width: 65px;
            height: 65px;
        }

        .encabezado-texto {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
        }

        /* Arriba de todo y discreta: es protocolo, no el título de la boleta. */
        .encabezado-texto .nombre-anio {
            font-size: 8px;
            font-style: italic;
            color: #45506A;
            margin: 0 0 3px;
        }

        .encabezado-texto h1 {
            font-size: 13px;
            color: #0E2650;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .encabezado-texto .ruc {
            font-size: 9px;
            color: #666;
            margin-top: 1px;
        }

        .encabezado-texto h2 {
            font-size: 11px;
            color: #1B4282;
            margin-top: 4px;
            font-weight: bold;
        }

        .encabezado-texto p {
            font-size: 10px;
            color: #666;
            margin-top: 2px;
        }

        .titulo-boleta {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            color: #0E2650;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .seccion { margin-bottom: 4px; }

        /* ── Las cuatro categorías ───────────────────────────────────────
           Ingresos, Descuentos, Aportaciones y Adelanto llevaban cada una un
           color saturado distinto (azul, rojo, verde y dorado), y el
           documento parecía un semáforo. Ahora comparten el azul
           institucional y se distinguen por lo que de verdad las separa: su
           título y el marco que encierra su tabla.

           Los colores van literales y no con variables CSS porque esto lo
           renderiza dompdf, que no las soporta. Son los mismos de
           styles.scss: #1B4282 es --brand-700, #0E2650 --brand-900 y
           #E7EEF9 --brand-100. */
        .seccion-titulo {
            background: #1B4282;
            color: white;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        /* El marco que encierra cada categoría: es lo que las separa a la
           vista ahora que todas comparten color. */
        .seccion table {
            border: 1px solid #1B4282;
            border-top: none;
        }

        table td {
            padding: 3px 6px;
            border: 1px solid #C9D2E3;
            font-size: 10px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        table td.monto { text-align: right; }

        /* La columna de etiquetas, en azul muy claro: en la ficha del
           trabajador distingue de un vistazo el rótulo del dato. */
        table td.label {
            background: #E7EEF9;
            font-weight: bold;
            color: #14325F;
        }

        .fila-total td {
            background: #0E2650;
            color: white;
            font-weight: bold;
            font-size: 11px;
        }

        /* El "TOTAL NETO" salía azul oscuro sobre azul oscuro, ilegible, en
           la fila más importante del documento.

           La culpa es de la especificidad: `table td.label` vale (0,1,2) y
           `.fila-total td` solo (0,1,1), así que la celda de la izquierda se
           quedaba con el color de las etiquetas por más que la fila pidiera
           blanco. Esta regla nombra las dos celdas y gana a ambas. No la
           colapses con la de arriba: el selector tiene que seguir mencionando
           `td.label` para poder ganarle. */
        table tr.fila-total td,
        table tr.fila-total td.label {
            background: #0E2650;
            color: #FFFFFF;
        }

        /* El total va sin título encima —lo decía dos veces: "Total Neto a
           Pagar" y, debajo, "TOTAL NETO"—, así que la franja necesita su
           propio borde superior: `.seccion table` lo quita porque en las
           demás secciones hace de tapa el título. */
        .seccion--total table {
            border-top: 1px solid #1B4282;
        }

        .fila-subtotal td {
            background: #E7EEF9;
            font-weight: bold;
            color: #14325F;
        }

        /* ── Densidad: la boleta se aprieta segun cuantos conceptos lleve ──
           Solo se tocan el relleno de las filas y el cuerpo de letra; los
           títulos, los totales y la cabecera se quedan como están para que
           la boleta siga leyéndose igual de bien. */
        .densidad-ajustada table td      { padding: 2px 6px; font-size: 9.5px; }
        .densidad-apretada table td      { padding: 1.5px 5px; font-size: 9px; line-height: 1.2; }
        .densidad-muy-apretada table td  { padding: 1px 5px; font-size: 8.5px; line-height: 1.15; }

        .densidad-apretada .seccion-titulo,
        .densidad-muy-apretada .seccion-titulo { padding: 3px 8px; font-size: 9.5px; }

        .densidad-muy-apretada .encabezado-logo img { width: 52px; height: 52px; }

        /* Si aun así no cupiera, que al menos no se corte a mitad de una
           sección ni deje las firmas huérfanas en una página sola. */
        .seccion, .fila-columnas { page-break-inside: avoid; }

        .nota {
            font-size: 8px;
            color: #888;
            margin-top: 3px;
            font-style: italic;
        }

        /* ── Layout de 2 columnas lado a lado (Ingresos | Descuentos, y Aportaciones | Adelanto) ── */
        .fila-columnas {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-bottom: 4px;
        }

        .columna {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 6px;
        }

        .columna:last-child {
            padding-right: 0;
            padding-left: 6px;
        }

        .firma-section {
            margin-top: 6px;
            display: table;
            width: 100%;
            page-break-inside: avoid;
            page-break-before: avoid;
        }

        .fecha-lugar {
            text-align: right;
            font-size: 9px;
            color: #555;
            margin-top: 10px;
            margin-bottom: 4px;
        }

        .firma-box {
            display: table-cell;
            width: 48%;
            text-align: center;
            padding-top: 10px;
            border-top: 1px solid #0E2650;
            font-size: 9px;
            color: #555;
        }

        .copia-label {
            text-align: center;
            font-size: 9px;
            color: #1B4282;
            margin-bottom: 6px;
            font-style: italic;
            font-weight: bold;
        }

        .firma-digital {
            margin-top: 8px;
            padding: 6px 8px;
            background: #E7EEF9;
            border: 1px solid #1B4282;
            font-size: 9px;
            color: #14325F;
        }
    </style>
</head>
<body>

@php
    $totalConceptosIngreso = $conceptosIngreso->sum('monto_calculado');

    /* Ya no se suman `planilla->bonificaciones` ni `planilla->descuentos`.
       Eran los dos montos sueltos que se escribían a mano cuando existía la
       edición directa de la planilla; ahora todo lo que mueve dinero es un
       concepto de pago con nombre, así que esas dos columnas quedan siempre
       en cero y sumarlas solo escondía de dónde salía la plata. */
    /* La gratificación ya no se suma aparte: en julio y diciembre es una línea
       de la planilla (la crea CalculaConceptosPlanilla), así que entra por
       $totalConceptosIngreso. Sumarla además dejaba la boleta diciendo más
       de lo que la planilla paga. */
    $totalIngresos = (float)$planilla->sueldo_base
        + $asignacionFamiliar
        + $totalConceptosIngreso;

    $totalConceptosDescuento  = $conceptosDescuento->sum('monto_calculado');
    $totalDescuentos = $pension['total'] + $renta5ta + $totalConceptosDescuento;

    $totalConceptosAportacion = $conceptosAportacion->sum('monto_calculado');
    $totalAportes = $essalud + $totalConceptosAportacion;

    $totalAdelantos = $conceptosAdelanto->sum('monto_calculado');

    // Total Neto = Ingresos - Descuentos - Adelantos (los Aportes del empleador NO se restan,
    // son informativos — verificado contra la boleta física del colegio).
    $totalNeto = $totalIngresos - $totalDescuentos - $totalAdelantos;

    /*
     * Las filas de la boleta, SIEMPRE las mismas y en el mismo orden que la
     * boleta física del colegio (postman/guia_boleta.jpeg): con un "-" cuando
     * el monto es cero. Antes solo salían los conceptos que la planilla
     * tenía, así que dos boletas no se parecían entre sí y la Renta de 5ta
     * desaparecía cuando era cero.
     *
     * Cada fila es [lo que dice la boleta, el monto]. Un concepto que la
     * planilla tenga y no esté en la lista fija (uno nuevo del catálogo) se
     * agrega al final de su columna: nada se pierde de vista.
     */
    $lineas  = $conceptosIngreso->concat($conceptosDescuento)->concat($conceptosAportacion)->concat($conceptosAdelanto);
    $montoDe = fn (string $nombre) => (float) $lineas->filter(fn ($d) => $d->paymentConcept?->nombre === $nombre)->sum('monto_calculado');
    $pensionDe = function (string $nombre) use ($pension) {
        $fila = collect($pension['detalle'])->firstWhere('concepto', $nombre);
        return $fila ? (float) $fila['monto'] : 0.0;
    };
    $C = \App\Support\ConceptosDePago::class;
    // Las tasas de ONP y EsSalud de la etiqueta son las del año de la boleta: "13" y no "13.00".
    $ley  = \App\Models\ValorLegal::delAnio((int) $anio);
    $tasa = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

    $filasIngreso = [
        ['Remuneración Básica', (float) $planilla->sueldo_base],
        ['Bonificación por Cargo', $montoDe($C::BONIFICACION_CARGO)],
        ['Asignación Familiar', (float) $asignacionFamiliar],
        ['Vacaciones Truncas', $montoDe($C::VACACIONES_TRUNCAS)],
        ['Gratificaciones Fiestas Patrias - Ley 29351 y 30334', $montoDe($C::GRATIFICACION)],
        ['Bonif. Extraord. Temporal - Ley 29351 y 30334', $montoDe($C::BONIF_EXTRAORDINARIA)],
        ['Otros Conceptos - Subsidio de Maternidad', $montoDe($C::OTROS_INGRESOS)],
        ['Bonificación', $montoDe('Bonificaciones')],
        ['Compensación por Tiempo de Servicios', $montoDe('Compensación por Tiempo de Servicios')],
    ];
    $filasDescuento = [
        ['ONP ' . $tasa($ley->onp) . '%', $pensionDe($C::ONP)],
        ['SPP: Fondo Pensiones', $pensionDe($C::SPP_FONDO)],
        ['SPP: Prima de Seguro', $pensionDe($C::SPP_PRIMA_SEGURO)],
        ['SPP: Comisión', $pensionDe($C::SPP_COMISION)],
        ['I.R. 5ta Categoría', (float) $renta5ta],
        ['Descuento Serv. Alimentación', $montoDe('Descuento Serv. Alimentación')],
        ['Descuento Serv. de Bazar', $montoDe('Descuento Serv. Bazar')],
        ['Descuento Autorizado - Diezmo', $montoDe($C::DIEZMO)],
        ['Descuento Otros Conceptos', $montoDe($C::OTROS_DESCUENTOS)],
        ['Descuento - Pago Escolaridad Mensual', $montoDe('Descuento - Pago de Escolaridad Mensual')],
    ];
    $filasAporte = [
        ['ESSALUD ' . $tasa($ley->essalud) . '%', (float) $essalud],
        ['SCTR', $montoDe('SCTR')],
    ];
    $filasAdelanto = [
        ['Adelanto de Sueldo', $montoDe('Adelanto de Sueldo')],
        ['Adelanto de Bonificación', $montoDe('Adelanto de Bonificaciones')],
    ];

    // Los conceptos de la planilla que no tienen fila fija: van al final de su columna.
    $fijos = [
        $C::BONIFICACION_CARGO, $C::VACACIONES_TRUNCAS, $C::GRATIFICACION, $C::BONIF_EXTRAORDINARIA,
        $C::OTROS_INGRESOS, 'Bonificaciones', 'Compensación por Tiempo de Servicios',
        'Descuento Serv. Alimentación', 'Descuento Serv. Bazar', $C::DIEZMO, $C::OTROS_DESCUENTOS,
        'Descuento - Pago de Escolaridad Mensual', 'SCTR', 'Adelanto de Sueldo', 'Adelanto de Bonificaciones',
    ];
    $extras = fn ($coleccion) => $coleccion
        ->reject(fn ($d) => in_array($d->paymentConcept?->nombre, $fijos, true))
        ->groupBy(fn ($d) => $d->etiqueta)
        ->map(fn ($grupo, $etiqueta) => [$etiqueta, (float) $grupo->sum('monto_calculado')])
        ->values()->all();

    $filasIngreso   = array_merge($filasIngreso, $extras($conceptosIngreso));
    $filasDescuento = array_merge($filasDescuento, $extras($conceptosDescuento));
    $filasAporte    = array_merge($filasAporte, $extras($conceptosAportacion));
    $filasAdelanto  = array_merge($filasAdelanto, $extras($conceptosAdelanto));

    // Como en la boleta física: el monto sin símbolo, y un guion cuando es cero.
    $monto = fn (float $v) => abs($v) < 0.005 ? '-' : number_format($v, 2);

    // Datos institucionales fijos (no hay un módulo de "datos del colegio" en el sistema).
    $colegioRuc = '20156630733';
    $colegioDireccion = 'Jr. Moquegua N° 852';
    $ciudadFirma = 'Juliaca';
@endphp

{{--
    Cuántas copias van en el PDF. Dos para la institución (una la firma y se
    queda el trabajador, la otra el colegio); una sola cuando el propio
    trabajador se descarga la suya desde Mis Boletas, que no necesita la copia
    del colegio y solo le hacía imprimir el doble.
--}}
@php $copias = $copias ?? 2; @endphp

@php
    /*
     * Cuántas líneas de concepto lleva esta boleta.
     *
     * Con el diseño de siempre caben catorce; en la quince se partía en dos
     * páginas, y la segunda salía SIN cabecera, sin el nombre del trabajador
     * y con el marco abierto: impresa era medio documento suelto que no se
     * sabía de quién era.
     *
     * Así que la boleta se aprieta sola: cuantas más líneas lleve, más
     * ajustados van el interlineado y la letra, para que quepa entera. Es
     * preferible a partirla, porque una boleta es UN documento y se firma
     * una vez.
     */
    // Lo que manda la altura es la columna más larga de cada fila de bloques,
    // no la suma: ahora todas las filas fijas salen siempre (9 y 10 arriba,
    // 2 y 2 abajo), así que lo que varía son los conceptos extra.
    $lineasDeConcepto = max(count($filasIngreso), count($filasDescuento))
        + max(count($filasAporte), count($filasAdelanto));

    $densidad = match (true) {
        $lineasDeConcepto <= 12 => 'holgada',
        $lineasDeConcepto <= 16 => 'ajustada',
        $lineasDeConcepto <= 22 => 'apretada',
        default                 => 'muy-apretada',
    };
@endphp

@for ($copia = 1; $copia <= $copias; $copia++)
<div class="boleta densidad-{{ $densidad }}">
    <div class="copia-label" @if ($copias === 1) style="visibility: hidden;" @endif>
        @if ($copia == 1)
            -- COPIA TRABAJADOR --
        @else
            -- COPIA INSTITUCIÓN --
        @endif
    </div>

    <div class="encabezado">
        <div class="encabezado-logo">
            <img src="{{ public_path('logo.png') }}">
        </div>
        <div class="encabezado-texto">
            {{-- La denominación oficial del año, como en todo documento del Estado. Se edita en Ajustes. --}}
            @if (!empty($nombre_anio))
                <p class="nombre-anio">"{{ $nombre_anio }}"</p>
            @endif
            <h1>Asociación Educativa Colegio Adventista Túpac Amaru</h1>
            <p class="ruc">RUC: {{ $colegioRuc }} — {{ $colegioDireccion }}</p>
            <h2>Boleta de Pago de Remuneraciones — {{ $mes_nombre }} {{ $anio }}</h2>
            <p>Documento generado el {{ now()->format('d/m/Y') }} — N° Boleta: {{ $numero_boleta }}</p>
            <p>Del {{ $cabecera['rango_inicio'] }} al {{ $cabecera['rango_fin'] }} (expresado en Soles)</p>
        </div>
    </div>

    <div class="titulo-boleta">Boleta de Remuneraciones</div>

    <div class="seccion">
        <div class="seccion-titulo">Datos del Trabajador</div>
        <table>
            <tr>
                <td class="label">Empleado</td>
                <td>{{ $empleado->apellido }}, {{ $empleado->nombre }}</td>
                <td class="label">DNI</td>
                <td>{{ $empleado->dni }}</td>
                <td class="label">Categoría</td>
                <td>{{ $cabecera['categoria'] }}</td>
            </tr>
            <tr>
                <td class="label">Cargo</td>
                <td>{{ $empleado->cargo->nombre ?? 'Sin asignar' }}</td>
                <td class="label">Área</td>
                <td>{{ $empleado->area->nombre ?? 'Sin asignar' }}</td>
                <td class="label">Sede</td>
                <td>{{ $empleado->sede->nombre ?? 'Sin asignar' }}</td>
            </tr>
            <tr>
                <td class="label">Fecha de Ingreso</td>
                <td>{{ \Carbon\Carbon::parse($empleado->fecha_ingreso)->format('d/m/Y') }}</td>
                <td class="label">Días Trabajados</td>
                <td>{{ $cabecera['dias_trabajados'] }}</td>
                {{-- Se llama por lo que es. "Días no trabajados" hacía pensar
                     al docente que eran días sin pagar, y las vacaciones se
                     pagan igual que un día de trabajo. --}}
                <td class="label">Días de Vacaciones</td>
                <td>{{ $cabecera['dias_vacaciones'] }}</td>
            </tr>
            <tr>
                <td class="label">Estado</td>
                <td>{{ ucfirst($empleado->estado) }}</td>
                <td class="label">Fecha de Cese</td>
                <td>{{ $cabecera['fecha_cese'] ? \Carbon\Carbon::parse($cabecera['fecha_cese'])->format('d/m/Y') : '-' }}</td>
                <td class="label">Sistema de Pensión</td>
                <td>{{ $pension['tipo'] }}</td>
            </tr>
            <tr>
                <td class="label">CUSPP</td>
                <td>{{ $empleado->cuspp ?? '-' }}</td>
                <td class="label">Entidad Financiera</td>
                <td>{{ $empleado->entidad_financiera ?? '-' }}</td>
                <td class="label">N° de Cuenta</td>
                <td>{{ $empleado->numero_cuenta ?? '-' }}</td>
            </tr>
            {{-- Solo si lo tiene. Es opcional y la mayoría no lo registra:
                 una fila fija con un guion sería ruido en un documento que
                 se archiva firmado. --}}
            @if ($empleado->cci)
            <tr>
                <td class="label">CCI</td>
                <td colspan="5">{{ $empleado->cci }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="fila-columnas">
        <div class="columna">
            <div class="seccion-titulo">Ingresos</div>
            <table>
                @foreach ($filasIngreso as [$etiqueta, $valor])
                <tr>
                    <td class="label">{{ $etiqueta }}</td>
                    <td class="monto">{{ $monto($valor) }}</td>
                </tr>
                @endforeach
                <tr class="fila-subtotal">
                    <td class="label">Total Ingresos</td>
                    <td class="monto">{{ number_format($totalIngresos, 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="columna">
            <div class="seccion-titulo">Descuentos</div>
            <table>
                @foreach ($filasDescuento as [$etiqueta, $valor])
                <tr>
                    <td class="label">{{ $etiqueta }}</td>
                    <td class="monto">{{ $monto($valor) }}</td>
                </tr>
                @endforeach
                <tr class="fila-subtotal">
                    <td class="label">Total Descuentos</td>
                    <td class="monto">{{ number_format($totalDescuentos, 2) }}</td>
                </tr>
            </table>
            @if ($pension['total'] > 0)
            <p class="nota">El descuento por {{ $pension['tipo'] }} se calcula sobre la remuneración básica según tasas vigentes {{ $anio }}.</p>
            @else
            {{-- Sin sistema de pensiones no hay nada que explicar, y la nota
                 quedaba como "El descuento por No aporta se calcula...". --}}
            <p class="nota">No se aplica descuento de pensión: el trabajador no aporta a ningún sistema.</p>
            @endif
        </div>
    </div>

    <div class="fila-columnas">
        <div class="columna">
            <div class="seccion-titulo">Aportaciones del Empleador (Informativo)</div>
            <table>
                @foreach ($filasAporte as [$etiqueta, $valor])
                <tr>
                    <td class="label">{{ $etiqueta }}</td>
                    <td class="monto">{{ $monto($valor) }}</td>
                </tr>
                @endforeach
                <tr class="fila-subtotal">
                    <td class="label">Total Aportes</td>
                    <td class="monto">{{ number_format($totalAportes, 2) }}</td>
                </tr>
            </table>
            <p class="nota">Este monto es asumido íntegramente por el empleador y no afecta el sueldo neto del trabajador.</p>
        </div>

        {{-- Siempre, como en la boleta física: "-" si no hubo adelanto. --}}
        <div class="columna">
            <div class="seccion-titulo">Adelanto</div>
            <table>
                @foreach ($filasAdelanto as [$etiqueta, $valor])
                <tr>
                    <td class="label">{{ $etiqueta }}</td>
                    <td class="monto">{{ $monto($valor) }}</td>
                </tr>
                @endforeach
                <tr class="fila-subtotal">
                    <td class="label">Total Adelanto</td>
                    <td class="monto">{{ number_format($totalAdelantos, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="seccion seccion--total">
        <table>
            <tr class="fila-total">
                <td class="label">TOTAL NETO A PAGAR</td>
                <td class="monto">S/ {{ number_format($totalNeto, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="fecha-lugar">
        {{ $ciudadFirma }}, {{ now()->translatedFormat('d \d\e F \d\e Y') }}
    </div>

    <div class="firma-section">
        <div class="firma-box" style="margin-right:4%;">
            @if (isset($documento) && $documento->estado_firma_empleador === 'firmado' && $documento->empleador?->identidadFirma?->firma_imagen)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('local')->path($documento->empleador->identidadFirma->firma_imagen) }}" style="height:50px; margin-bottom:5px;"><br>
            @endif
            @if (isset($documento) && $documento->estado_firma_empleador === 'firmado' && $documento->empleador?->identidadFirma?->huella_imagen)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('local')->path($documento->empleador->identidadFirma->huella_imagen) }}" style="height:35px; margin-left:10px;">
            @endif
            <div style="border-top:1px solid #0E2650; padding-top:5px; margin-top:5px;">
                Firma Empleador<br>
                Colegio Adventista Túpac Amaru
            </div>
        </div>
        <div class="firma-box">
            @if (isset($documento) && $documento->estado_firma === 'firmado' && $empleado->identidadFirma?->firma_imagen)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('local')->path($empleado->identidadFirma->firma_imagen) }}" style="height:50px; margin-bottom:5px;"><br>
            @endif
            @if (isset($documento) && $documento->estado_firma === 'firmado' && $empleado->identidadFirma?->huella_imagen)
                {{-- Igual que en un documento físico peruano, la huella va junto a la firma. --}}
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('local')->path($empleado->identidadFirma->huella_imagen) }}" style="height:35px; margin-left:10px;">
            @endif
            <div style="border-top:1px solid #0E2650; padding-top:5px; margin-top:5px;">
                Firma del Trabajador<br>
                {{ $empleado->apellido }}, {{ $empleado->nombre }}
            </div>
        </div>
    </div>

    @if (isset($documento) && $documento->estado_firma_empleador === 'firmado')
    <div class="firma-digital">
        Firmado por el empleador: {{ $documento->firmado_por_empleador }}
        el {{ \Carbon\Carbon::parse($documento->fecha_firma_empleador)->format('d/m/Y H:i') }} —
        Código de verificación: {{ $documento->codigo_firma_empleador }}
    </div>
    @endif

    @if (isset($documento) && $documento->estado_firma === 'firmado')
    <div class="firma-digital">
        Documento firmado digitalmente por {{ $documento->firmado_por }}
        el {{ \Carbon\Carbon::parse($documento->fecha_firma)->format('d/m/Y H:i') }} —
        Código de verificación: {{ $documento->codigo_firma }}
    </div>
    @endif

</div>
@endfor

</body>
</html>
