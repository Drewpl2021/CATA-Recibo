<?php

namespace Tests\Support;

/**
 * Firma un PDF como lo hace un firmador PAdES (ReFirma, Adobe): agrega al
 * final un diccionario de firma con su /ByteRange y su /Contents, sin tocar
 * lo que ya estaba. Con un certificado de prueba hecho al vuelo.
 *
 * No es un PDF que Adobe vaya a validar (no actualiza el AcroForm), pero sí
 * es exactamente lo que App\Support\FirmaDigitalPdf lee y comprueba.
 */
final class FirmadorPdfDePrueba
{
    private const HUECO = 16384; // caracteres hex reservados para la firma

    /** @return array{0: string, 1: \OpenSSLAsymmetricKey} certificado PEM y su llave */
    public static function certificado(string $nombre, string $apellido, string $dni): array
    {
        $llave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new([
            'commonName'   => "{$apellido} {$nombre} FIR {$dni} hard",
            'givenName'    => $nombre,
            'surname'      => $apellido,
            'serialNumber' => "PNOPE-{$dni}",
            'countryName'  => 'PE',
        ], $llave, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $llave, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($cert, $pem);

        return [$pem, $llave];
    }

    /** El PDF con una firma más al final. */
    public static function firmar(string $pdf, string $certificado, $llave, string $fecha = '20261108103000'): string
    {
        $relleno = str_repeat('0', self::HUECO);
        $marcador = '[0 ########## ########## ##########]';
        $diccionario = "\n9999 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached"
            . " /M (D:{$fecha}-05'00') /ByteRange {$marcador} /Contents <{$relleno}> >>\nendobj\n%%EOF\n";

        $todo = $pdf . $diccionario;
        $b = strpos($todo, '/Contents <', strlen($pdf)) + strlen('/Contents ');
        $c = $b + self::HUECO + 2;
        $d = strlen($todo) - $c;
        $rango = sprintf('[0 %10d %10d %10d]', $b, $c, $d);
        $todo = substr_replace($todo, $rango, strpos($todo, $marcador, strlen($pdf)), strlen($marcador));

        $datos = substr($todo, 0, $b) . substr($todo, $c);
        $entrada = tempnam(sys_get_temp_dir(), 'firmar');
        $salida = tempnam(sys_get_temp_dir(), 'firmado');
        file_put_contents($entrada, $datos);
        openssl_cms_sign($entrada, $salida, $certificado, $llave, [], OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY, OPENSSL_ENCODING_DER);
        $hex = strtoupper(bin2hex((string) file_get_contents($salida)));
        @unlink($entrada);
        @unlink($salida);

        return substr_replace($todo, str_pad($hex, self::HUECO, '0'), $b + 1, self::HUECO);
    }
}
