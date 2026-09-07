<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Traits\ListadoPaginado;

class UserController extends Controller
{
    use ListadoPaginado;

    /**
     * GET /users?page=&size=&search=
     *
     * Los usuarios dados de baja solo los ve el admin.
     */
    public function index(Request $request)
    {
        $query = User::with('rol', 'empleado');

        $rolNombre = $request->user()->rol?->nombre;
        if ($rolNombre !== 'admin') {
            $query->where('estado_registro', 'activo');
        }

        return $this->responderListado(
            $request,
            $query->orderBy('name'),
            ['name', 'email', 'empleado.dni'],
            // Las cifras de la cabecera: se cuentan sobre todo lo que pasa el
            // filtro, no sobre la página que se está viendo.
            fn (Builder $filtrada) => $this->conteoPorEstado($filtrada, 'estado_registro', ['activos' => 'activo', 'inactivos' => 'inactivo'])
        );
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
            'name'            => 'sometimes|string|max:255',
            'email'           => 'sometimes|email|unique:users,email,' . $id,
            'rol_id'          => 'sometimes|uuid|exists:roles,id',
            'empleado_id'     => 'nullable|uuid|exists:empleados,id',
            'estado_registro' => 'sometimes|in:activo,inactivo',
        ]);

        $esAdmin = $request->user()->rol?->nombre === 'admin';

        if ($request->has('rol_id') && !$esAdmin) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Solo un Administrador puede cambiar el rol de un usuario.'],
            ], 403);
        }

        // Reactivar a alguien devuelve acceso al sistema, así que es cosa del
        // Administrador. Sin esto, destroy() dejaba al usuario dado de baja sin
        // ninguna forma de recuperarlo desde la API.
        if ($request->has('estado_registro') && !$esAdmin) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Solo un Administrador puede activar o desactivar un usuario.'],
            ], 403);
        }

        // Si se le vuelve a desactivar por acá, también se le cierran las sesiones.
        if ($request->input('estado_registro') === 'inactivo') {
            $user->tokens()->delete();
        }

        $user->update($request->only(['name', 'email', 'rol_id', 'empleado_id', 'estado_registro']));
        $user->load('rol', 'empleado');

        return response()->json(['success' => true, 'data' => $user]);
    }

    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->update(['estado_registro' => 'inactivo']);
        $user->tokens()->delete();

        return response()->json(['success' => true, 'data' => ['message' => 'Usuario eliminado correctamente.']]);
    }
}