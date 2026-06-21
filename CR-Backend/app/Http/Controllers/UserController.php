<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('rol', 'empleado');

        $rolNombre = $request->user()->rol?->nombre;
        if ($rolNombre !== 'admin') {
            $query->where('estado_registro', 'activo');
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function show(string $id)
    {
        $user = User::with('rol', 'empleado')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $user]);
    }

    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'email'       => 'sometimes|email|unique:users,email,' . $id,
            'rol_id'      => 'sometimes|uuid|exists:roles,id',
            'empleado_id' => 'nullable|uuid|exists:empleados,id',
        ]);

        $user->update($request->only(['name', 'email', 'rol_id', 'empleado_id']));
        $user->load('rol', 'empleado');

        return response()->json(['success' => true, 'data' => $user]);
    }

    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->update(['estado_registro' => 'inactivo']);

        return response()->json(['success' => true, 'data' => ['message' => 'Usuario eliminado correctamente.']]);
    }
}