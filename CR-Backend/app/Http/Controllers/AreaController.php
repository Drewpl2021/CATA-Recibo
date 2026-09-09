<?php
namespace App\Http\Controllers;
use App\Models\Area;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Traits\ListadoPaginado;

class AreaController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /areas
     *
     * Sin ?page devuelve la lista completa (así la piden los desplegables
     * de los formularios). Con ?page&size la corta el servidor y manda
     * además el total, para que el frontend no tenga que traerse todo
     * para saber cuántos hay. Ver App\Traits\ListadoPaginado.
     */
    public function index(Request $request)
    {
        return $this->responderListado(
            $request,
            Area::query()->orderBy('nombre'),
            ['nombre', 'descripcion'],
            // Las cifras de la cabecera: se cuentan sobre todo lo que pasa el
            // filtro, no sobre la página que se está viendo.
            fn (Builder $filtrada) => $this->conteoPorEstado($filtrada, 'estado', ['activos' => 'activo', 'inactivos' => 'inactivo'])
        );
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:100|unique:areas,nombre',
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|string|in:activo,inactivo',
        ]);
        $area = Area::create($datos);
        return response()->json(['success' => true, 'data' => $area], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => Area::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $area = Area::findOrFail($id);
        $datos = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:100', Rule::unique('areas', 'nombre')->ignore($id)],
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|string|in:activo,inactivo',
        ]);
        $area->update($datos);
        return response()->json(['success' => true, 'data' => $area]);
    }

    public function destroy(string $id)
    {
        Area::findOrFail($id)->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Area eliminada.']]);
    }
}