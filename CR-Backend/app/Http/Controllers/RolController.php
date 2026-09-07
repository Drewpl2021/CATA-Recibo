<?php
namespace App\Http\Controllers;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\ListadoPaginado;

class RolController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /roles
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
            Rol::query()->orderBy('nombre'),
            ['nombre', 'descripcion']
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:45|unique:roles,nombre',
            'descripcion' => 'nullable|string|max:255',
        ]);
        $rol = Rol::create($request->only(['nombre', 'descripcion']));
        return response()->json(['success' => true, 'data' => $rol], 201);
    }

    public function show(string $id)
    {
        return response()->json(['success' => true, 'data' => Rol::findOrFail($id)]);
    }

    public function update(Request $request, string $id)
    {
        $rol = Rol::findOrFail($id);
        $request->validate([
            'nombre' => 'sometimes|string|max:45|unique:roles,nombre,' . $id,
            'descripcion' => 'nullable|string|max:255',
        ]);
        $rol->update($request->only(['nombre', 'descripcion']));
        return response()->json(['success' => true, 'data' => $rol]);
    }

    public function destroy(string $id)
    {
        $rol = Rol::findOrFail($id);

        $usuariosConEsteRol = User::where('rol_id', $id)->count();
        if ($usuariosConEsteRol > 0) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => "No se puede eliminar: hay {$usuariosConEsteRol} usuario(s) con este rol asignado."],
            ], 409);
        }

        $rol->delete();
        return response()->json(['success' => true, 'data' => ['message' => 'Rol eliminado.']]);
    }
}