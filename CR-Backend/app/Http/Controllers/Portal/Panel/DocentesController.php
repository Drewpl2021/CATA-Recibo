<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Docente;
use App\Models\Portal\Imagen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * /web-portal/teachers?nivel=… — el personal docente (contrato §3.21), hasta
 * 150 publicados: nombre, cargo, nivel y foto, nada más. No sale de la ficha
 * de RR.HH.: esa tiene datos que nunca deben llegar a internet.
 *
 * El orden cuenta dentro de su nivel, que es como los muestra el portal: se
 * ordena con ?nivel=…, y quien cambia de nivel pasa al final del nuevo.
 */
class DocentesController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return Docente::class;
    }

    protected function entidad(): string
    {
        return 'el docente';
    }

    protected function topePublicados(): ?int
    {
        return 150;
    }

    protected function plural(): string
    {
        return 'docentes publicados';
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/docentes'];
    }

    /** Los de un nivel (?nivel=id), o el del docente que se crea, para ponerlo al final de los suyos. */
    protected function consulta(Request $peticion): Builder
    {
        $nivel = $peticion->query('nivel')
            ?? ($peticion->routeIs('*.docentes.store') ? $peticion->input('nivel_id') : null);

        return Docente::query()
            ->when(is_string($nivel), fn ($c) => $c->where('nivel_id', $nivel))
            ->orderBy('orden');
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';

        return [
            'nombre'    => [$obligatorio, 'string', 'max:80'],
            'cargo'     => [$obligatorio, 'string', 'max:80'],
            'nivel_id'  => [$obligatorio, 'uuid', Rule::exists('portal_docentes_niveles', 'id')],
            // Cuadrada, con la autorización de uso de imagen del docente.
            'imagen_id' => ['nullable', 'uuid', Rule::exists('portal_imagenes', 'id')],
        ];
    }

    protected function atributos(): array
    {
        return ['nombre' => 'nombre', 'cargo' => 'cargo o área', 'nivel_id' => 'nivel', 'imagen_id' => 'foto'];
    }

    /** El orden siempre va dentro de un nivel. */
    public function ordenar(Request $peticion): JsonResponse
    {
        $peticion->validate(['nivel' => ['required', 'uuid', Rule::exists('portal_docentes_niveles', 'id')]]);

        return parent::ordenar($peticion);
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if ($fila && isset($v['nivel_id']) && $v['nivel_id'] !== $fila->nivel_id) {
            $v['orden'] = (int) Docente::where('nivel_id', $v['nivel_id'])->max('orden') + 1;
        }

        if (! $fila) {
            $v['clave'] = $this->claveUnica($v['nombre'], 'docente');
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'clave', 'nombre', 'cargo', 'nivel_id', 'imagen_id', 'orden', 'estado']) + [
            'imagen' => $fila->imagen_id ? Imagen::find($fila->imagen_id)?->aPanel() : null,
        ];
    }
}
