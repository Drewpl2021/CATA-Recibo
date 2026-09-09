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
        return $this->responderListado(
            $request,
            Cargo::query()->orderBy('nombre'),
            ['nombre', 'descripcion'],
            // Las cifras de la cabecera: se cuentan sobre todo lo que pasa el
            // filtro, no sobre la página que se está viendo.
            fn (Builder $filtrada) => $this->conteoPorEstado($filtrada, 'estado', ['activos' => 'activo', 'inactivos' => 'inactivo'])
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:cargos,nombre',
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|string|in:activo,inactivo',
        ]);
        $cargo = Cargo::create($request->all());
        return response()->json(['success' => true, 'data' => $cargo], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => Cargo::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $cargo = Cargo::findOrFail($id);
        $request->validate([
            'nombre' => ['sometimes', 'string', 'max:100', Rule::unique('cargos', 'nombre')->ignore($id)],
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|string|in:activo,inactivo',
        ]);
        $cargo->update($request->all());
        return response()->json(['success' => true, 'data' => $cargo]);
    }

    public function destroy(string $id)
    {
        Cargo::findOrFail($id)->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Cargo eliminado.']]);
    }
}