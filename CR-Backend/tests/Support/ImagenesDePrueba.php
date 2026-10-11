<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

/**
 * Imágenes fabricadas para probar la subida del portal, cada una con la
 * trampa que hay que desarmar.
 */
final class ImagenesDePrueba
{
    /** El modelo de celular que va en el EXIF: si aparece en lo guardado, no se limpió. */
    public const MARCA_DEL_CELULAR = 'CelularDePrueba';

    /**
     * Un JPEG como los de un celular: con la marca de orientación (6 = hay
     * que girarlo 90° a la derecha), el modelo del teléfono y una posición GPS.
     */
    public static function jpegDeCelular(int $ancho, int $alto, int $orientacion = 6): UploadedFile
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 30, 90, 160));
        // Una marca arriba a la izquierda, para ver hacia dónde quedó.
        imagefilledrectangle($imagen, 0, 0, (int) ($ancho / 4), (int) ($alto / 4), imagecolorallocate($imagen, 250, 200, 0));

        ob_start();
        imagejpeg($imagen, null, 90);
        $jpeg = ob_get_clean();

        // El EXIF va justo después del inicio del JPEG (FF D8).
        $conExif = substr($jpeg, 0, 2) . self::segmentoExif($orientacion) . substr($jpeg, 2);

        return self::archivo('IMG_2034.JPG', $conExif);
    }

    /** Un PNG con fondo transparente y una figura opaca al centro: un «recorte». */
    public static function pngTransparente(int $ancho, int $alto): UploadedFile
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);
        imagefill($imagen, 0, 0, imagecolorallocatealpha($imagen, 0, 0, 0, 127));
        imagefilledellipse($imagen, (int) ($ancho / 2), (int) ($alto / 2), (int) ($ancho / 2), (int) ($alto / 2), imagecolorallocatealpha($imagen, 27, 66, 130, 0));

        ob_start();
        imagepng($imagen);

        return self::archivo('recorte.png', ob_get_clean());
    }

    /** Ruido de colores: lo que más cuesta comprimir. */
    public static function jpegRuidoso(int $ancho, int $alto): UploadedFile
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        mt_srand(7);
        for ($y = 0; $y < $alto; $y += 2) {
            for ($x = 0; $x < $ancho; $x += 2) {
                imagefilledrectangle($imagen, $x, $y, $x + 1, $y + 1, (mt_rand(0, 255) << 16) | (mt_rand(0, 255) << 8) | mt_rand(0, 255));
            }
        }

        ob_start();
        imagejpeg($imagen, null, 95);

        return self::archivo('ruido.jpg', ob_get_clean());
    }

    public static function archivo(string $nombre, string $contenido): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($ruta, $contenido);

        return new UploadedFile($ruta, $nombre, null, null, true);
    }

    /**
     * Un segmento APP1 con EXIF mínimo, en little-endian: orientación,
     * fabricante y un bloque GPS con latitud.
     */
    private static function segmentoExif(int $orientacion): string
    {
        $entrada = fn (int $tag, int $tipo, int $cuenta, string $valor) => pack('vvV', $tag, $tipo, $cuenta) . str_pad($valor, 4, "\0");

        $marca = self::MARCA_DEL_CELULAR . "\0";         // 16 bytes
        $ifd0 = 8;                                         // donde empieza IFD0
        $finIfd0 = $ifd0 + 2 + 3 * 12 + 4;                 // 50
        $posMarca = $finIfd0;                              // 50
        $posGps = $posMarca + strlen($marca);              // 66
        $posRacionales = $posGps + 2 + 2 * 12 + 4;         // 96

        $tiff = 'II' . pack('vV', 42, $ifd0)
            . pack('v', 3)
            . $entrada(0x0112, 3, 1, pack('v', $orientacion))      // Orientation
            . $entrada(0x010F, 2, strlen($marca), pack('V', $posMarca)) // Make
            . $entrada(0x8825, 4, 1, pack('V', $posGps))           // puntero al GPS
            . pack('V', 0)
            . $marca
            . pack('v', 2)
            . $entrada(0x0001, 2, 2, "S\0")                         // GPSLatitudeRef
            . $entrada(0x0002, 5, 3, pack('V', $posRacionales))     // GPSLatitude
            . pack('V', 0)
            . pack('V*', 15, 1, 29, 1, 0, 1);                      // 15° 29' 0"

        $datos = "Exif\0\0" . $tiff;

        return "\xFF\xE1" . pack('n', strlen($datos) + 2) . $datos;
    }
}
