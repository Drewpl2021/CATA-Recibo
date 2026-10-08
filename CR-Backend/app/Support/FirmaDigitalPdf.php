<?php

namespace App\Support;

/**
 * Lee las firmas digitales de un PDF (las que pone ReFirma de RENIEC, o
 * cualquier firmador PAdES) y comprueba que el archivo no se tocó después.
 *
 * Cómo se firma un PDF, en corto: el firmador AGREGA al final del archivo un
 * «diccionario de firma» con dos datos —/ByteRange (qué bytes del archivo
 * cubre la firma: todo menos el hueco donde va ella misma) y /Contents (la
 * firma, un CMS/PKCS#7 en hexadecimal)—. Una segunda firma se agrega igual,
 * más al final, y cubre todo lo anterior incluida la primera.
 *
 * Por cada firma esto comprueba, con OpenSSL:
 *   - que la firma corresponda exactamente a esos bytes (si alguien cambió
 *     una coma después de firmar, no cuadra);
 *   - quién firmó: el nombre y el DNI salen del certificado.
 *
 * Lo que NO hace es preguntarle a RENIEC si el certificado está vigente o
 * revocado (eso lo muestra ReFirma o Adobe al abrir el PDF): aquí se prueba
 * la integridad y la identidad que dice el certificado.
 */
final class FirmaDigitalPdf
{
    /**
     * Las firmas del PDF, de la primera a la última.
     *
     * @return list<array{
     *     valida: bool, cubre_hasta_el_final: bool, nombre: ?string, dni: ?string,
     *     emisor: ?string, fecha: ?string, motivo: ?string
     * }>
     */
    public static function firmas(string $pdf): array
    {
        $firmas = [];
        $total = strlen($pdf);

        if (! preg_match_all('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdf, $rangos, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        foreach ($rangos as $rango) {
            [$a, $b, $c, $d] = [(int) $rango[1][0], (int) $rango[2][0], (int) $rango[3][0], (int) $rango[4][0]];
            $firma = [
                'valida' => false, 'cubre_hasta_el_final' => false, 'nombre' => null, 'dni' => null,
                'emisor' => null, 'fecha' => self::fechaDelDiccionario($pdf, $rango[0][1]), 'motivo' => null,
            ];

            // El rango tiene que tener sentido: empieza en 0, deja un hueco y
            // no se sale del archivo.
            if ($a !== 0 || $b <= 0 || $c <= $b || $c + $d > $total) {
                $firmas[] = ['motivo' => 'La firma no indica bien qué parte del archivo cubre.'] + $firma;
                continue;
            }

            $hueco = substr($pdf, $b, $c - $b);
            if (! preg_match('/^<([0-9A-Fa-f\s]+)>$/', $hueco, $m)) {
                $firmas[] = ['motivo' => 'No se encontró la firma donde el archivo dice que está.'] + $firma;
                continue;
            }

            $der = self::recortarDer(hex2bin(preg_replace('/\s+/', '', $m[1])) ?: '');
            $datos = substr($pdf, $a, $b) . substr($pdf, $c, $d);
            $firma['cubre_hasta_el_final'] = ($c + $d) >= strlen(rtrim($pdf));

            [$valida, $certificado] = self::verificar($datos, $der);
            $firma['valida'] = $valida;
            if (! $valida) {
                $firma['motivo'] = 'La firma no corresponde al contenido: el archivo se cambió después de firmarlo.';
            }

            if ($certificado) {
                $firma = self::datosDelFirmante($certificado) + $firma;
            }

            $firmas[] = $firma;
        }

        return $firmas;
    }

    /**
     * La firma va en un hueco fijo relleno de ceros: lo que importa es el
     * CMS (un DER), que dice su propio largo en la cabecera.
     */
    private static function recortarDer(string $binario): string
    {
        if (strlen($binario) < 2 || ord($binario[0]) !== 0x30) {
            return $binario;
        }

        $primero = ord($binario[1]);
        if ($primero < 0x80) {
            return substr($binario, 0, 2 + $primero);
        }

        $bytes = $primero & 0x7F;
        $largo = 0;
        for ($i = 0; $i < $bytes; $i++) {
            $largo = ($largo << 8) | ord($binario[2 + $i]);
        }

        return substr($binario, 0, 2 + $bytes + $largo);
    }

    /** @return array{0: bool, 1: ?string} si cuadra y el certificado de quien firmó (PEM). */
    private static function verificar(string $datos, string $der): array
    {
        $dir = sys_get_temp_dir();
        $archivoDatos = tempnam($dir, 'pdfdatos');
        $archivoFirma = tempnam($dir, 'pdffirma');
        $archivoCert  = tempnam($dir, 'pdfcert');

        try {
            file_put_contents($archivoDatos, $datos);
            file_put_contents($archivoFirma, $der);

            // NOVERIFY: no se valida la cadena hasta RENIEC (no hay internet ni
            // sus raíces aquí); sí que la firma corresponda a los datos.
            $ok = @openssl_cms_verify(
                $archivoDatos,
                OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY | OPENSSL_CMS_NOVERIFY,
                $archivoCert,
                [],
                null,
                null,
                null,
                $archivoFirma,
                OPENSSL_ENCODING_DER
            );

            $pem = is_file($archivoCert) ? (string) file_get_contents($archivoCert) : '';
            // El primero que trae es el de quien firmó.
            $certificado = preg_match('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', $pem, $m) ? $m[0] : null;

            return [$ok === true, $certificado];
        } finally {
            @unlink($archivoDatos);
            @unlink($archivoFirma);
            @unlink($archivoCert);
        }
    }

    /** Nombre, DNI y quién emitió el certificado. */
    private static function datosDelFirmante(string $pem): array
    {
        $info = openssl_x509_parse($pem) ?: [];
        $sujeto = $info['subject'] ?? [];
        $uno = fn ($v) => is_array($v) ? implode(' ', $v) : ($v === null ? null : (string) $v);

        $nombre = trim(($uno($sujeto['GN'] ?? $sujeto['givenName'] ?? null) ?? '') . ' ' . ($uno($sujeto['SN'] ?? $sujeto['surname'] ?? null) ?? ''));
        if ($nombre === '') {
            $nombre = $uno($sujeto['CN'] ?? null) ?? '';
        }

        // RENIEC pone el DNI en el serialNumber del sujeto («PNOPE-12345678»)
        // y a veces también en el CN: se toman los 8 dígitos.
        $dni = null;
        foreach ([$sujeto['serialNumber'] ?? null, $sujeto['CN'] ?? null] as $donde) {
            if ($donde !== null && preg_match('/(?<!\d)(\d{8})(?!\d)/', $uno($donde), $d)) {
                $dni = $d[1];
                break;
            }
        }

        return [
            'nombre' => $nombre !== '' ? mb_strtoupper(trim(preg_replace('/\s+/', ' ', $nombre))) : null,
            'dni'    => $dni,
            'emisor' => $uno($info['issuer']['O'] ?? $info['issuer']['CN'] ?? null),
        ];
    }

    /** La fecha del diccionario de firma (/M), si la trae: «D:20261108103000-05'00'». */
    private static function fechaDelDiccionario(string $pdf, int $posicion): ?string
    {
        $inicio = strrpos(substr($pdf, 0, $posicion), '<<');
        $tramo = substr($pdf, $inicio === false ? max(0, $posicion - 2000) : $inicio, 4000);

        if (! preg_match('/\/M\s*\(D:(\d{4})(\d{2})(\d{2})(\d{2})?(\d{2})?(\d{2})?([^)]*)\)/', $tramo, $f)) {
            return null;
        }

        $zona = '-05:00';
        if (preg_match("/([+\-])(\d{2})'?(\d{2})/", $f[7] ?? '', $z)) {
            $zona = "{$z[1]}{$z[2]}:{$z[3]}";
        } elseif (str_starts_with($f[7] ?? '', 'Z')) {
            $zona = '+00:00';
        }

        try {
            return (new \DateTimeImmutable(sprintf('%s-%s-%sT%s:%s:%s%s', $f[1], $f[2], $f[3], $f[4] ?: '00', $f[5] ?: '00', $f[6] ?: '00', $zona)))
                ->setTimezone(new \DateTimeZone(config('app.timezone', 'America/Lima')))
                ->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
