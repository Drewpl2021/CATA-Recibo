<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Http\Controllers\Controller;
use App\Models\Portal\Imagen;
use App\Support\Portal\ProcesadorDeImagenes;
use App\Support\Portal\UsosDeImagen;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Las imágenes del portal, subidas desde el panel.
 *
 *   GET   /web-portal/images/uses  → para qué se sube cada una y qué se espera
 *   POST  /web-portal/images       → subir (archivo + uso + texto alternativo)
 *   PATCH /web-portal/images/{id}  → cambiar el texto alternativo
 *
 * Subir solo crea la imagen: queda suelta hasta que un formulario la usa
 * (la foto de una sede, el afiche de un banner). Las que nadie usa se borran
 * con `portal:limpiar-imagenes`.
 *
 * El texto alternativo es obligatorio (contrato §1.7, WCAG 2.2 AA): describe
 * lo que muestra la imagen. Solo puede quedar vacío si se marca como
 * decorativa, y nunca puede ser «foto» ni el nombre de un archivo.
 */
class ImagenesController extends Controller
{
    public function usos(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => UsosDeImagen::USOS]);
    }

    public function subir(Request $peticion, ProcesadorDeImagenes $procesador): JsonResponse
    {
        $validado = $peticion->validate([
            // 10 MB: una foto de celular sin tocar. Se achica al procesarla.
            'archivo'    => ['required', 'file', 'max:10240'],
            'uso'        => ['required', Rule::in(UsosDeImagen::claves())],
            ...$this->reglasDelAlt(),
        ], $this->mensajes());

        [$imagen, $avisos] = $procesador->procesar($validado['archivo'], $validado['uso'], $this->alt($validado));

        return response()->json(['success' => true, 'data' => [
            'imagen' => $this->aPanel($imagen),
            'avisos' => $avisos,
        ]], 201);
    }

    public function editar(Request $peticion, string $id): JsonResponse
    {
        $imagen = Imagen::findOrFail($id);
        $validado = $peticion->validate($this->reglasDelAlt(), $this->mensajes());

        $imagen->update(['alt' => $this->alt($validado)]);

        return response()->json(['success' => true, 'data' => ['imagen' => $this->aPanel($imagen), 'avisos' => []]]);
    }

    private function reglasDelAlt(): array
    {
        return [
            'decorativa' => ['sometimes', 'boolean'],
            'alt'        => [
                'exclude_if:decorativa,true,1', 'required', 'string', 'max:1000',
                // Lo que el contrato pone como ejemplo de lo que NO es un alt.
                function (string $atributo, mixed $valor, Closure $falla) {
                    $texto = mb_strtolower(trim((string) $valor));

                    if (in_array($texto, ['foto', 'imagen', 'image', 'img', 'fotografía', 'logo'], true)
                        || preg_match('/\.(jpe?g|png|webp|gif|svg)$/', $texto)) {
                        $falla('Describe lo que muestra la imagen y por qué importa, no su nombre ni la palabra «foto».');
                    }
                },
            ],
        ];
    }

    private function mensajes(): array
    {
        return [
            'alt.required' => 'Escribe el texto alternativo: lo que muestra la imagen, para quien no puede verla. Si es solo decorativa, márcala como tal.',
            'archivo.max'  => 'La imagen pesa más de 10 MB.',
        ];
    }

    /** Decorativa → alt vacío: el portal la oculta a los lectores de pantalla. */
    private function alt(array $validado): string
    {
        return ($validado['decorativa'] ?? false) ? '' : trim((string) ($validado['alt'] ?? ''));
    }

    private function aPanel(Imagen $imagen): array
    {
        return [
            'id'    => $imagen->id,
            'url'   => $imagen->url(),
            'alt'   => $imagen->alt,
            'ancho' => $imagen->ancho,
            'alto'  => $imagen->alto,
            'bytes' => $imagen->bytes,
        ];
    }
}
