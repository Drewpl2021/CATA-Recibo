<?php

namespace App\Support;

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
 *   - un campo de firma invisible en la primera página (Adobe solo reconoce
 *     una firma si está en un campo del formulario del PDF);
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
     * @param  string                $pdf          el PDF tal como está
     * @param  string                $certificado  PEM de quien firma
     * @param  \OpenSSLAsymmetricKey $llave        su llave privada
     * @param  list<string>          $cadena       PEM de los certificados intermedios (van dentro de la firma)
     * @param  array{nombre?: ?string, motivo?: ?string, lugar?: ?string, fecha?: ?\DateTimeInterface} $datos
     * @return string el PDF firmado
     */
    public static function firmar(string $pdf, string $certificado, $llave, array $cadena = [], array $datos = []): string
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

        // ── El campo de firma (invisible) ──────────────────────────────
        $nombreCampo = 'Firma' . ($existentes + 1) . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $objetos[$numCampo] = "<< /Type /Annot /Subtype /Widget /FT /Sig /T ({$nombreCampo}) /V {$numFirma} 0 R"
            . " /F 132 /Rect [0 0 0 0] /P {$numPagina} 0 R >>";

        // ── El diccionario de firma, con su hueco ──────────────────────
        $fecha = \DateTimeImmutable::createFromInterface($datos['fecha'] ?? new \DateTimeImmutable('now'))
            ->setTimezone(new \DateTimeZone(config('app.timezone', 'America/Lima')));
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
