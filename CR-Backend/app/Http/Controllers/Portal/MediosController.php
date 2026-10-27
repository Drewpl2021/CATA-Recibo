<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Imagen;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /api/portal/medios/{archivo}: las imágenes del portal subidas desde el
 * panel. Es la dirección que arma Imagen::url() para las que no son externas.
 *
 * Se sirven desde el mismo dominio que el sistema de RR.HH., donde está la
 * sesión del personal. Por eso esto es estricto:
 *
 *   - Solo JPEG, PNG, WebP y GIF. Nada de SVG: puede llevar JavaScript, y
 *     abierto directamente correría con los permisos de este dominio.
 *   - El tipo sale de la extensión, de esta lista fija, y no de lo que diga
 *     el archivo; con nosniff el navegador no lo reinterpreta, y la CSP
 *     sandbox no deja ejecutar nada aunque alguien lo intente.
 *   - Solo los archivos registrados en portal_imagenes, con nombre plano
 *     (sin carpetas: no hay `../` por donde salir del disco). Un archivo
 *     que llegue al disco por otra vía no queda público.
 *
 * Los nombres no se reutilizan (cada subida tiene el suyo), así que el
 * navegador y cualquier proxy pueden guardarlas un año: `immutable`.
 */
class MediosController extends Controller
{
    /** Extensión → tipo que se declara. Ver la ruta: solo estas llegan aquí. */
    public const TIPOS = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
    ];

    public function ver(string $archivo): BinaryFileResponse
    {
        $disco = Storage::disk('portal');

        if (! Imagen::where('ruta', $archivo)->exists() || ! $disco->exists($archivo)) {
            throw new NotFoundHttpException();
        }

        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));

        return response()->file($disco->path($archivo), [
            'Content-Type'                 => self::TIPOS[$extension],
            'Cache-Control'                => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options'       => 'nosniff',
            'Content-Security-Policy'      => "default-src 'none'; sandbox",
            // El portal está en otro dominio (cata.edu.pe) y las muestra con <img>.
            'Cross-Origin-Resource-Policy' => 'cross-origin',
        ]);
    }
}
