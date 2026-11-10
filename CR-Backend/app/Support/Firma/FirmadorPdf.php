<?php

namespace App\Support\Firma;


use App\Support\Pdf\LectorPdf;
use RuntimeException;

/**
 * Firma un PDF con un certificado digital, como lo hacen ReFirma o Adobe:
 * PAdES (firma CAdES dentro del PDF), agregada AL FINAL del archivo en una
 * «actualización incremental». Lo que ya estaba —la boleta como la emitió el
 * sistema, y las firmas que ya tenga— no se toca ni un byte: por eso una
 * segunda firma, sea de aquí o de ReFirma, no rompe la primera.
 *
 * Lo que se agrega al final:
 *   - el diccionario de firma (/Type /Sig) con su /ByteRange y su /Contents;
 *   - un campo de firma en la primera página (Adobe solo reconoce una firma
 *     si está en un campo del formulario del PDF). Con «sello», el campo va
 *     sobre la casilla de firma que deja el sistema y se ve: el logo del
 *     colegio y lo que dice el certificado (ver sello()). La
 *     primera firma va en la primera casilla, la segunda en la segunda. Si
 *     el PDF no tiene casilla libre, la firma va invisible, como antes;
 *   - el formulario del PDF (/AcroForm) y la página, con ese campo sumado;
 *   - su tabla de referencias y su trailer, apuntando a la anterior.
 *
 * La firma es una CAdES «B-B»: el resumen SHA-256 de los bytes cubiertos,
 * firmado con la llave del certificado, más el atributo que ata la firma a
 * ESE certificado (signing-certificate-v2). Es lo que pide PAdES y lo que
 * Adobe muestra como «firma válida» cuando confía en quien emitió el
 * certificado. Sin sello de tiempo (eso sería B-T y necesita un servicio
 * externo); la fecha va en /M, como la pone ReFirma.
 *
 * Lee PDFs con tabla de referencias clásica (los de dompdf, los que emite el
 * sistema) y también con tabla comprimida (xref stream), por si otro
 * firmador la usó en una firma anterior.
 */
final class FirmadorPdf
{
    /** Bytes reservados para la firma (en hexadecimal ocupa el doble). */
    private const HUECO = 24576;

    /**
     * El fondo de la casilla de firma (#FEFEFE, no se ve sobre blanco) en
     * boleta.blade.php y partials/firma-digital.blade.php, tal como lo
     * escribe dompdf: así se encuentra la casilla en el PDF ya emitido.
     */
    private const FONDO_CASILLA = '0.996 0.996 0.996 rg';

    /**
     * El borde punteado (#8FA9B3) de la casilla en los PDF emitidos antes;
     * se sigue buscando para poder firmarlos. También es el color de la raya
     * que separa el logo del texto en el sello.
     */
    private const COLOR_CASILLA = '0.561 0.663 0.702 RG';

    /**
     * @param  string                $pdf          el PDF tal como está
     * @param  string                $certificado  PEM de quien firma
     * @param  \OpenSSLAsymmetricKey $llave        su llave privada
     * @param  list<string>          $cadena       PEM de los certificados intermedios (van dentro de la firma)
     * @param  array{nombre?: ?string, motivo?: ?string, lugar?: ?string, fecha?: ?\DateTimeInterface,
     *               sello?: ?array{nombre: string, dni?: ?string, cargo?: ?string}} $datos  con «sello», la firma se ve en su casilla
     * @return string el PDF firmado
     */
    public static function firmar(string $pdf, string $certificado, #[\SensitiveParameter] $llave, array $cadena = [], array $datos = []): string
    {
        $lector = new LectorPdf($pdf);
        $trailer = $lector->trailer();

        $raiz = $lector->referencia($trailer['Root'] ?? '') ?? throw new RuntimeException('El PDF no tiene catálogo (/Root).');
        $catalogo = $lector->diccionario($raiz[0]);
        $pagina = self::primeraPagina($lector, $catalogo);

        $siguiente = (int) ($trailer['Size'] ?? 0);
        $numFirma = $siguiente++;
        $numCampo = $siguiente++;

        /** @var array<int, string> $objetos número => contenido (lo que va entre «obj» y «endobj») */
        $objetos = [];

        // ── El formulario del PDF, con el campo nuevo ──────────────────
        $existentes = 0;
        $acro = $catalogo['AcroForm'] ?? null;
        $refAcro = $acro !== null ? $lector->referencia($acro) : null;
        if ($refAcro) {
            $form = $lector->diccionario($refAcro[0]);
            [$form['Fields'], $existentes] = self::sumarAlArreglo($lector, $form['Fields'] ?? null, "{$numCampo} 0 R", $objetos);
            $form['SigFlags'] = '3';
            $objetos[$refAcro[0]] = LectorPdf::armarDiccionario($form);
        } elseif ($acro !== null && str_starts_with(ltrim($acro), '<<')) {
            $form = LectorPdf::partirDiccionario($acro);
            [$form['Fields'], $existentes] = self::sumarAlArreglo($lector, $form['Fields'] ?? null, "{$numCampo} 0 R", $objetos);
            $form['SigFlags'] = '3';
            $catalogo['AcroForm'] = LectorPdf::armarDiccionario($form);
            $objetos[$raiz[0]] = LectorPdf::armarDiccionario($catalogo);
        } else {
            $numForm = $siguiente++;
            $objetos[$numForm] = "<< /Fields [{$numCampo} 0 R] /SigFlags 3 >>";
            $catalogo['AcroForm'] = "{$numForm} 0 R";
            $objetos[$raiz[0]] = LectorPdf::armarDiccionario($catalogo);
        }

        // ── La página, con el campo entre sus anotaciones ──────────────
        [$numPagina, $dicPagina] = $pagina;
        [$dicPagina['Annots']] = self::sumarAlArreglo($lector, $dicPagina['Annots'] ?? null, "{$numCampo} 0 R", $objetos);
        $objetos[$numPagina] = LectorPdf::armarDiccionario($dicPagina);

        $fecha = \DateTimeImmutable::createFromInterface($datos['fecha'] ?? new \DateTimeImmutable('now'))
            ->setTimezone(new \DateTimeZone(config('app.timezone', 'America/Lima')));

        // ── El campo de firma: con su sello en la casilla, o invisible ─
        $rect = '[0 0 0 0]';
        $apariencia = '';
        if (! empty($datos['sello'])) {
            // Las firmas que ya tiene ocupan las primeras casillas.
            $casilla = self::casillas($lector->contenidoDePagina($pagina[1]))[preg_match_all('/\/ByteRange\s*\[/', $pdf)] ?? null;
            if ($casilla) {
                [$x1, $y1, $x2, $y2] = $casilla;
                $rect = sprintf('[%.3F %.3F %.3F %.3F]', $x1, $y1, $x2, $y2);
                $numSello = $siguiente;
                $objetos += self::sello($x2 - $x1, $y2 - $y1, $datos['sello'], $fecha, $datos['lugar'] ?? null, $siguiente);
                $apariencia = " /AP << /N {$numSello} 0 R >>";
            }
        }
        $nombreCampo = 'Firma' . ($existentes + 1) . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $objetos[$numCampo] = "<< /Type /Annot /Subtype /Widget /FT /Sig /T ({$nombreCampo}) /V {$numFirma} 0 R"
            . " /F 132 /Rect {$rect}{$apariencia} /P {$numPagina} 0 R >>";

        // ── El diccionario de firma, con su hueco ──────────────────────
        $zona = str_replace(':', "'", $fecha->format('P')) . "'";
        $marcador = '[0 ########## ########## ##########]';
        $objetos[$numFirma] = '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached'
            . " /ByteRange {$marcador} /Contents <" . str_repeat('0', self::HUECO * 2) . '>'
            . ' /M (D:' . $fecha->format('YmdHis') . $zona . ')'
            . (empty($datos['nombre']) ? '' : ' /Name ' . LectorPdf::texto($datos['nombre']))
            . (empty($datos['motivo']) ? '' : ' /Reason ' . LectorPdf::texto($datos['motivo']))
            . (empty($datos['lugar']) ? '' : ' /Location ' . LectorPdf::texto($datos['lugar']))
            . ' >>';

        // ── La actualización incremental ───────────────────────────────
        $salida = $pdf . (str_ends_with($pdf, "\n") ? '' : "\n");
        ksort($objetos);
        $posiciones = [];
        foreach ($objetos as $num => $contenido) {
            $posiciones[$num] = strlen($salida);
            $salida .= "{$num} {$lector->generacion($num)} obj\n{$contenido}\nendobj\n";
        }

        $tabla = strlen($salida);
        $salida .= "xref\n";
        foreach ($posiciones as $num => $pos) {
            $salida .= "{$num} 1\n" . sprintf('%010d %05d n', $pos, $lector->generacion($num)) . "\r\n";
        }
        $id = $trailer['ID'] ?? null;
        $primerId = $id && preg_match('/^\[\s*(<[0-9A-Fa-f]*>)/', $id, $m) ? $m[1] : '<' . bin2hex(random_bytes(16)) . '>';
        $salida .= "trailer\n<< /Size {$siguiente} /Root {$raiz[0]} {$raiz[1]} R"
            . (isset($trailer['Info']) ? " /Info {$trailer['Info']}" : '')
            . " /ID [{$primerId} <" . bin2hex(random_bytes(16)) . '>]'
            . " /Prev {$lector->inicioDeLaTabla()} >>\nstartxref\n{$tabla}\n%%EOF\n";

        // ── El rango que cubre la firma, y la firma ────────────────────
        $inicioFirma = $posiciones[$numFirma];
        $b = strpos($salida, '/Contents <', $inicioFirma) + strlen('/Contents ');
        $c = $b + self::HUECO * 2 + 2;
        $rango = sprintf('[0 %10d %10d %10d]', $b, $c, strlen($salida) - $c);
        $salida = substr_replace($salida, $rango, strpos($salida, $marcador, $inicioFirma), strlen($marcador));

        $cubierto = substr($salida, 0, $b) . substr($salida, $c);
        $cms = CmsCades::firmar($cubierto, $certificado, $llave, $cadena);
        if (strlen($cms) > self::HUECO) {
            throw new RuntimeException('La firma no entra en el espacio reservado: el certificado trae una cadena demasiado larga.');
        }

        return substr_replace($salida, strtoupper(str_pad(bin2hex($cms), self::HUECO * 2, '0')), $b + 1, self::HUECO * 2);
    }

    /**
     * Las casillas de firma de la página, de izquierda a derecha, como
     * [x1, y1, x2, y2]. La de ahora es un fondo #FEFEFE sin borde (dompdf lo
     * escribe como un «re f»); las emitidas antes eran un borde punteado,
     * que dompdf dibuja lado por lado: con la raya de arriba y la de abajo
     * basta.
     *
     * @return list<array{0: float, 1: float, 2: float, 3: float}>
     */
    private static function casillas(string $contenido): array
    {
        $casillas = [];
        preg_match_all(
            '/' . preg_quote(self::FONDO_CASILLA, '/') . '\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+re\s+f\b/',
            $contenido,
            $fondos,
            PREG_SET_ORDER
        );
        foreach ($fondos as [, $x, $y, $ancho, $alto]) {
            if ((float) $ancho >= 100 && (float) $alto >= 24 && (float) $alto <= 160) {
                $casillas[] = [(float) $x, (float) $y, (float) $x + (float) $ancho, (float) $y + (float) $alto];
            }
        }
        if ($casillas) {
            usort($casillas, fn ($a, $b) => $a[0] <=> $b[0]);

            return $casillas;
        }

        preg_match_all(
            '/' . preg_quote(self::COLOR_CASILLA, '/') . '\s+(?:\/\w+\s+gs\s+)?[\d.]+\s+w\s+\d+\s+J\s+\[[^\]]*\]\s+[\d.]+\s+d\s+'
                . '([\d.]+)\s+([\d.]+)\s+m\s+([\d.]+)\s+([\d.]+)\s+l\s+S/',
            $contenido,
            $rayas,
            PREG_SET_ORDER
        );

        $porAncho = [];
        foreach ($rayas as [, $xa, $ya, $xb, $yb]) {
            // (borde punteado: el de las emitidas antes)
            if (abs((float) $ya - (float) $yb) > 0.01) {
                continue; // un lado: no hace falta
            }
            [$izq, $der] = [min((float) $xa, (float) $xb), max((float) $xa, (float) $xb)];
            $clave = round($izq) . ':' . round($der);
            $porAncho[$clave]['x'] = [$izq, $der];
            $porAncho[$clave]['y'][] = (float) $ya;
        }

        foreach ($porAncho as $raya) {
            [$abajo, $arriba] = [min($raya['y']), max($raya['y'])];
            // Una casilla de verdad: dos rayas, ni muy baja ni muy alta.
            if (count($raya['y']) >= 2 && $arriba - $abajo >= 24 && $arriba - $abajo <= 160) {
                $casillas[] = [$raya['x'][0], $abajo, $raya['x'][1], $arriba];
            }
        }
        usort($casillas, fn ($a, $b) => $a[0] <=> $b[0]);

        return $casillas;
    }

    /**
     * El dibujo de la firma dentro de su casilla: el logo del colegio a la
     * izquierda y, a la derecha, lo que dice el certificado: quién firmó, su
     * cargo, DNI y RUC, la institución, la fecha, y quién emitió el
     * certificado con su número de serie. Fuentes Helvetica del propio PDF
     * (no hay que incrustar nada) y el logo en JPEG (public/logo-firma.jpg).
     *
     * @param  array{nombre: string, dni?: ?string, cargo?: ?string, ruc?: ?string, organizacion?: ?string, emisor?: ?string, serie?: ?string}  $sello
     * @return array<int, string> los objetos nuevos; el primero es el dibujo
     */
    private static function sello(float $ancho, float $alto, array $sello, \DateTimeImmutable $fecha, ?string $lugar, int &$siguiente): array
    {
        $numDibujo = $siguiente++;
        $numNormal = $siguiente++;
        $numNegrita = $siguiente++;
        $objetos = [
            $numNormal  => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            $numNegrita => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];

        $margen = 4.0;
        $dibujo = '';

        $xTexto = $margen + 2;
        $logo = public_path('logo-firma.jpg');
        $datosLogo = is_file($logo) ? getimagesize($logo) : false;
        if ($datosLogo) {
            $numLogo = $siguiente++;
            $jpg = (string) file_get_contents($logo);
            $color = ($datosLogo['channels'] ?? 3) === 1 ? '/DeviceGray' : '/DeviceRGB';
            $objetos[$numLogo] = "<< /Type /XObject /Subtype /Image /Width {$datosLogo[0]} /Height {$datosLogo[1]}"
                . " /ColorSpace {$color} /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpg) . " >>\nstream\n{$jpg}\nendstream";
            $lado = min($alto - 2 * $margen, 46.0);
            $dibujo .= sprintf("q %.3F 0 0 %.3F %.3F %.3F cm /Logo Do Q\n", $lado, $lado, $margen + 2, ($alto - $lado) / 2);
            $xRaya = $margen + 2 + $lado + 5;
            $dibujo .= sprintf("q %s 0.5 w %.3F %.3F m %.3F %.3F l S Q\n", self::COLOR_CASILLA, $xRaya, $margen + 2, $xRaya, $alto - $margen - 2);
            $xTexto = $xRaya + 6;
        }

        // Algunas entidades pegan el RUC al nombre («… ISIDORO RUC:20…»): en el sello sobra.
        $nombre = trim((string) preg_replace('/\s*RUC\s*:?\s*\d{11}/i', '', $sello['nombre']));
        $gris = '0.400 0.467 0.502';
        $oscuro = '0.122 0.169 0.200';
        $lineas = [['F1', 5.6, $gris, 'Firmado digitalmente por:'], ['F2', 8.5, $oscuro, $nombre]];
        if (! empty($sello['cargo'])) {
            $lineas[] = ['F1', 5.6, $oscuro, mb_strtoupper($sello['cargo'])];
        }
        $documentos = implode(' · ', array_filter([
            empty($sello['dni']) ? null : 'DNI ' . $sello['dni'],
            empty($sello['ruc']) ? null : 'RUC ' . $sello['ruc'],
        ]));
        if ($documentos !== '') {
            $lineas[] = ['F1', 5.6, $oscuro, $documentos];
        }
        if (! empty($sello['organizacion'])) {
            $lineas[] = ['F1', 5.6, $oscuro, $sello['organizacion']];
        }
        $lineas[] = ['F1', 5.6, $oscuro, 'Fecha: ' . $fecha->format('d/m/Y H:i:s') . ($lugar ? " · {$lugar}" : '')];
        // Con qué certificado: quien lo emitió y su número de serie, para buscarlo si hace falta.
        $certificado = implode(' · ', array_filter([
            empty($sello['emisor']) ? null : 'Certificado: ' . $sello['emisor'],
            empty($sello['serie']) ? null : 'N° ' . strtoupper($sello['serie']),
        ]));
        if ($certificado !== '') {
            $lineas[] = ['F1', 4.8, $gris, $certificado];
        }

        // Cada línea entra en el ancho que queda: si no, se achica la letra.
        $disponible = $ancho - $xTexto - $margen;
        foreach ($lineas as &$linea) {
            $linea[1] = max(4.0, min($linea[1], $disponible / max(1.0, self::anchoHelvetica($linea[3], $linea[0] === 'F2'))));
        }
        unset($linea);

        // Y todas juntas entran en el alto: si no, se achican parejo.
        $total = array_sum(array_map(fn ($l) => $l[1] * 1.3, $lineas));
        if ($total > $alto - 2 * $margen) {
            $escala = ($alto - 2 * $margen) / $total;
            $lineas = array_map(fn ($l) => [$l[0], $l[1] * $escala, $l[2], $l[3]], $lineas);
            $total = $alto - 2 * $margen;
        }
        $y = ($alto + $total) / 2;
        foreach ($lineas as [$fuente, $tamano, $color, $texto]) {
            $y -= $tamano * 1.3;
            $dibujo .= sprintf("BT /%s %.2F Tf %s rg %.3F %.3F Td (%s) Tj ET\n", $fuente, $tamano, $color, $xTexto, $y + $tamano * 0.25, self::textoWinAnsi($texto));
        }

        $recursos = "/Font << /F1 {$numNormal} 0 R /F2 {$numNegrita} 0 R >>" . (isset($numLogo) ? " /XObject << /Logo {$numLogo} 0 R >>" : '');
        $objetos[$numDibujo] = sprintf('<< /Type /XObject /Subtype /Form /BBox [0 0 %.3F %.3F] /Resources << %s >> /Length %d >>', $ancho, $alto, $recursos, strlen($dibujo))
            . "\nstream\n{$dibujo}\nendstream";
        ksort($objetos);

        return $objetos;
    }

    /** Cuánto mide un texto en Helvetica a 1 punto, más o menos (para que no se salga de la casilla). */
    private static function anchoHelvetica(string $texto, bool $negrita): float
    {
        $ancho = 0.0;
        foreach (mb_str_split($texto) as $c) {
            $ancho += match (true) {
                $c === ' ', str_contains("iljI.,:;·'|!", $c) => 0.278,
                str_contains('MWmw', $c)                      => 0.89,
                ctype_digit($c)                               => 0.556,
                mb_strtolower($c) !== $c                      => 0.722,
                default                                       => 0.556,
            };
        }

        return $ancho * ($negrita ? 1.05 : 1.0);
    }

    /** Un texto para las fuentes estándar del PDF (WinAnsi), con tildes y eñes. */
    private static function textoWinAnsi(string $texto): string
    {
        return addcslashes((string) mb_convert_encoding($texto, 'Windows-1252', 'UTF-8'), '\\()');
    }

    /** @return array{0: int, 1: array<string, string>} número y diccionario de la primera página. */
    private static function primeraPagina(LectorPdf $lector, array $catalogo): array
    {
        $ref = $lector->referencia($catalogo['Pages'] ?? '') ?? throw new RuntimeException('El PDF no tiene páginas.');
        for ($vueltas = 0; $vueltas < 32; $vueltas++) {
            $dic = $lector->diccionario($ref[0]);
            if (trim($dic['Type'] ?? '') === '/Page') {
                return [$ref[0], $dic];
            }
            $hijos = $lector->valor($dic['Kids'] ?? '[]');
            if (! preg_match('/(\d+)\s+(\d+)\s+R/', $hijos, $m)) {
                break;
            }
            $ref = [(int) $m[1], (int) $m[2]];
        }

        throw new RuntimeException('No se encontró la primera página del PDF.');
    }

    /**
     * Suma una referencia a un arreglo del PDF (/Fields, /Annots), esté
     * escrito ahí mismo o en un objeto aparte (en ese caso se reescribe ese
     * objeto). Devuelve el valor a poner en la clave y cuántos había.
     *
     * @param  array<int, string>  $objetos
     * @return array{0: string, 1: int}
     */
    private static function sumarAlArreglo(LectorPdf $lector, ?string $valor, string $nueva, array &$objetos): array
    {
        if ($valor === null || trim($valor) === '') {
            return ["[{$nueva}]", 0];
        }
        if ($ref = $lector->referencia($valor)) {
            $arreglo = trim($lector->contenido($ref[0]));
            $objetos[$ref[0]] = self::agregar($arreglo, $nueva);

            return [$valor, preg_match_all('/\d+\s+\d+\s+R/', $arreglo)];
        }

        return [self::agregar(trim($valor), $nueva), preg_match_all('/\d+\s+\d+\s+R/', $valor)];
    }

    private static function agregar(string $arreglo, string $nueva): string
    {
        if (! str_starts_with($arreglo, '[') || ! str_ends_with($arreglo, ']')) {
            throw new RuntimeException('El PDF tiene un arreglo que no se entiende.');
        }

        return rtrim(substr($arreglo, 0, -1)) . " {$nueva}]";
    }
}
