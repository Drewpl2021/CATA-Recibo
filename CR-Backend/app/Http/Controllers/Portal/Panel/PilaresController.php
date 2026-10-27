<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Pilar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * /web-portal/pillars — los pilares de Propuesta Educativa (contrato
 * §3.23), hasta 8. Cada uno con su texto y, si los tiene, sus aspectos
 * («Espiritual», «Mental»…).
 */
class PilaresController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return Pilar::class;
    }

    protected function entidad(): string
    {
        return 'el pilar';
    }

    protected function topePublicados(): ?int
    {
        return 8;
    }

    protected function plural(): string
    {
        return 'pilares publicados';
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/propuesta-educativa/pilares'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';

        return [
            'titulo'   => [$obligatorio, 'string', 'max:60'],
            'texto'    => [$obligatorio, 'string', 'max:400'],
            'puntos'   => ['sometimes', 'array', 'max:8'],
            'puntos.*' => ['required', 'string', 'max:30', 'distinct'],
        ];
    }

    protected function atributos(): array
    {
        return ['titulo' => 'título', 'texto' => 'texto', 'puntos' => 'aspectos', 'puntos.*' => 'aspecto'];
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (array_key_exists('puntos', $v)) {
            $v['puntos'] = array_values($v['puntos']);
        }

        if (! $fila) {
            $v['clave'] = $this->claveUnica($v['titulo'], 'pilar');
            $v['puntos'] ??= [];
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'clave', 'titulo', 'texto', 'puntos', 'orden', 'estado']);
    }
}
