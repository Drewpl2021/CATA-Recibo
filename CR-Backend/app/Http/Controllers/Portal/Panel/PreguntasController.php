<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Pregunta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * /web-portal/faqs — las preguntas frecuentes de Matrícula (contrato §3.14).
 * La respuesta es texto plano: sin HTML ni Markdown.
 */
class PreguntasController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return Pregunta::class;
    }

    protected function entidad(): string
    {
        return 'la pregunta';
    }

    protected function topePublicados(): ?int
    {
        return 15;
    }

    protected function plural(): string
    {
        return 'preguntas publicadas';
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/matricula/preguntas'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';

        return [
            'pregunta'  => [$obligatorio, 'string', 'max:140'],
            'respuesta' => [$obligatorio, 'string', 'max:600'],
        ];
    }

    protected function atributos(): array
    {
        return ['pregunta' => 'pregunta', 'respuesta' => 'respuesta'];
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (! $fila) {
            $v['clave'] = $this->claveUnica($v['pregunta'], 'pregunta');
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'clave', 'pregunta', 'respuesta', 'orden', 'estado']);
    }
}
