<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Vacante;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * /web-portal/vacancies — las vacantes de cada nivel (contrato §3.12).
 *
 * `disponibilidad` es el `estado` del contrato (disponible, pocas, agotadas,
 * consultar); se llama distinto para no chocar con el estado de publicación.
 *
 * Solo cifras reales: el número es opcional y, con «agotadas» o
 * «consultar», no se guarda (el portal no lo mostraría).
 */
class VacantesController extends ListaDelPortalController
{
    /** Con estas no se muestra número. */
    private const SIN_NUMERO = ['agotadas', 'consultar'];

    protected function modelo(): string
    {
        return Vacante::class;
    }

    protected function entidad(): string
    {
        return 'el nivel';
    }

    protected function topePublicados(): ?int
    {
        return 6;
    }

    protected function plural(): string
    {
        return 'niveles publicados';
    }

    protected function rutasAfectadas(?Model $fila): array
    {
        return ['/v1/matricula/vacantes'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        $obligatorio = $fila ? 'sometimes' : 'required';

        return [
            'nivel'          => [$obligatorio, 'string', 'max:40'],
            'detalle'        => ['nullable', 'string', 'max:60'],
            'disponibilidad' => [$obligatorio, Rule::in(Vacante::DISPONIBILIDADES)],
            'vacantes'       => ['nullable', 'integer', 'between:0,9999'],
        ];
    }

    protected function atributos(): array
    {
        return ['nivel' => 'nivel', 'detalle' => 'detalle', 'disponibilidad' => 'estado de las vacantes', 'vacantes' => 'número de vacantes'];
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        $disponibilidad = $v['disponibilidad'] ?? $fila?->disponibilidad;
        if (in_array($disponibilidad, self::SIN_NUMERO, true)) {
            $v['vacantes'] = null;
        }

        if (! $fila) {
            $v['clave'] = $this->claveUnica($v['nivel'], 'nivel');
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'clave', 'nivel', 'detalle', 'disponibilidad', 'vacantes', 'orden', 'estado']);
    }
}
