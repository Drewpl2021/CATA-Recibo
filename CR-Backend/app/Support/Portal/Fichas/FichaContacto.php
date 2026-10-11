<?php

namespace App\Support\Portal\Fichas;

use App\Support\Portal\ImagenesEnJson;
use Illuminate\Validation\Rule;

/**
 * La cabecera de la página Contacto (contrato §3.16): título, frase, el
 * recorte de un estudiante y el correo general. Las sedes son su propia
 * lista; el WhatsApp y las redes salen de los datos del colegio.
 */
class FichaContacto implements Ficha
{
    public function nombre(): string
    {
        return 'la cabecera de Contacto';
    }

    public function reglas(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:60'],
            'bajada' => ['nullable', 'string', 'max:160'],
            // El id de una imagen ya subida (POST /web-portal/images, uso «recorte»).
            'figura' => ['nullable', 'uuid', Rule::exists('portal_imagenes', 'id')],
            'correo' => ['nullable', 'email', 'max:80'],
        ];
    }

    public function atributos(): array
    {
        return [
            'titulo' => 'título',
            'bajada' => 'frase bajo el título',
            'figura' => 'recorte del estudiante',
            'correo' => 'correo general',
        ];
    }

    public function aContenido(array $v): array
    {
        return [
            'titulo' => $v['titulo'],
            'bajada' => $v['bajada'] ?? null,
            'figura' => filled($v['figura'] ?? null) ? [ImagenesEnJson::CLAVE => $v['figura']] : null,
            'correo' => $v['correo'] ?? null,
        ];
    }

    public function rutas(): array
    {
        return ['/v1/contacto'];
    }
}
