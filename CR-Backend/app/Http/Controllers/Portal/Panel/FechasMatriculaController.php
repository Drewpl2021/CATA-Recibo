<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\FechaMatricula;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * /web-portal/enrollment-dates — el cronograma de matrícula (contrato §3.13).
 *
 * Va en orden cronológico, siempre: el portal las muestra por su fecha de
 * inicio, así que aquí no se ordenan a mano (no hay ruta de orden). El portal
 * marca «En curso» o «Terminó» según la fecha de hoy en Lima.
 */
class FechasMatriculaController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return FechaMatricula::class;
    }

    protected function entidad(): string
    {
        return 'la fecha';
    }

    protected function topePublicados(): ?int
    {
        return 12;
    }

    protected function plural(): string
    {
        return 'fechas publicadas';
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/matricula/fechas'];
    }

    /** Como las ve el portal: por fecha de inicio. */
    protected function consulta(Request $peticion): Builder
    {
        return FechaMatricula::query()->orderBy('inicio')->orderBy('orden');
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';
        // «Hasta» no puede ser antes de «desde»: el que llega o, si no llega, el guardado.
        $desde = $peticion->has('inicio') || ! $fila ? 'inicio' : $fila->inicio->format('Y-m-d');

        return [
            'titulo'  => [$obligatorio, 'string', 'max:80'],
            'inicio'  => [$obligatorio, 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            // null = un solo día.
            'fin'     => ['nullable', 'date_format:Y-m-d', "after_or_equal:{$desde}", 'before_or_equal:2100-12-31'],
            'detalle' => ['nullable', 'string', 'max:160'],
        ];
    }

    protected function atributos(): array
    {
        return ['titulo' => 'qué pasa', 'inicio' => 'desde', 'fin' => 'hasta', 'detalle' => 'detalle'];
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (! $fila) {
            $v['clave'] = $this->claveUnica($v['titulo'], 'fecha');
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'clave', 'titulo', 'detalle', 'orden', 'estado']) + [
            'inicio' => $fila->inicio?->format('Y-m-d'),
            'fin'    => $fila->fin?->format('Y-m-d'),
        ];
    }
}
