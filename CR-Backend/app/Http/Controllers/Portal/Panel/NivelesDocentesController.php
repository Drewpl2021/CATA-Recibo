<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\DocenteNivel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * /web-portal/teacher-levels — los grupos de Personal Docente, con su
 * filtro (contrato §3.21), hasta 6 publicados. Se publican aunque no tengan
 * docentes; los docentes de un grupo en borrador no se ven.
 *
 * Un grupo con docentes no se elimina: primero se pasan a otro.
 */
class NivelesDocentesController extends ListaDelPortalController
{
    protected function modelo(): string
    {
        return DocenteNivel::class;
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
        return ['/v1/docentes'];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        return ['nombre' => [$fila ? 'sometimes' : 'required', 'string', 'max:40']];
    }

    protected function atributos(): array
    {
        return ['nombre' => 'nombre'];
    }

    public function destroy(Request $peticion, string $id): JsonResponse
    {
        $nivel = $this->buscar($peticion, $id);
        $cuantos = $nivel->docentes()->count();

        if ($cuantos > 0) {
            throw ValidationException::withMessages([
                'nivel' => "«{$nivel->nombre}» tiene {$cuantos} " . ($cuantos === 1 ? 'docente' : 'docentes')
                    . '. Pásalos a otro nivel antes de eliminarlo.',
            ]);
        }

        return parent::destroy($peticion, $id);
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (! $fila) {
            // El id del filtro en la dirección (?nivel=…): se arma una vez y no cambia.
            $v['clave'] = $this->claveUnica($v['nombre'], 'nivel');
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'clave', 'nombre', 'orden', 'estado']) + [
            'docentes' => $fila->docentes()->count(),
        ];
    }
}
