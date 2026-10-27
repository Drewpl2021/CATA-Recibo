<?php

namespace App\Http\Controllers\Portal\Panel;

use App\Models\Portal\Proyecto;
use App\Models\Portal\ProyectoCategoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * /web-portal/project-categories — las categorías del filtro de Proyectos
 * (contrato §3.17), hasta 8, en el orden del filtro.
 *
 * No tienen borrador: el portal las manda todas y oculta las que no tienen
 * proyectos. Una categoría con proyectos no se elimina (se quedarían sin
 * categoría): primero se pasan a otra.
 */
class CategoriasProyectoController extends ListaDelPortalController
{
    private const TOPE = 8;

    protected function modelo(): string
    {
        return ProyectoCategoria::class;
    }

    protected function entidad(): string
    {
        return 'la categoría';
    }

    protected function tieneEstado(): bool
    {
        return false;
    }

    /** El portal y el detalle de cada proyecto publicado de la categoría (su `categoriaNombre`). */
    protected function rutasAfectadas(?Model $fila): array
    {
        $detalles = $fila
            ? Proyecto::where('categoria_id', $fila->getKey())->where('estado', Proyecto::PUBLICADO)
                ->pluck('slug')->map(fn (string $slug) => "/v1/proyectos/{$slug}")->all()
            : [];

        return ['/v1/proyectos', ...$detalles];
    }

    protected function reglas(Request $peticion, ?Model $fila): array
    {
        return ['nombre' => [$fila ? 'sometimes' : 'required', 'string', 'max:40']];
    }

    protected function atributos(): array
    {
        return ['nombre' => 'nombre'];
    }

    public function store(Request $peticion): JsonResponse
    {
        if (ProyectoCategoria::count() >= self::TOPE) {
            throw ValidationException::withMessages([
                'nombre' => 'El filtro de Proyectos admite hasta ' . self::TOPE . ' categorías.',
            ]);
        }

        return parent::store($peticion);
    }

    public function destroy(Request $peticion, string $id): JsonResponse
    {
        $categoria = $this->buscar($peticion, $id);
        $cuantos = $categoria->proyectos()->count();

        if ($cuantos > 0) {
            throw ValidationException::withMessages([
                'categoria' => "La categoría «{$categoria->nombre}» tiene {$cuantos} " . ($cuantos === 1 ? 'proyecto' : 'proyectos')
                    . '. Pásalos a otra categoría antes de eliminarla.',
            ]);
        }

        return parent::destroy($peticion, $id);
    }

    protected function aColumnas(array $v, Request $peticion, ?Model $fila): array
    {
        if (! $fila) {
            // El id del filtro en la dirección (?categoria=…): se arma una vez y no cambia.
            $v['clave'] = $this->claveUnica($v['nombre'], 'categoria');
        }

        return $v;
    }

    protected function aPanel(Model $fila): array
    {
        return $fila->only(['id', 'clave', 'nombre', 'orden']) + [
            'proyectos' => $fila->proyectos()->count(),
        ];
    }
}
