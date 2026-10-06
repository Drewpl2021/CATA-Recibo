<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    {{--
        La boleta, con el diseño de la boleta de pago del Minedu
        (postman/plame/BOLETA DEGUIA.pdf): hoja vertical, datos personales en
        cuadrícula de tres columnas, Ingresos y Descuentos lado a lado sobre
        un panel gris azulado, el total líquido, el QR para verificarla, el
        mensaje y la nota del medio ambiente. Con la cabecera, los datos y
        las cuatro categorías (también Aportaciones y Adelantos) del colegio.

        Lo dibuja dompdf: nada de flex ni de variables CSS, todo con tablas y
        colores literales. Cada copia es UNA hoja.
    --}}
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        /* El margen de la hoja va en html: dompdf lo toma de ahí, y el "* { margin: 0 }" de arriba se comía el de @page. */
        html { margin: 12mm 14mm 10mm 14mm; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8.5px;
            color: #1F2B33;
        }

        .hoja + .hoja { page-break-before: always; }
        .hoja { position: relative; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td { vertical-align: top; }

        /* ── Cabecera ── */
        .cab td { vertical-align: bottom; }
        .cab__logo img { width: 50px; height: 50px; }
        .cab__inst { font-size: 7px; font-weight: bold; color: #1F2B33; line-height: 1.25; padding-left: 6px; vertical-align: middle; }
        .cab__inst span { font-weight: normal; color: #4A5961; }
        .cab__codigo-rotulo { font-size: 5.5px; color: #4A5961; text-transform: uppercase; margin-top: 4px; }
        .cab__codigo { font-size: 10px; color: #1F2B33; }
        .cab__titulo { text-align: center; font-size: 15px; font-weight: bold; color: #1F2B33; padding-bottom: 2px; }
        .cab__copia { text-align: center; font-size: 6.5px; color: #6B7A82; letter-spacing: 0.5px; margin-top: 2px; }
        .cab__lugar { text-align: right; font-size: 8.5px; color: #1F2B33; line-height: 1.3; }
        .cab__lugar strong { font-size: 9.5px; }
        .cab__periodo { text-align: right; font-size: 15px; font-weight: bold; color: #EC7A3A; padding-top: 3px; }
        .cab__nombre-anio { text-align: center; font-size: 6.5px; font-style: italic; color: #4A5961; margin-bottom: 4px; }

        /* ── Barras de sección (gris azulado, como la guía) ── */
        .barra { background: #D3E3E8; font-weight: bold; font-size: 8px; text-transform: uppercase; padding: 4px 4px; color: #1F2B33; }
        .barra td { padding: 0; }
        .barra .der { text-align: right; }

        /* ── Datos personales: cuadrícula de tres columnas con raya debajo ── */
        .datos { position: relative; margin-top: 4px; }
        .datos td { padding: 3px 3px 3px; border-bottom: 1.2px solid #BFD3DA; }
        .datos tr:last-child td { border-bottom: none; }
        .rotulo { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #1F2B33; }
        .valor { font-size: 8px; color: #2E3B42; margin-top: 1px; }
        .valor--grande { font-size: 11.5px; color: #1F2B33; }

        /* La marca de agua: el logo "CATA" (public/marca-agua.png, recortado de
           public/fondo_agua.jpeg y con el fondo transparente), apenas visible
           y centrado sobre los datos personales, como el de la guía. */
        .marca-agua { position: absolute; top: 58px; left: 190px; width: 370px; opacity: 0.08; }

        /* ── Paneles de conceptos ── */
        .paneles { margin-top: 6px; }
        .paneles > tbody > tr > td.panel { background: #EDF3F5; padding: 0; }
        .paneles td.hueco { background: #FFFFFF; }
        .panel__titulo { background: #D3E3E8; font-weight: bold; font-size: 7.5px; text-transform: uppercase; padding: 4px 5px; margin: 4px 4px 0; }
        .conceptos { margin: 2px 0 0; }
        .conceptos td { padding: 2.6px 6px; font-size: 7.4px; color: #2E3B42; }
        .conceptos tr.encabezado td { font-size: 6.5px; font-weight: bold; color: #1F2B33; text-transform: uppercase; padding-top: 4px; padding-bottom: 4px; }
        .conceptos td.monto { text-align: right; white-space: nowrap; width: 26%; }
        .conceptos tr.cero td { color: #8A989F; }
        .conceptos tr.otro-concepto td.concepto { padding-left: 12px; font-style: italic; }
        .total-panel td { background: #FFFFFF; padding: 5px 6px; font-size: 7.5px; font-weight: bold; border-top: 2px solid #FFFFFF; }
        .total-panel td.monto { text-align: right; font-weight: normal; font-size: 7.5px; }
        .nota { font-size: 6px; color: #6B7A82; font-style: italic; padding: 2px 6px 5px; }

        /* Densidad: si una boleta trae muchos conceptos, las filas se aprietan para que siga cabiendo en una hoja. */
        .densidad-apretada .conceptos td, .densidad-muy-apretada .conceptos td { padding-top: 1.5px; padding-bottom: 1.5px; }
        .densidad-muy-apretada .conceptos td { font-size: 6.3px; }

        /* ── Pie: QR a la izquierda, totales y mensajes a la derecha ── */
        .pie { margin-top: 8px; }
        .qr { text-align: center; }
        .qr__marco { border: 1px solid #1F2B33; padding: 5px; display: inline-block; }
        .qr__marco img { width: 112px; height: 112px; }
        .qr__texto { font-size: 6.4px; color: #2E3B42; line-height: 1.35; margin: 5px auto 0; width: 150px; }
        .totales td { padding: 0; }
        .total-caja { background: #D3E3E8; padding: 5px 6px; font-size: 7.5px; font-weight: bold; text-transform: uppercase; }
        .total-caja td { vertical-align: middle; }
        .total-caja .cifra { text-align: right; font-size: 10px; font-weight: normal; text-transform: none; }
        .mensaje-barra { background: #D3E3E8; font-weight: bold; font-size: 7.5px; text-transform: uppercase; padding: 4px 6px; margin-top: 5px; }
        .mensaje { font-size: 7.6px; color: #2E3B42; padding: 5px 6px 2px; line-height: 1.45; }
        .eco { margin-top: 10px; border-top: 1px solid #BFD3DA; border-bottom: 1px solid #BFD3DA; }
        .eco td { vertical-align: middle; padding: 7px 4px; }
        .eco img { width: 34px; height: 24px; }
        .eco__texto { font-size: 7.6px; font-style: italic; color: #2E3B42; line-height: 1.4; }

        /* ── Firmas ── */
        .firmas { margin-top: 34px; }
        .firmas td { text-align: center; vertical-align: bottom; font-size: 7.6px; color: #2E3B42; }
        .firmas .linea { border-top: 1px solid #1F2B33; padding-top: 3px; margin: 0 22px; }
        .firmas img.firma { height: 38px; }
        .firmas img.huella { height: 28px; margin-left: 6px; }
        .firma-digital { margin-top: 6px; font-size: 6.3px; color: #2E3B42; background: #EDF3F5; padding: 4px 6px; }
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
    // "Otros Conceptos" lleva el detalle que se le escribió a la línea, como
    // en la boleta física: "Descuento Otros Conceptos: Cobro de corbata".
    $otros = function (string $nombre, string $etiqueta) use ($lineas) {
        $detalle = $lineas->first(fn ($d) => $d->paymentConcept?->nombre === $nombre && $d->descripcion)?->descripcion;
        return $detalle ? "{$etiqueta}: {$detalle}" : $etiqueta;
    };

    $filasIngreso = [
        ['Remuneración Básica', (float) $planilla->sueldo_base],
        ['Bonificación por Cargo', $montoDe($C::BONIFICACION_CARGO)],
        ['Asignación Familiar', (float) $asignacionFamiliar],
        ['Vacaciones Truncas', $montoDe($C::VACACIONES_TRUNCAS)],
        ['Gratificaciones Fiestas Patrias - Ley 29351 y 30334', $montoDe($C::GRATIFICACION)],
        ['Bonif. Extraord. Temporal - Ley 29351 y 30334', $montoDe($C::BONIF_EXTRAORDINARIA)],
        [$otros($C::OTROS_INGRESOS, 'Otros Conceptos - Subsidio de Maternidad'), $montoDe($C::OTROS_INGRESOS)],
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
        [$otros($C::OTROS_DESCUENTOS, 'Descuento Otros Conceptos'), $montoDe($C::OTROS_DESCUENTOS)],
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
    // Cada concepto agregado (a uno, a un grupo o por Excel) sale con su
    // nombre, marcado como "otro concepto" (la tercera posición es la clase).
    $extras = fn ($coleccion) => $coleccion
        ->reject(fn ($d) => in_array($d->paymentConcept?->nombre, $fijos, true))
        ->groupBy(fn ($d) => $d->etiqueta)
        ->map(fn ($grupo, $etiqueta) => [$etiqueta, (float) $grupo->sum('monto_calculado'), 'otro-concepto'])
        ->values()->all();
    // En Ingresos y Descuentos van justo debajo de su fila "Otros Conceptos";
    // en Aportes y Adelantos, que no la tienen, al final.
    $debajoDe = function (array $filas, int $posicion, array $nuevas) {
        array_splice($filas, $posicion + 1, 0, $nuevas);
        return $filas;
    };

    $filasIngreso   = $debajoDe($filasIngreso, 6, $extras($conceptosIngreso));
    $filasDescuento = $debajoDe($filasDescuento, 8, $extras($conceptosDescuento));
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

@php
    /* ── Lo que el diseño nuevo añade a los cálculos de arriba ── */

    // "S/ 4,200.84" como en la guía; un guion cuando es cero.
    $soles = fn (float $v) => abs($v) < 0.005 ? '-' : 'S/ ' . number_format($v, 2);

    // Afecto a cargas sociales: la base sobre la que se calculan AFP/ONP,
    // EsSalud y el diezmo (sueldo + asignación + bonificación por cargo +
    // vacaciones truncas). La gratificación no entra (Ley 29351/30334).
    $afectoCargas = (float) $planilla->sueldo_base + (float) $asignacionFamiliar
        + $montoDe($C::BONIFICACION_CARGO) + $montoDe($C::VACACIONES_TRUNCAS);

    // El QR: la dirección firmada que abre la verificación de esta boleta.
    $qr = (isset($documento) && $documento?->id)
        ? \App\Support\CodigoQr::png($documento->urlDeVerificacion())
        : null;

    $fecha   = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '---';
    $termino = $empleado->fecha_cese ? $fecha($empleado->fecha_cese) : 'Indeterminado';
    $sistema = rtrim(config('app.frontend_url'), '/');

    // La nota del medio ambiente: tres árboles de línea, como en la guía.
    $arboles = 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="68" height="48" viewBox="0 0 68 48" fill="none" stroke="#5B6970" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'
        . '<path d="M14 44V30M14 36l-4-4M14 34l4-4"/><path d="M14 30c-7 0-10-5-8-10 0-6 5-10 8-10s8 4 8 10c2 5-1 10-8 10z"/>'
        . '<path d="M34 44V26M34 33l-5-5M34 31l5-5"/><path d="M34 26c-8 0-12-6-9-12 0-7 6-12 9-12s9 5 9 12c3 6-1 12-9 12z"/>'
        . '<path d="M54 44V30M54 36l-4-4M54 34l4-4"/><path d="M54 30c-7 0-10-5-8-10 0-6 5-10 8-10s8 4 8 10c2 5-1 10-8 10z"/>'
        . '<path d="M4 44h60"/></svg>'
    );
@endphp

@for ($copia = 1; $copia <= $copias; $copia++)
<div class="hoja densidad-{{ $densidad }}">

    {{-- La denominación oficial del año. Se edita en Ajustes. --}}
    @if (!empty($nombre_anio))
        <p class="cab__nombre-anio">"{{ $nombre_anio }}"</p>
    @endif

    {{-- ── Cabecera: colegio y N° de boleta | título | lugar, RUC y mes ── --}}
    <table class="cab">
        <tr>
            <td style="width: 37%;">
                <table>
                    <tr>
                        <td class="cab__logo" style="width: 24%;"><img src="{{ public_path('logo.png') }}"></td>
                        <td class="cab__inst" style="width: 76%;">ASOCIACIÓN EDUCATIVA<br>COLEGIO ADVENTISTA<br>TÚPAC AMARU</td>
                    </tr>
                </table>
                <div class="cab__codigo-rotulo">N° de boleta</div>
                <div class="cab__codigo">{{ $numero_boleta }}</div>
            </td>
            <td style="width: 28%;">
                <div class="cab__titulo">BOLETA DE PAGO</div>
                @if ($copias > 1)
                    <div class="cab__copia">{{ $copia == 1 ? 'COPIA TRABAJADOR' : 'COPIA INSTITUCIÓN' }}</div>
                @endif
            </td>
            <td style="width: 35%;">
                <div class="cab__lugar">
                    <strong>JULIACA</strong><br>
                    {{ $colegioDireccion }}<br>
                    RUC {{ $colegioRuc }}
                </div>
                <div class="cab__periodo">{{ mb_strtoupper($mes_nombre) }} - {{ $anio }}</div>
            </td>
        </tr>
    </table>

    {{-- ── Datos personales ── --}}
    <table class="barra" style="margin-top: 5px;">
        <tr>
            <td>Datos personales</td>
            <td class="der">Estado &nbsp;{{ mb_strtoupper($empleado->estado) }}</td>
        </tr>
    </table>

    <div class="datos">
        <img class="marca-agua" src="{{ public_path('marca-agua.png') }}">
        <table>
            <tr>
                <td style="width: 34%;"><div class="rotulo">Apellidos</div><div class="valor valor--grande">{{ $empleado->apellido }}</div></td>
                <td style="width: 34%;"><div class="rotulo">Nombres</div><div class="valor valor--grande">{{ $empleado->nombre }}</div></td>
                <td style="width: 32%;"><div class="rotulo">D.N.I</div><div class="valor valor--grande">{{ $empleado->dni }}</div></td>
            </tr>
            <tr>
                <td><div class="rotulo">Centro de trabajo</div><div class="valor">{{ $empleado->sede->nombre ?? '---' }}</div></td>
                <td><div class="rotulo">Cargo</div><div class="valor">{{ $empleado->cargo->nombre ?? '---' }}</div></td>
                <td><div class="rotulo">Área</div><div class="valor">{{ $empleado->area->nombre ?? '---' }}</div></td>
            </tr>
            <tr>
                <td><div class="rotulo">Régimen laboral</div><div class="valor">{{ $cabecera['categoria'] }}</div></td>
                <td><div class="rotulo">Vínculo laboral</div><div class="valor">INGRESO: {{ $fecha($empleado->fecha_ingreso) }} &nbsp;&nbsp; TÉRMINO: {{ $termino }}</div></td>
                <td><div class="rotulo">Entidad bancaria</div><div class="valor">{{ $empleado->entidad_financiera ?: '---' }}</div></td>
            </tr>
            <tr>
                <td><div class="rotulo">N° de cuenta</div><div class="valor">{{ $empleado->numero_cuenta ?: '---' }}</div></td>
                <td><div class="rotulo">CCI</div><div class="valor">{{ $empleado->cci ?: '---' }}</div></td>
                <td><div class="rotulo">Régimen pensionario</div><div class="valor">{{ $pension['tipo'] }}</div></td>
            </tr>
            <tr>
                <td><div class="rotulo">CUSPP</div><div class="valor">{{ $empleado->cuspp ?: '---' }}</div></td>
                <td><div class="rotulo">Periodo de pago</div><div class="valor">Del {{ $cabecera['rango_inicio'] }} al {{ $cabecera['rango_fin'] }}</div></td>
                <td><div class="rotulo">Fecha de cese</div><div class="valor">{{ $cabecera['fecha_cese'] ? $fecha($cabecera['fecha_cese']) : '---' }}</div></td>
            </tr>
            <tr>
                <td><div class="rotulo">Días laborados</div><div class="valor">{{ $cabecera['dias_trabajados'] }}</div></td>
                <td><div class="rotulo">Días de vacaciones</div><div class="valor">{{ $cabecera['dias_vacaciones'] }}</div></td>
                <td><div class="rotulo">Fecha de emisión</div><div class="valor">{{ now()->format('d/m/Y') }}</div></td>
            </tr>
        </table>
    </div>

    {{-- ── Ingresos | Descuentos ── --}}
    <table class="paneles">
        <tr>
            <td class="panel" style="width: 49.4%; height: 235px;">
                <div class="panel__titulo">Ingresos</div>
                <table class="conceptos">
                    <tr class="encabezado"><td class="concepto">Concepto</td><td class="monto">Monto</td></tr>
                    @foreach ($filasIngreso as $fila)
                        <tr class="{{ trim(($fila[2] ?? '') . (abs($fila[1]) < 0.005 ? ' cero' : '')) }}">
                            <td class="concepto">{{ $fila[0] }}</td><td class="monto">{{ $soles($fila[1]) }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
            <td class="hueco" style="width: 1.2%;"></td>
            <td class="panel" style="width: 49.4%; height: 235px;">
                <div class="panel__titulo">Descuentos</div>
                <table class="conceptos">
                    <tr class="encabezado"><td class="concepto">Concepto</td><td class="monto">Monto</td></tr>
                    @foreach ($filasDescuento as $fila)
                        <tr class="{{ trim(($fila[2] ?? '') . (abs($fila[1]) < 0.005 ? ' cero' : '')) }}">
                            <td class="concepto">{{ $fila[0] }}</td><td class="monto">{{ $soles($fila[1]) }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
        <tr class="total-panel">
            <td style="background: #EDF3F5; padding: 3px 4px 4px;">
                <table><tr><td>TOTAL INGRESOS</td><td class="monto">S/ {{ number_format($totalIngresos, 2) }}</td></tr></table>
            </td>
            <td class="hueco" style="width: 1.2%;"></td>
            <td style="background: #EDF3F5; padding: 3px 4px 4px;">
                <table><tr><td>TOTAL DESCUENTOS</td><td class="monto">S/ {{ number_format($totalDescuentos, 2) }}</td></tr></table>
            </td>
        </tr>
    </table>

    {{-- ── Aportaciones del empleador | Adelantos ── --}}
    <table class="paneles">
        <tr>
            <td class="panel" style="width: 49.4%; height: 56px;">
                <div class="panel__titulo">Aportaciones del empleador</div>
                <table class="conceptos">
                    @foreach ($filasAporte as $fila)
                        <tr class="{{ trim(($fila[2] ?? '') . (abs($fila[1]) < 0.005 ? ' cero' : '')) }}">
                            <td class="concepto">{{ $fila[0] }}</td><td class="monto">{{ $soles($fila[1]) }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
            <td class="hueco" style="width: 1.2%;"></td>
            <td class="panel" style="width: 49.4%; height: 56px;">
                <div class="panel__titulo">Adelantos</div>
                <table class="conceptos">
                    @foreach ($filasAdelanto as $fila)
                        <tr class="{{ trim(($fila[2] ?? '') . (abs($fila[1]) < 0.005 ? ' cero' : '')) }}">
                            <td class="concepto">{{ $fila[0] }}</td><td class="monto">{{ $soles($fila[1]) }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
        <tr class="total-panel">
            <td style="background: #EDF3F5; padding: 3px 4px 4px;">
                <table><tr><td>TOTAL APORTES</td><td class="monto">S/ {{ number_format($totalAportes, 2) }}</td></tr></table>
            </td>
            <td class="hueco" style="width: 1.2%;"></td>
            <td style="background: #EDF3F5; padding: 3px 4px 4px;">
                <table><tr><td>TOTAL ADELANTOS</td><td class="monto">S/ {{ number_format($totalAdelantos, 2) }}</td></tr></table>
            </td>
        </tr>
    </table>

    {{-- ── Pie: QR | total líquido, afecto, mensaje y nota del medio ambiente ── --}}
    <table class="pie">
        <tr>
            <td style="width: 36%;" class="qr">
                @if ($qr)
                    <div class="qr__marco"><img src="{{ $qr }}"></div>
                    <p class="qr__texto">La presentación de la boleta de pago electrónica puede ser verificada a través de la lectura del código QR.</p>
                @endif
            </td>
            <td style="width: 64%;">
                <table class="totales">
                    <tr>
                        <td style="width: 49%;">
                            <table class="total-caja"><tr><td>Total líquido</td><td class="cifra">S/ {{ number_format($totalNeto, 2) }}</td></tr></table>
                        </td>
                        <td style="width: 2%;"></td>
                        <td style="width: 49%;">
                            <table class="total-caja"><tr><td>Afecto a cargas sociales</td><td class="cifra">S/ {{ number_format($afectoCargas, 2) }}</td></tr></table>
                        </td>
                    </tr>
                </table>

                <div class="mensaje-barra">Mensaje</div>
                <p class="mensaje">
                    Revise, firme y descargue sus boletas de pago en CATA-Recibo: {{ $sistema }}<br>
                    @if ($pension['total'] > 0)
                        El aporte a {{ $pension['tipo'] }} se calcula sobre la remuneración afecta, con las tasas de {{ $anio }}.
                    @else
                        No se aplica descuento de pensión: el trabajador no aporta a ningún sistema.
                    @endif
                    Las aportaciones del empleador las asume el colegio y no afectan su sueldo neto.
                </p>

                <table class="eco">
                    <tr>
                        <td style="width: 15%; text-align: center;"><img src="{{ $arboles }}"></td>
                        <td class="eco__texto" style="width: 85%;">Antes de imprimir esta boleta de pago, piense en su responsabilidad social y compromiso con el medio ambiente.</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ── Firmas: del empleador y del trabajador ── --}}
    <table class="firmas">
        <tr>
            <td>
                @if (isset($documento) && $documento->estado_firma_empleador === 'firmado' && $documento->empleador?->identidadFirma?->firma_imagen)
                    <img class="firma" src="{{ \Illuminate\Support\Facades\Storage::disk('local')->path($documento->empleador->identidadFirma->firma_imagen) }}">
                    @if ($documento->empleador?->identidadFirma?->huella_imagen)
                        <img class="huella" src="{{ \Illuminate\Support\Facades\Storage::disk('local')->path($documento->empleador->identidadFirma->huella_imagen) }}">
                    @endif
                @endif
                <div class="linea">Firma del empleador<br>Colegio Adventista Túpac Amaru</div>
            </td>
            <td>
                @if (isset($documento) && $documento->estado_firma === 'firmado' && $empleado->identidadFirma?->firma_imagen)
                    <img class="firma" src="{{ \Illuminate\Support\Facades\Storage::disk('local')->path($empleado->identidadFirma->firma_imagen) }}">
                    {{-- Como en un documento físico peruano, la huella va junto a la firma. --}}
                    @if ($empleado->identidadFirma?->huella_imagen)
                        <img class="huella" src="{{ \Illuminate\Support\Facades\Storage::disk('local')->path($empleado->identidadFirma->huella_imagen) }}">
                    @endif
                @endif
                <div class="linea">Firma del trabajador<br>{{ $empleado->apellido }}, {{ $empleado->nombre }}</div>
            </td>
        </tr>
    </table>

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
