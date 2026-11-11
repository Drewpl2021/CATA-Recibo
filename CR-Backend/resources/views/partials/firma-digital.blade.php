{{--
    El pie de firmas de las hojas que emite el sistema (CTS, gratificación).

    Con firma digital del colegio: a la izquierda, una o dos casillas para el
    sello del empleador; a la derecha, el trabajador: un espacio en blanco con
    la línea para firmar cuando se imprime, su nombre y DNI. En el PDF no
    firma (lo invalidaría): su conformidad la da con su contraseña en
    CATA-Recibo y queda registrada allí. La firma del colegio va dentro del PDF y su sello se
    dibuja en la casilla del empleador, que no lleva borde: «Firmar aquí» la
    encuentra por su fondo #FEFEFE (ver FirmadorPdf::casillas()).

    Sin firma digital: las dos rayas de siempre, para firmar en papel.

    Recibe: $firmaDigital, $firmasRequeridas, $trabajador (nombre), $dni.
    Con $soloEmpleador (constancias) va solo la firma del colegio, al centro.
    Lo dibuja dompdf: solo tablas y colores literales.
--}}
<style>
    .pie-firmas { margin-top: 18px; }
    .pie-firmas td { vertical-align: top; padding: 0 8px; }
    .pie-firmas .casilla { background: #FEFEFE; height: 64px; }
    .pie-firmas .firma-trabajador { height: 64px; border-bottom: 1px solid #1F2B33; margin: 0 16px; }
    .pie-firmas .pie { text-align: center; font-size: 7.4px; color: #2E3B42; margin-top: 4px; line-height: 1.4; }
    .pie-firmas .pie strong { font-size: 7.6px; }
    .nota-legal { margin-top: 10px; font-size: 6.6px; color: #2E3B42; background: #EDF3F5; padding: 5px 8px; line-height: 1.45; }
    .firmas-papel { margin-top: 56px; }
    .firmas-papel td { text-align: center; font-size: 7.8px; padding: 0 24px; vertical-align: top; }
    .firmas-papel .raya { border-top: 1px solid #1F2B33; padding-top: 4px; line-height: 1.4; }
</style>

@if (!empty($firmaDigital) && !empty($soloEmpleador))
    {{-- Una constancia: solo la firma del colegio, al centro. --}}
    @php $casillas = max(1, min(2, (int) ($firmasRequeridas ?? 1))); @endphp
    <table class="pie-firmas">
        <tr>
            <td style="width: {{ $casillas === 1 ? 25 : 10 }}%;"></td>
            @for ($i = 1; $i <= $casillas; $i++)
                <td style="width: {{ $casillas === 1 ? 50 : 40 }}%;">
                    <div class="casilla"></div>
                    <div class="pie">
                        <strong>EL EMPLEADOR</strong>{{ $casillas > 1 ? ' (' . $i . ' de ' . $casillas . ')' : '' }}<br>
                        Colegio Adventista Túpac Amaru
                    </div>
                </td>
            @endfor
            <td style="width: {{ $casillas === 1 ? 25 : 10 }}%;"></td>
        </tr>
    </table>
    <div class="nota-legal">
        Documento firmado digitalmente por el empleador conforme a la Ley N° 27269, Ley de Firmas y Certificados Digitales,
        y al D. Leg. N° 1310. Emitido desde el sistema CATA-Recibo.
    </div>
@elseif (!empty($firmaDigital))
    @php $casillas = max(1, min(2, (int) ($firmasRequeridas ?? 1))); @endphp
    <table class="pie-firmas">
        <tr>
            @for ($i = 1; $i <= $casillas; $i++)
                <td style="width: {{ $casillas === 1 ? 50 : 33 }}%;">
                    <div class="casilla"></div>
                    <div class="pie">
                        <strong>EL EMPLEADOR</strong>{{ $casillas > 1 ? ' (' . $i . ' de ' . $casillas . ')' : '' }}<br>
                        Colegio Adventista Túpac Amaru
                    </div>
                </td>
            @endfor
            <td style="width: {{ $casillas === 1 ? 50 : 34 }}%;">
                <div class="firma-trabajador"></div>
                <div class="pie">
                    <strong>EL TRABAJADOR</strong><br>
                    {{ $trabajador }} · DNI {{ $dni }}
                </div>
            </td>
        </tr>
    </table>
    <div class="nota-legal">
        Documento firmado digitalmente por el empleador conforme a la Ley N° 27269, Ley de Firmas y Certificados Digitales,
        y al D. Leg. N° 1310. La recepción y la conformidad del trabajador quedan registradas en el sistema CATA-Recibo.
    </div>
@else
    <table class="firmas-papel">
        <tr>
            <td><div class="raya">EL EMPLEADOR<br>Asociación Educativa Colegio Adventista Túpac Amaru</div></td>
            <td><div class="raya">EL TRABAJADOR<br>{{ $trabajador }} · DNI {{ $dni }}</div></td>
        </tr>
    </table>
@endif
