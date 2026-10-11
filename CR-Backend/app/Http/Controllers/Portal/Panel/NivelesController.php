<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Imagen;
use App\Models\Portal\Nivel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * /web-portal/home-levels — los niveles de Inicio (contrato §3.5), hasta 4.
 *
 * Son una lista propia, no la de Propuesta Educativa ni la de Docentes: en
 * el portal cambian el nombre, el rango, la foto y hasta qué niveles salen.
 */
class NivelesController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return Nivel::class;
    }

    protected function entidad(): string
    {
        return 'el nivel';
    }

    protected function topePublicados(): ?int
    {
        return 4;
    }

    protected function plural(): string
    {
        return 'niveles publicados';
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/niveles'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';

        return [
            'nombre'         => [$obligatorio, 'string', 'max:24'],
            'rango'          => ['nullable', 'string', 'max:40'],
            'sede'           => ['nullable', 'string', 'max:60'],
            'reconocimiento' => ['nullable', 'string', 'max:60'],
            'imagen_id'      => ['nullable', 'uuid', Rule::exists('portal_imagenes', 'id')],
        ];
    }

    protected function atributos(): array
    {
        return ['nombre' => 'nombre', 'rango' => 'rango', 'sede' => 'sede', 'reconocimiento' => 'reconocimiento', 'imagen_id' => 'foto'];
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (! $fila) {
            $v['clave'] = $this->claveUnica($v['nombre'], 'nivel');
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'clave', 'nombre', 'rango', 'sede', 'reconocimiento', 'imagen_id', 'orden', 'estado']) + [
            'imagen' => $fila->imagen_id ? Imagen::find($fila->imagen_id)?->aPanel() : null,
        ];
    }
}
