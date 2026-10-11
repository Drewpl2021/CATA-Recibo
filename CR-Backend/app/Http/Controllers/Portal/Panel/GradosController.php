<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\GrupoGrados;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * /web-portal/grade-options — las opciones del selector de grado del
 * formulario «Solicitar información» (contrato §3.8), agrupadas por nivel.
 *
 * El contrato no les da id: cada grupo se reconoce por su nivel, que por eso
 * no se repite. Cada uno lleva de 1 a 8 opciones, sin repetir.
 */
class GradosController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return GrupoGrados::class;
    }

    protected function entidad(): string
    {
        return 'el nivel';
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/grados'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';

        return [
            'nivel'      => [$obligatorio, 'string', 'max:24', Rule::unique('portal_grados', 'nivel')->ignore($fila?->getKey())],
            'opciones'   => [$obligatorio, 'array', 'min:1', 'max:8'],
            'opciones.*' => ['required', 'string', 'max:24', 'distinct:ignore_case'],
        ];
    }

    protected function atributos(): array
    {
        return ['nivel' => 'nivel', 'opciones' => 'opciones', 'opciones.*' => 'opción'];
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (array_key_exists('opciones', $v)) {
            $v['opciones'] = array_values($v['opciones']);
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'nivel', 'opciones', 'orden', 'estado']);
    }
}
