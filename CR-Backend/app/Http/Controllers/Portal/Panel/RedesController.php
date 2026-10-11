<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\RedSocial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * /web-portal/social-networks — las redes sociales oficiales del colegio
 * (contrato §3.1, `redes`). Cada red una sola vez; la dirección siempre
 * https, y la de WhatsApp, un enlace https://wa.me/….
 */
class RedesController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return RedSocial::class;
    }

    protected function entidad(): string
    {
        return 'la red social';
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/sitio'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';
        $red = $peticion->input('red', $fila?->red);

        return [
            'red'        => [$obligatorio, Rule::in(RedSocial::REDES), Rule::unique('portal_redes', 'red')->ignore($fila?->getKey())],
            'cuenta'     => [$obligatorio, 'string', 'max:60'],
            'url'        => [
                $obligatorio, 'string', 'max:255', 'url', 'starts_with:https://',
                // El portal arma el botón de WhatsApp con esta dirección.
                ...($red === 'whatsapp' ? ['starts_with:https://wa.me/'] : []),
            ],
            'aclaracion' => ['nullable', 'string', 'max:80'],
        ];
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'red', 'cuenta', 'url', 'aclaracion', 'orden', 'estado']);
    }
}
