<?php
namespace App\Http\Controllers;
use App\Models\Cargo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Traits\ListadoPaginado;

class CargoController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /cargos
     *
     * Sin ?page devuelve la lista completa (así la piden los desplegables
     * de los formularios). Con ?page&size la corta el servidor y manda
     * además el total, para que el frontend no tenga que traerse todo
     * para saber cuántos hay. Ver App\Traits\ListadoPaginado.
     */
    public function index(Request $request)
    {
        $query = Cargo::query()->with('areas:id,nombre')->orderBy('nombre');

        /*
         * "Los cargos que puedo elegir en esta área": los suyos MÁS los
         * comodines (los que no están acotados a ninguna). Sin la segunda
         * mitad, elegir un área dejaría fuera a Practicante o Docente y
         * habría que acotar los 34 cargos a mano para que la lista sirviera.
         */
        if ($request->filled('area_id')) {
            $area = $request->input('area_id');

            $query->where(function (Builder $q) use ($area) {
                $q->whereHas('areas', fn (Builder $a) => $a->where('areas.id', $area))
                    ->orWhereDoesntHave('areas');
            });
        }

        return $this->responderListado(
            $request,
            $query,
            ['nombre', 'descripcion'],
            // Las cifras de la cabecera: se cuentan sobre todo lo que pasa el
            // filtro, no sobre la página que se está viendo.
            fn (Builder $filtrada) => $this->conteoPorEstado($filtrada, 'estado', ['activos' => 'activo', 'inactivos' => 'inactivo'])
        );
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:100|unique:cargos,nombre',
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|string|in:activo,inactivo',
            // Las áreas donde vale. Vacío o ausente = vale en todas.
            'area_ids'   => 'sometimes|array',
            'area_ids.*' => 'uuid|exists:areas,id',
        ]);
        $cargo = Cargo::create($datos);
        $cargo->areas()->sync($request->input('area_ids', []));
        return response()->json(['success' => true, 'data' => $cargo->load('areas:id,nombre')], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => Cargo::with('areas:id,nombre')->findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $cargo = Cargo::findOrFail($id);
        $datos = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:100', Rule::unique('cargos', 'nombre')->ignore($id)],
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|string|in:activo,inactivo',
            'area_ids'   => 'sometimes|array',
            'area_ids.*' => 'uuid|exists:areas,id',
        ]);
        $cargo->update($datos);
        // Solo si vienen: una edición que no las menciona no debe borrarlas.
        if ($request->has('area_ids')) {
            $cargo->areas()->sync($request->input('area_ids', []));
        }
        return response()->json(['success' => true, 'data' => $cargo->load('areas:id,nombre')]);
    }

    public function destroy(string $id)
    {
        Cargo::findOrFail($id)->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Cargo eliminado.']]);
    }
}