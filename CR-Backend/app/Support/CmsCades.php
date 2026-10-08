<?php

namespace App\Support;

use RuntimeException;

/**
 * La firma CAdES («B-B») que va dentro del PDF: un CMS SignedData separado
 * (no lleva los datos, solo su resumen), firmado con SHA-256.
 *
 * Se arma a mano, en DER, porque openssl_cms_sign() de PHP no deja agregar
 * el atributo signing-certificate-v2, y sin él la firma no es PAdES: ata la
 * firma al certificado exacto de quien firmó, para que nadie pueda hacerla
 * pasar por otra de la misma llave.
 *
 * Atributos firmados: tipo de contenido, resumen del documento y el
 * certificado. Sin signingTime: en PAdES la fecha va en el /M del PDF.
 */
final class CmsCades
{
    private const SHA256 = '2.16.840.1.101.3.4.2.1';

    /**
     * @param  string                $datos        los bytes que cubre la firma
     * @param  string                $certificado  PEM de quien firma
     * @param  \OpenSSLAsymmetricKey $llave
     * @param  list<string>          $cadena       PEM de los intermedios
     * @return string el CMS en DER
     */
    public static function firmar(string $datos, string $certificado, $llave, array $cadena = []): string
    {
        $der = self::pemADer($certificado);
        [$emisor, $serie] = self::emisorYSerie($der);

        $certificadoFirmante = self::seq(
            self::seq(
                self::seq(
                    self::tlv(0x04, hash('sha256', $der, true)),
                    // IssuerSerial: GeneralNames { directoryName [4] Name } y la serie.
                    self::seq(self::seq(self::tlv(0xA4, $emisor)), $serie)
                )
            )
        );

        $atributos = [
            self::seq(self::oid('1.2.840.113549.1.9.3'), self::set(self::oid('1.2.840.113549.1.7.1'))),
            self::seq(self::oid('1.2.840.113549.1.9.4'), self::set(self::tlv(0x04, hash('sha256', $datos, true)))),
            self::seq(self::oid('1.2.840.113549.1.9.16.2.47'), self::set($certificadoFirmante)),
        ];
        // Un SET OF en DER va ordenado por su codificación.
        sort($atributos, SORT_STRING);
        $firmados = self::tlv(0x31, implode('', $atributos));

        if (! openssl_sign($firmados, $firma, $llave, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar con la llave del certificado.');
        }

        $tipo = openssl_pkey_get_details($llave)['type'] ?? null;
        $algoritmoFirma = match ($tipo) {
            OPENSSL_KEYTYPE_RSA => self::seq(self::oid('1.2.840.113549.1.1.1'), "\x05\x00"),
            OPENSSL_KEYTYPE_EC  => self::seq(self::oid('1.2.840.10045.4.3.2')),
            default             => throw new RuntimeException('El certificado usa un tipo de llave que no se puede usar para firmar.'),
        };
        $sha256 = self::seq(self::oid(self::SHA256));

        $firmante = self::seq(
            self::entero(1),
            self::seq($emisor, $serie),
            $sha256,
            // Los atributos van con la etiqueta [0] en vez de la del SET.
            "\xA0" . substr($firmados, 1),
            $algoritmoFirma,
            self::tlv(0x04, $firma)
        );

        $certificados = $der . implode('', array_map(self::pemADer(...), $cadena));

        $signedData = self::seq(
            self::entero(1),
            self::set($sha256),
            self::seq(self::oid('1.2.840.113549.1.7.1')),
            self::tlv(0xA0, $certificados),
            self::set($firmante)
        );

        return self::seq(self::oid('1.2.840.113549.1.7.2'), self::tlv(0xA0, $signedData));
    }

    public static function pemADer(string $pem): string
    {
        if (! preg_match('/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s', $pem, $m)) {
            throw new RuntimeException('El certificado no se entiende.');
        }

        return base64_decode(preg_replace('/\s+/', '', $m[1]), true) ?: throw new RuntimeException('El certificado no se entiende.');
    }

    /**
     * El emisor (Name) y el número de serie del certificado, tal cual vienen
     * en su DER: así se repiten exactos en la firma.
     *
     * @return array{0: string, 1: string}
     */
    private static function emisorYSerie(string $der): array
    {
        $cert = self::leer($der, 0);
        $tbs = self::leer($der, $cert['contenido']);
        $pos = $tbs['contenido'];

        $campo = self::leer($der, $pos);
        if ($campo['etiqueta'] === 0xA0) { // la versión, si viene
            $pos = $campo['fin'];
            $campo = self::leer($der, $pos);
        }
        $serie = substr($der, $pos, $campo['fin'] - $pos);
        $pos = $campo['fin'];
        $pos = self::leer($der, $pos)['fin']; // el algoritmo
        $emisor = self::leer($der, $pos);

        return [substr($der, $pos, $emisor['fin'] - $pos), $serie];
    }

    /** @return array{etiqueta: int, contenido: int, fin: int} */
    private static function leer(string $der, int $pos): array
    {
        $etiqueta = ord($der[$pos] ?? "\0");
        $largo = ord($der[$pos + 1] ?? "\0");
        $cabecera = 2;
        if ($largo & 0x80) {
            $bytes = $largo & 0x7F;
            $largo = 0;
            for ($i = 0; $i < $bytes; $i++) {
                $largo = ($largo << 8) | ord($der[$pos + 2 + $i]);
            }
            $cabecera += $bytes;
        }

        return ['etiqueta' => $etiqueta, 'contenido' => $pos + $cabecera, 'fin' => $pos + $cabecera + $largo];
    }

    // ── DER ───────────────────────────────────────────────────────────

    private static function tlv(int $etiqueta, string $contenido): string
    {
        $n = strlen($contenido);
        if ($n < 0x80) {
            return chr($etiqueta) . chr($n) . $contenido;
        }
        $bytes = ltrim(pack('N', $n), "\0");

        return chr($etiqueta) . chr(0x80 | strlen($bytes)) . $bytes . $contenido;
    }

    private static function seq(string ...$partes): string
    {
        return self::tlv(0x30, implode('', $partes));
    }

    private static function set(string ...$partes): string
    {
        sort($partes, SORT_STRING);

        return self::tlv(0x31, implode('', $partes));
    }

    private static function entero(int $n): string
    {
        return self::tlv(0x02, chr($n));
    }

    private static function oid(string $oid): string
    {
        $partes = array_map('intval', explode('.', $oid));
        $salida = chr(40 * $partes[0] + $partes[1]);
        foreach (array_slice($partes, 2) as $n) {
            $trozo = chr($n & 0x7F);
            while ($n >>= 7) {
                $trozo = chr(0x80 | ($n & 0x7F)) . $trozo;
            }
            $salida .= $trozo;
        }

        return self::tlv(0x06, $salida);
    }
}
