<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

    /**
     * POST /users/{id}/restablecer-password
     *
     * Para cuando alguien se queda fuera de su cuenta.
     *
     * Si tiene correo, se le envía «Tu acceso a CATA-Recibo» con el enlace para
     * crear una contraseña nueva (y la que tenía deja de servir). Si no tiene
     * correo, se genera una clave temporal aleatoria que se devuelve UNA sola
     * vez, para que RR.HH. se la entregue en persona; el sistema le obliga a
     * cambiarla al entrar. Nunca el DNI: no es un secreto, está en la boleta.
     */
    public function restablecerPassword(Request $request, string $id)
    {
        $user = User::with('rol', 'empleado')->findOrFail($id);
        $quienPide = $request->user();

        // Reponerse la contraseña a uno mismo por acá cerraría la sesión con
        // la que se está trabajando. Para eso está "Cambiar contraseña".
        if ($quienPide->id === $user->id) {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Para tu propia cuenta usa Cambiar contraseña, en tu perfil.'],
            ], 422);
        }

        // RR.HH. no puede reponerle la contraseña a un Administrador: sería
        // ponérsela él mismo y entrar con esa cuenta. Solo un Administrador
        // repone a otro Administrador.
        if ($user->rol?->nombre === 'admin' && $quienPide->rol?->nombre !== 'admin') {
            return response()->json([
                'success' => false,
                'data'    => ['message' => 'Solo un Administrador puede restablecer la contraseña de otro Administrador.'],
            ], 403);
        }

        // Con correo: el enlace, y la clave que tenía deja de servir.
        if ($user->email && filter_var($user->email, FILTER_VALIDATE_EMAIL) && $user->estado_registro === 'activo') {
            \App\Support\AccesoPorCorreo::enviar($user, true, true);

            return response()->json([
                'success' => true,
                'data'    => [
                    'message'    => "Le enviamos a {$user->email} el enlace para crear una contraseña nueva. Vence en 72 horas; la que tenía ya no sirve.",
                    'por_correo' => true,
                ],
            ]);
        }

        // Sin correo: una clave temporal aleatoria, que se muestra una sola vez.
        $nueva = Str::password(12, symbols: false);
        $user->update([
            'password'              => Hash::make($nueva),
            'debe_cambiar_password' => true,
        ]);
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'data'    => [
                'message'           => 'Contraseña restablecida. Entrégale esta clave temporal en persona: se muestra una sola vez, y el sistema le pedirá cambiarla al entrar.',
                'password_temporal' => $nueva,
                'por_correo'        => false,
            ],
        ]);
    }
}
