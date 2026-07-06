<?php
namespace App\Http\Controllers;

use App\Models\Modulo;
use Illuminate\Http\Request;

class ModuloController extends Controller
{
    public function index(Request $request)
    {
        $query = Modulo::with('moduloPadre', 'roles');

        if ($request->has('modulo_padre_id')) {
            $query->where('modulo_padre_id', $request->modulo_padre_id);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->orderBy('orden')->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'modulo_padre_id' => 'required|uuid|exists:modulo_padre,id',
            'nombre'          => 'required|string|max:255',
            'ruta'            => 'nullable|string|max:255',
            'icono'           => 'nullable|string|max:255',
            'orden'           => 'nullable|integer|min:0',
        ]);

        $modulo = Modulo::create($request->all());

        return response()->json(['success' => true, 'data' => $modulo], 201);
    }

    public function show(string $id)
    {
        $modulo = Modulo::with('moduloPadre', 'roles')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $modulo]);
    }

    public function update(Request $request, string $id)
    {
        $modulo = Modulo::findOrFail($id);

        $request->validate([
            'modulo_padre_id' => 'sometimes|uuid|exists:modulo_padre,id',
            'nombre'          => 'sometimes|string|max:255',
            'ruta'            => 'nullable|string|max:255',
            'icono'           => 'nullable|string|max:255',
            'orden'           => 'nullable|integer|min:0',
        ]);

        $modulo->update($request->all());

        return response()->json(['success' => true, 'data' => $modulo]);
    }

    public function destroy(string $id)
    {
        $modulo = Modulo::findOrFail($id);
        $modulo->update(['estado_registro' => 'inactivo']);

        return response()->json(['success' => true, 'data' => ['message' => 'Módulo eliminado correctamente.']]);
    }

    /**
     * Asigna o reemplaza los roles que pueden ver este módulo.
     * Recibe un array de rol_id y sincroniza la tabla pivote rol_modulo.
     */
    public function asignarRoles(Request $request, string $id)
    {
        $modulo = Modulo::findOrFail($id);

        $request->validate([
            'roles'   => 'required|array',
            'roles.*' => 'uuid|exists:roles,id',
        ]);

        $modulo->roles()->sync($request->roles);

        return response()->json([
            'success' => true,
            'data'    => $modulo->load('roles'),
            'message' => 'Roles actualizados para este módulo.'
        ]);
    }
}