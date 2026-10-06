<?php

namespace App\Support;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Un código QR como imagen PNG lista para el `src` de un <img> (data URI).
 *
 * Se dibuja aquí mismo, sin pedírselo a ningún servicio de internet: la
 * boleta lleva sueldo y DNI, y la dirección que va dentro del QR no debe
 * salir del servidor.
 */
final class CodigoQr
{
    public static function png(string $texto): string
    {
        $opciones = new QROptions([
            'outputType'   => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => true,
            'scale'        => 6,
            'quietzoneSize' => 1,
        ]);

        return (new QRCode($opciones))->render($texto);
    }
}
