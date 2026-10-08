<?php

namespace App\Support;

use RuntimeException;

/**
 * Abre un certificado digital en archivo (.pfx / .p12) con su clave y dice
 * de quién es y si sirve para firmar. Cada problema sale con un mensaje que
 * le dice a la persona qué hacer.
 *
 * Lo que comprueba:
 *   - que la clave abra el archivo y que traiga la llave privada;
 *   - que esa llave sea la del certificado;
 *   - que esté vigente hoy;
 *   - que sirva para firmar (no uno solo de autenticación o de cifrado);
 *   - que lo haya emitido otra entidad (no uno hecho en casa).
 *
 * Lo que NO puede comprobar aquí es que la entidad que lo emitió esté
 * acreditada ante INDECOPI ni que el certificado no esté revocado: eso lo
 * muestra Adobe o el validador de RENIEC al abrir la boleta. Por eso la
 * pantalla enseña quién lo emitió, para que la persona lo confirme.
 */
final class CertificadoDigital
{
    /**
     * @return array{
     *     certificado: string, llave: \OpenSSLAsymmetricKey, cadena: list<string>,
     *     nombre: string, dni: ?string, organizacion: ?string, ruc: ?string, emisor: ?string,
     *     serie: string, huella: string, desde: ?\DateTimeImmutable, hasta: ?\DateTimeImmutable
     * }
     */
    public static function abrir(string $pfx, string $clave, bool $exigirVigente = true): array
    {
        while (openssl_error_string() !== false) {
            // Errores viejos de OpenSSL: que no se mezclen con los de este intento.
        }

        if (! @openssl_pkcs12_read($pfx, $partes, $clave)) {
            $errores = '';
            while (($e = openssl_error_string()) !== false) {
                $errores .= $e . ' ';
            }
            throw new RuntimeException(match (true) {
                str_contains($errores, 'mac verify failure'),
                str_contains($errores, 'bad decrypt') => 'La clave del certificado no es correcta.',
                str_contains($errores, 'unsupported') => 'El certificado usa un cifrado que este servidor no puede abrir. Avisa a soporte.',
                default => 'No es un certificado digital en archivo (.pfx o .p12), o el archivo está dañado.',
            });
        }

        $certificado = $partes['cert'] ?? '';
        $llaveTexto = $partes['pkey'] ?? '';
        if ($certificado === '' || $llaveTexto === '') {
            throw new RuntimeException('El archivo no trae la llave privada: así no se puede firmar. Pide a la entidad el certificado completo en .pfx.');
        }
        $llave = openssl_pkey_get_private($llaveTexto);
        if ($llave === false || ! openssl_x509_check_private_key($certificado, $llave)) {
            throw new RuntimeException('La llave del archivo no corresponde a su certificado.');
        }

        $info = openssl_x509_parse($certificado) ?: [];
        $sujeto = $info['subject'] ?? [];
        $emisorDatos = $info['issuer'] ?? [];

        if ($sujeto === $emisorDatos) {
            throw new RuntimeException('Este certificado no lo emitió una entidad de certificación (está hecho en casa o es de prueba): no tiene validez legal.');
        }

        $usos = (string) ($info['extensions']['keyUsage'] ?? '');
        if ($usos !== '' && ! str_contains($usos, 'Digital Signature') && ! str_contains($usos, 'Non Repudiation')) {
            throw new RuntimeException('Este certificado no sirve para firmar documentos (es de otro uso, como autenticarse o cifrar). Pide a la entidad uno de firma.');
        }

        $desde = isset($info['validFrom_time_t']) ? now()->setTimestamp((int) $info['validFrom_time_t'])->toDateTimeImmutable() : null;
        $hasta = isset($info['validTo_time_t']) ? now()->setTimestamp((int) $info['validTo_time_t'])->toDateTimeImmutable() : null;
        $hoy = now()->toDateTimeImmutable();
        if ($exigirVigente && $hasta && $hasta < $hoy) {
            throw new RuntimeException('Este certificado venció el ' . $hasta->format('d/m/Y') . '. Renuévalo con la entidad que lo emitió.');
        }
        if ($exigirVigente && $desde && $desde > $hoy) {
            throw new RuntimeException('Este certificado recién vale desde el ' . $desde->format('d/m/Y') . '.');
        }

        $persona = FirmaDigitalPdf::datosDelFirmante($certificado);
        $uno = fn ($v) => is_array($v) ? implode(' ', $v) : ($v === null ? null : (string) $v);
        $organizacion = $uno($sujeto['O'] ?? null);

        // El RUC del colegio, si el certificado es de persona jurídica: va en
        // organizationIdentifier («VATPE-20…») o dentro de algún campo.
        $ruc = null;
        foreach ([$sujeto['organizationIdentifier'] ?? null, $sujeto['2.5.4.97'] ?? null, $organizacion, $sujeto['OU'] ?? null] as $donde) {
            if ($donde !== null && preg_match('/(?<!\d)((?:10|15|17|20)\d{9})(?!\d)/', $uno($donde), $m)) {
                $ruc = $m[1];
                break;
            }
        }

        openssl_x509_export($certificado, $pem);

        return [
            'certificado'  => $pem,
            'llave'        => $llave,
            'cadena'       => array_values(array_filter((array) ($partes['extracerts'] ?? []))),
            'nombre'       => $persona['nombre'] ?? 'SIN NOMBRE',
            'dni'          => $persona['dni'],
            'organizacion' => $organizacion,
            'ruc'          => $ruc,
            'emisor'       => $persona['emisor'],
            'serie'        => (string) ($info['serialNumberHex'] ?? $info['serialNumber'] ?? ''),
            'huella'       => openssl_x509_fingerprint($certificado, 'sha256') ?: hash('sha256', $pem),
            'desde'        => $desde,
            'hasta'        => $hasta,
        ];
    }
}
