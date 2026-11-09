{{--
    El pie de firmas de las hojas que emite el sistema (CTS, gratificación).

    Con firma digital del colegio: una o dos casillas para el sello del
    empleador, y al lado la conformidad del trabajador, que no firma sobre el
    PDF (lo invalidaría): la da con su contraseña en CATA-Recibo y queda
    registrada allí. La firma del colegio va dentro del PDF; el sello visible
    lo pone ReFirma si se firma con el DNIe.

    Sin firma digital: las dos rayas de siempre, para firmar en papel.

    Recibe: $firmaDigital, $firmasRequeridas, $trabajador (nombre), $dni.
    Lo dibuja dompdf: solo tablas y colores literales.
--}}
<style>
    .pie-firmas { margin-top: 18px; }
    .pie-firmas td { vertical-align: top; padding: 0 8px; }
    .pie-firmas .casilla { border: 1px dashed #8FA9B3; height: 64px; position: relative; }
    .pie-firmas .casilla__guia { position: absolute; top: 26px; left: 0; right: 0; text-align: center; font-size: 6.4px; color: #A8B9BF; letter-spacing: .4px; text-transform: uppercase; }
    .pie-firmas .casilla--trabajador { border: 1px solid #DCE7EB; background: #F6F9FA; }
    .pie-firmas .casilla__texto { padding: 12px 10px 0; text-align: center; font-size: 7px; color: #4A5961; line-height: 1.45; }
    .pie-firmas .pie { text-align: center; font-size: 7.4px; color: #2E3B42; margin-top: 4px; line-height: 1.4; }
    .pie-firmas .pie strong { font-size: 7.6px; }
    .nota-legal { margin-top: 10px; font-size: 6.6px; color: #2E3B42; background: #EDF3F5; padding: 5px 8px; line-height: 1.45; }
    .firmas-papel { margin-top: 56px; }
    .firmas-papel td { text-align: center; font-size: 7.8px; padding: 0 24px; vertical-align: top; }
    .firmas-papel .raya { border-top: 1px solid #1F2B33; padding-top: 4px; line-height: 1.4; }
</style>

@if (!empty($firmaDigital))
    @php $casillas = max(1, min(2, (int) ($firmasRequeridas ?? 1))); @endphp
    <table class="pie-firmas">
        <tr>
            @for ($i = 1; $i <= $casillas; $i++)
                <td style="width: {{ $casillas === 1 ? 50 : 33 }}%;">
                    <div class="casilla"><div class="casilla__guia">Firma digital</div></div>
                    <div class="pie">
                        <strong>EL EMPLEADOR</strong>{{ $casillas > 1 ? ' (' . $i . ' de ' . $casillas . ')' : '' }}<br>
                        Colegio Adventista Túpac Amaru
                    </div>
                </td>
            @endfor
            <td style="width: {{ $casillas === 1 ? 50 : 34 }}%;">
                <div class="casilla casilla--trabajador">
                    <div class="casilla__texto">
                        Conformidad registrada en CATA-Recibo<br>
                        con la contraseña personal del trabajador,<br>
                        con fecha, hora y código de verificación.
                    </div>
                </div>
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
