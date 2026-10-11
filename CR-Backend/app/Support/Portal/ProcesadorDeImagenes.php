<?php

namespace App\Support\Portal;

use App\Models\Portal\Imagen;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lo que le pasa a una imagen subida desde el panel antes de llegar al portal.
 *
 *  1. Que sea de verdad una imagen: se abre con GD. La extensión y el tipo que
 *     dice el navegador no cuentan; un .jpg que no es un JPEG no se abre.
 *     Solo JPEG, PNG y WebP. Nada de SVG (puede llevar JavaScript) ni GIF.
 *  2. Derecha: las fotos de celular vienen de costado y una marca EXIF dice
 *     cómo girarlas. Se giran ANTES de soltar esa marca.
 *  3. Sin metadatos: se re-codifica de cero, así que no pasa nada del
 *     original —ni la ubicación (GPS) ni el modelo del celular ni la fecha—.
 *  4. Tamaño y peso: el lado más largo hasta 2560 px, y se busca quedar en
 *     los 400 KB que recomienda el contrato bajando la calidad y, si no
 *     alcanza, el tamaño (nunca por debajo del mínimo de su uso).
 *  5. Siempre WebP: pesa menos que JPEG y conserva la transparencia de los
 *     recortes, así que un solo formato sirve para todo.
 *  6. Nombre nuevo: uuid7 en minúsculas. El del archivo original no se usa
 *     (puede traer tildes, espacios o el nombre de un alumno).
 *
 * Devuelve la fila de portal_imagenes y los avisos para quien la subió
 * (más chica de lo recomendado, otra proporción, sin transparencia…): no
 * impiden guardar, pero conviene que los lea.
 */
class ProcesadorDeImagenes
{
    /** Más píxeles que esto no se abre: una "bomba" de 1 KB puede pedir gigas de memoria. */
    private const PIXELES_MAXIMOS = 40_000_000;

    private const TIPOS = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
    private const CALIDADES = [82, 74, 66, 58];

    /**
     * @return array{0: Imagen, 1: string[]} la imagen guardada y los avisos
     */
    public function procesar(UploadedFile $archivo, string $uso, string $alt): array
    {
        $especificacion = UsosDeImagen::de($uso);
        $imagen = $this->abrir($archivo->getRealPath());

        $original = [imagesx($imagen), imagesy($imagen)];
        $transparente = $this->tieneTransparencia($imagen);
        $imagen = $this->achicar($imagen, UsosDeImagen::LADO_MAXIMO);

        [$bytes, $imagen] = $this->comprimir($imagen, $especificacion['minimo']);

        $ruta = Str::lower((string) Str::uuid7()) . '.webp';
        Storage::disk('portal')->put($ruta, $bytes);

        $fila = Imagen::create([
            'ruta'  => $ruta,
            'alt'   => $alt,
            'ancho' => imagesx($imagen),
            'alto'  => imagesy($imagen),
            'mime'  => 'image/webp',
            'bytes' => strlen($bytes),
        ]);

        return [$fila, $this->avisos($especificacion, $original, $transparente, strlen($bytes), $alt, $uso)];
    }

    /** La abre con GD, o explica por qué no es una imagen que se pueda usar. */
    private function abrir(string $ruta): GdImage
    {
        $info = @getimagesize($ruta);

        if ($info === false || ! in_array($info[2], self::TIPOS, true)) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no es una imagen JPEG, PNG o WebP. Si es una foto, guárdala en uno de esos formatos y vuelve a subirla.',
            ]);
        }

        if ($info[0] * $info[1] > self::PIXELES_MAXIMOS) {
            throw ValidationException::withMessages([
                'archivo' => 'La imagen es demasiado grande (' . $info[0] . ' × ' . $info[1] . ' píxeles). Achícala a menos de 6000 px de ancho.',
            ]);
        }

        $imagen = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($ruta),
            IMAGETYPE_PNG  => @imagecreatefrompng($ruta),
            IMAGETYPE_WEBP => @imagecreatefromwebp($ruta),
        };

        if (! $imagen instanceof GdImage) {
            throw ValidationException::withMessages(['archivo' => 'La imagen está dañada y no se pudo abrir.']);
        }

        if (! imageistruecolor($imagen)) {
            imagepalettetotruecolor($imagen);
        }
        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);

        return $info[2] === IMAGETYPE_JPEG ? $this->enderezar($imagen, $ruta) : $imagen;
    }

    /**
     * Gira la foto como dice su marca EXIF de orientación (1 a 8). Sin la
     * extensión exif de PHP no se puede leer: la foto queda como viene.
     */
    private function enderezar(GdImage $imagen, string $ruta): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $imagen;
        }

        $orientacion = (int) (@exif_read_data($ruta)['Orientation'] ?? 1);

        // imagerotate gira en sentido contrario a las agujas del reloj.
        [$grados, $espejo] = match ($orientacion) {
            2 => [0, IMG_FLIP_HORIZONTAL],
            3 => [180, null],
            4 => [0, IMG_FLIP_VERTICAL],
            5 => [270, IMG_FLIP_HORIZONTAL],
            6 => [270, null],
            7 => [90, IMG_FLIP_HORIZONTAL],
            8 => [90, null],
            default => [0, null],
        };

        if ($grados !== 0) {
            $imagen = imagerotate($imagen, $grados, 0);
        }
        if ($espejo !== null) {
            imageflip($imagen, $espejo);
        }

        return $imagen;
    }

    /** Si algún píxel tiene transparencia (se mira una rejilla, no todos). */
    private function tieneTransparencia(GdImage $imagen): bool
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $paso = max(1, (int) (min($ancho, $alto) / 64));

        for ($x = 0; $x < $ancho; $x += $paso) {
            for ($y = 0; $y < $alto; $y += $paso) {
                if ((imagecolorat($imagen, $x, $y) >> 24) & 0x7F) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Lleva el lado más largo a $lado como máximo, sin perder la transparencia. */
    private function achicar(GdImage $imagen, int $lado): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $factor = $lado / max($ancho, $alto);

        return $factor >= 1 ? $imagen : $this->escalar($imagen, $factor);
    }

    private function escalar(GdImage $imagen, float $factor): GdImage
    {
        $ancho = max(1, (int) round(imagesx($imagen) * $factor));
        $alto = max(1, (int) round(imagesy($imagen) * $factor));

        $nueva = imagecreatetruecolor($ancho, $alto);
        imagealphablending($nueva, false);
        imagesavealpha($nueva, true);
        imagefill($nueva, 0, 0, imagecolorallocatealpha($nueva, 0, 0, 0, 127));
        imagecopyresampled($nueva, $imagen, 0, 0, 0, 0, $ancho, $alto, imagesx($imagen), imagesy($imagen));

        return $nueva;
    }

    /**
     * Busca quedar en el peso recomendado: primero baja la calidad; si no
     * alcanza, el tamaño, sin pasar del mínimo de su uso. Si ni así, se
     * guarda la más liviana que se logró (y el aviso lo dice).
     *
     * @return array{0: string, 1: GdImage}
     */
    private function comprimir(GdImage $imagen, array $minimo): array
    {
        for ($intento = 0; $intento < 5; $intento++) {
            foreach (self::CALIDADES as $calidad) {
                $bytes = $this->webp($imagen, $calidad);

                if (strlen($bytes) <= UsosDeImagen::PESO_RECOMENDADO) {
                    return [$bytes, $imagen];
                }
            }

            $siguiente = 0.85;
            if (imagesx($imagen) * $siguiente < $minimo[0] || imagesy($imagen) * $siguiente < $minimo[1]) {
                break;
            }
            $imagen = $this->escalar($imagen, $siguiente);
        }

        return [$bytes, $imagen];
    }

    private function webp(GdImage $imagen, int $calidad): string
    {
        ob_start();
        imagewebp($imagen, null, $calidad);

        return (string) ob_get_clean();
    }

    /** @return string[] */
    private function avisos(array $esp, array $original, bool $transparente, int $bytes, string $alt, string $uso): array
    {
        $avisos = [];
        [$ancho, $alto] = $original;
        [$minAncho, $minAlto] = $esp['minimo'];

        if ($ancho < $minAncho || $alto < $minAlto) {
            $avisos[] = "Es más chica de lo recomendado para {$esp['nombre']} ({$ancho} × {$alto}; se recomienda al menos {$minAncho} × {$minAlto}). En pantallas grandes se verá borrosa.";
        }

        if ($esp['proporcion'] !== null) {
            [$pAncho, $pAlto] = $esp['proporcion'];
            $esperada = $pAncho / $pAlto;

            if (abs(($ancho / $alto) - $esperada) / $esperada > 0.03) {
                $avisos[] = $esp['foco']
                    ? 'No tiene la proporción del espacio: el portal la recortará alrededor del punto que elijas en la vista previa.'
                    : 'No tiene la proporción del espacio: el portal la recortará centrada. Revisa la vista previa.';
            }
        }

        if ($esp['transparente'] && ! $transparente) {
            $avisos[] = 'Este espacio espera un recorte con fondo transparente (una persona sin fondo). Esta imagen tiene fondo.';
        }

        if ($bytes > UsosDeImagen::PESO_RECOMENDADO) {
            $avisos[] = 'Aun comprimida pesa ' . round($bytes / 1024) . ' KB (lo recomendado es hasta 400 KB): la página tardará más en cargar.';
        }

        if ($alt !== '' && mb_strlen($alt) > 150 && ! in_array($uso, ['banner', 'banner-movil'], true)) {
            $avisos[] = 'El texto alternativo es largo (' . mb_strlen($alt) . ' caracteres; lo recomendado es hasta 150).';
        }

        return $avisos;
    }
}
